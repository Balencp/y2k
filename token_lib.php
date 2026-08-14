<?php
/**
 * token_lib.php  (เอนจินกลาง)
 * -------------------------------------------------------------
 * รวม logic ทั้งหมดของระบบ token อัตโนมัติไว้ที่เดียว:
 *   - ต่อฐานข้อมูล (PDO + env vars เหมือน process_transferv2.php)
 *   - สร้างรหัส TOTP เอง (มาตรฐาน RFC 6238 เหมือน Google Authenticator)
 *   - ล็อกอินแบบ 2 สเต็ป (/api/admin/auth -> /api/admin/auth/2fa/verify)
 *   - ดึง token (JWT) จากผลลัพธ์แล้วเก็บลงตาราง wallet_tokens
 *
 * ไฟล์นี้ไม่มี output ใช้เป็นตัวรวมฟังก์ชัน (include โดยไฟล์อื่น)
 * ฟังก์ชันตั้งชื่อขึ้นต้น tok_ เพื่อกันชนกับโค้ดเดิมของคุณ
 *
 * ตั้งค่าทั้งหมดผ่าน environment variables (ดูวิธีตั้งในคำอธิบาย)
 * -------------------------------------------------------------
 */

/* ---------- อ่าน env ---------- */
function tok_env($name, $required = true, $default = '') {
    $v = getenv($name);
    if ($v === false || $v === '') {
        if ($required) throw new Exception("ยังไม่ได้ตั้งค่า environment variable: $name");
        return $default;
    }
    return $v;
}

/* ---------- ฐานข้อมูล ---------- */
function tok_pdo() {
    $host = tok_env('DB_HOST'); $db = tok_env('DB_NAME'); $user = tok_env('DB_USER');
    $pass = tok_env('DB_PASS'); $port = tok_env('DB_PORT');
    $dsn = "pgsql:host=$host;port=$port;dbname=$db;";
    try { return new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]); }
    catch (PDOException $e) { throw new Exception("เชื่อมต่อฐานข้อมูลไม่สำเร็จ: " . $e->getMessage()); }
}

function tok_ensure_table($pdo) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS public.wallet_tokens (
            name VARCHAR(100) PRIMARY KEY,
            backend_url TEXT,
            token TEXT,
            updated_at TIMESTAMP NOT NULL DEFAULT NOW()
        );
    ");
}

function tok_save_token($pdo, $name, $backendUrl, $token) {
    $stmt = $pdo->prepare("
        INSERT INTO public.wallet_tokens (name, backend_url, token, updated_at)
        VALUES (:name, :url, :token, NOW())
        ON CONFLICT (name) DO UPDATE
        SET token = EXCLUDED.token, backend_url = EXCLUDED.backend_url, updated_at = NOW()
    ");
    $stmt->execute(['name' => $name, 'url' => $backendUrl, 'token' => $token]);
}

function tok_load_token($pdo, $name) {
    $stmt = $pdo->prepare("SELECT token, backend_url, updated_at FROM public.wallet_tokens WHERE name = :name");
    $stmt->execute(['name' => $name]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/* ---------- TOTP (RFC 6238) ---------- */
function tok_base32_decode($b32) {
    $map = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $b32 = strtoupper(str_replace('=', '', trim($b32)));
    $bits = '';
    for ($i = 0; $i < strlen($b32); $i++) {
        $pos = strpos($map, $b32[$i]);
        if ($pos === false) continue; // ข้ามอักขระที่ไม่ใช่ base32
        $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
    }
    $bytes = '';
    for ($i = 0; $i + 8 <= strlen($bits); $i += 8) {
        $bytes .= chr(bindec(substr($bits, $i, 8)));
    }
    return $bytes;
}

// สร้างรหัส 6 หลักของช่วงเวลาปัจจุบัน (หรือเวลาที่กำหนดผ่าน $forTime สำหรับทดสอบ)
function tok_totp($secret, $forTime = null, $digits = 6, $period = 30) {
    $key = tok_base32_decode($secret);
    $time = ($forTime === null) ? time() : $forTime;
    $counter = intdiv($time, $period);
    $binCounter = pack('N*', 0) . pack('N*', $counter); // 8-byte big-endian
    $hash = hash_hmac('sha1', $binCounter, $key, true);
    $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
    $part = (ord($hash[$offset]) & 0x7F) << 24
          | (ord($hash[$offset + 1]) & 0xFF) << 16
          | (ord($hash[$offset + 2]) & 0xFF) << 8
          | (ord($hash[$offset + 3]) & 0xFF);
    $otp = $part % (10 ** $digits);
    return str_pad((string)$otp, $digits, '0', STR_PAD_LEFT);
}

// วินาทีที่เหลือของช่วง TOTP ปัจจุบัน
function tok_totp_seconds_left($period = 30) {
    return $period - (time() % $period);
}

/* ---------- ยิงไป backend (server-side = ไม่ติด CORS) ---------- */
function tok_backend_post($url, $payload, $origin) {
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $headers = [
        'Accept: application/json, text/plain, */*',
        'Content-Type: application/json;charset=UTF-8',
        'Origin: ' . $origin,
        'Referer: ' . rtrim($origin, '/') . '/',
        'X-Requested-With: wallet-admin',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36',
    ];

    // ถ้าไม่มี cURL ให้ใช้ stream (file_get_contents) แทน
    if (!function_exists('curl_init')) {
        $ctx = stream_context_create(['http' => [
            'method'        => 'POST',
            'header'        => implode("\r\n", $headers),
            'content'       => $json,
            'timeout'       => 30,
            'ignore_errors' => true,
        ]]);
        $body = @file_get_contents($url, false, $ctx);
        if ($body === false) throw new Exception('ยิงไป backend ไม่สำเร็จ (stream)');
        $status = 0;
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
            $status = (int)$m[1];
        }
        return [$status, json_decode($body, true), $body];
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $json,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    $body = curl_exec($ch);
    if ($body === false) {
        $err = curl_error($ch); curl_close($ch);
        throw new Exception('ยิงไป backend ไม่สำเร็จ: ' . $err);
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$status, json_decode($body, true), $body];
}

/* ---------- หา JWT จาก response แบบไม่รู้ชื่อฟิลด์ ---------- */
function tok_find_jwt($data) {
    if (is_string($data)) {
        if (preg_match('/eyJ[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+/', $data, $m)) return $m[0];
        return null;
    }
    if (is_array($data)) {
        foreach ($data as $v) { $r = tok_find_jwt($v); if ($r) return $r; }
    }
    return null;
}

/* ---------- อ่านค่า exp จาก JWT (ไว้โชว์เวลาใกล้หมดอายุ) ---------- */
function tok_jwt_exp($jwt) {
    $parts = explode('.', (string)$jwt);
    if (count($parts) < 2) return null;
    $payload = $parts[1];
    $payload = strtr($payload, '-_', '+/');
    $payload .= str_repeat('=', (4 - strlen($payload) % 4) % 4);
    $json = json_decode(base64_decode($payload), true);
    return isset($json['exp']) ? (int)$json['exp'] : null;
}

function tok_mask($tok) {
    if (!$tok) return '';
    $len = strlen($tok);
    if ($len <= 18) return substr($tok, 0, 6) . '...';
    return substr($tok, 0, 12) . '...' . substr($tok, -6);
}

/* ============================================================
 *  รีเฟรช token อัตโนมัติ (หัวใจของระบบ) — พอร์ตจาก 2FA_Login.py
 *  คืน array: ['success'=>bool, 'message'=>.., 'token_mask'=>.., 'exp'=>..]
 * ============================================================ */
function tok_refresh() {
    $origin   = tok_env('APP_ORIGIN');
    $backend  = rtrim(tok_env('APP_BACKEND'), '/');
    $username = tok_env('ADMIN_USERNAME');
    $password = tok_env('ADMIN_PASSWORD');
    $secret   = tok_env('TOTP_SECRET');
    $name     = tok_env('SITE_NAME');

    // สเต็ป 1: login user/pass
    [$st1, $j1] = tok_backend_post("$backend/api/admin/auth",
        ['username' => $username, 'password' => $password], $origin);
    if ($st1 !== 200) {
        return ['success' => false, 'message' => "ล็อกอินไม่สำเร็จ (backend ตอบ $st1)", 'raw' => $j1];
    }

    // บางระบบอาจได้ token เลยโดยไม่ต้อง 2FA
    if (empty($j1['twoFactorRequired'])) {
        $tok = tok_find_jwt($j1);
        if ($tok) return tok__store_and_ok($name, $backend, $tok);
        return ['success' => false, 'message' => 'ล็อกอินผ่านแต่ไม่พบ token และไม่ได้ขอ 2FA', 'raw' => $j1];
    }

    $tempToken = $j1['tempToken'] ?? null;
    if (!$tempToken) {
        return ['success' => false, 'message' => 'ระบบขอ 2FA แต่ไม่พบ tempToken', 'raw' => $j1];
    }

    // ถ้าใกล้หมดช่วง TOTP (<3 วิ) รอให้ขึ้นช่วงใหม่ก่อน กันรหัสหมดอายุกลางคัน
    if (tok_totp_seconds_left() < 3) { sleep(3); }
    $code = tok_totp($secret);

    // สเต็ป 2: verify 2FA
    [$st2, $j2] = tok_backend_post("$backend/api/admin/auth/2fa/verify",
        ['tempToken' => $tempToken, 'code' => $code], $origin);
    if ($st2 !== 200) {
        return ['success' => false, 'message' => "ยืนยัน 2FA ไม่สำเร็จ (backend ตอบ $st2)", 'raw' => $j2];
    }

    $tok = tok_find_jwt($j2);
    if (!$tok) {
        return ['success' => false, 'message' => 'ยืนยัน 2FA ผ่านแต่หา token ในผลลัพธ์ไม่เจอ', 'raw' => $j2];
    }
    return tok__store_and_ok($name, $backend, $tok);
}

/* ============================================================
 *  แบบไม่แตะ DB — คืน token สดๆ (ใช้กับ get_token.php)
 *  คืน ['success'=>bool, 'token'=>.., 'message'=>..]
 * ============================================================ */
function tok_refresh_no_db($prefix = '') {
    $origin   = tok_env($prefix . 'APP_ORIGIN');
    $backend  = rtrim(tok_env($prefix . 'APP_BACKEND'), '/');
    $username = tok_env($prefix . 'ADMIN_USERNAME');
    $password = tok_env($prefix . 'ADMIN_PASSWORD');
    $secret   = tok_env($prefix . 'TOTP_SECRET');

    [$st1, $j1] = tok_backend_post("$backend/api/admin/auth",
        ['username' => $username, 'password' => $password], $origin);
    if ($st1 !== 200) return ['success' => false, 'message' => "ล็อกอินไม่สำเร็จ (backend ตอบ $st1)", 'raw' => $j1];

    if (empty($j1['twoFactorRequired'])) {
        $tok = tok_find_jwt($j1);
        return $tok ? ['success' => true, 'token' => $tok]
                    : ['success' => false, 'message' => 'ล็อกอินผ่านแต่ไม่พบ token', 'raw' => $j1];
    }

    $tempToken = $j1['tempToken'] ?? null;
    if (!$tempToken) return ['success' => false, 'message' => 'ระบบขอ 2FA แต่ไม่พบ tempToken', 'raw' => $j1];

    if (tok_totp_seconds_left() < 3) { sleep(3); }
    $code = tok_totp($secret);

    [$st2, $j2] = tok_backend_post("$backend/api/admin/auth/2fa/verify",
        ['tempToken' => $tempToken, 'code' => $code], $origin);
    if ($st2 !== 200) return ['success' => false, 'message' => "ยืนยัน 2FA ไม่สำเร็จ (backend ตอบ $st2)", 'raw' => $j2];

    $tok = tok_find_jwt($j2);
    return $tok ? ['success' => true, 'token' => $tok]
                : ['success' => false, 'message' => 'ยืนยัน 2FA ผ่านแต่หา token ไม่เจอ', 'raw' => $j2];
}

function tok__store_and_ok($name, $backend, $tok) {
    $pdo = tok_pdo();
    tok_ensure_table($pdo);
    tok_save_token($pdo, $name, $backend, $tok);
    $exp = tok_jwt_exp($tok);
    return [
        'success'    => true,
        'message'    => 'ได้ token ใหม่และบันทึกลงฐานข้อมูลแล้ว',
        'token_mask' => tok_mask($tok),
        'exp'        => $exp,
        'exp_text'   => $exp ? date('Y-m-d H:i:s', $exp) : null,
    ];
}
