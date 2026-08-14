<?php
/**
 * get_token.php  (รองรับหลายสาขาในไฟล์เดียว)
 * -------------------------------------------------------------
 * ถูกเรียกเมื่อไหร่ ก็ล็อกอิน + สร้างรหัส 2FA เอง + ดึง token สดๆ ส่งกลับ
 * ไม่เก็บ DB ไม่ต้อง cron ไม่ต้องมีหน้าแอดมิน
 *
 * แยกสาขาด้วยพารามิเตอร์ ?site=  เช่น
 *     get_token.php?site=bl   -> ใช้ env ขึ้นต้น BL_
 *     get_token.php?site=k9   -> ใช้ env ขึ้นต้น K9_
 *     get_token.php           -> ใช้ env ธรรมดา (สาขาเดียว)
 *
 * env ต่อสาขา (ตัวอย่างสาขา K9):
 *   K9_SITE_NAME, K9_APP_ORIGIN, K9_APP_BACKEND,
 *   K9_ADMIN_USERNAME, K9_ADMIN_PASSWORD, K9_TOTP_SECRET
 * (สาขา BL ก็เปลี่ยน prefix เป็น BL_)
 *
 * คืน JSON: { "url": "...", "token": "eyJ...", "name": "..." }
 * -------------------------------------------------------------
 */

require __DIR__ . '/token_lib.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
date_default_timezone_set('Asia/Bangkok');

try {
    // ระบุสาขาจาก ?site= (อนุญาตเฉพาะ a-z0-9) -> prefix ตัวใหญ่ + _
    $site   = strtolower(preg_replace('/[^a-z0-9]/i', '', $_GET['site'] ?? ''));
    $prefix = ($site !== '') ? strtoupper($site) . '_' : '';

    $backend = rtrim(tok_env($prefix . 'APP_BACKEND'), '/');
    $name    = getenv($prefix . 'SITE_NAME');
    if ($name === false || $name === '') $name = ($site !== '') ? strtoupper($site) : '';

    // cache สั้นๆ ต่อสาขา (กันยิง backend รัวๆ เวลาหลายคนเปิดพร้อมกัน)
    $cacheKey  = $site !== '' ? $site : 'default';
    $cacheFile = sys_get_temp_dir() . '/wtok_' . preg_replace('/[^a-z0-9_]/i', '', $cacheKey) . '.json';
    $now = time();

    if (is_file($cacheFile)) {
        $c = json_decode(@file_get_contents($cacheFile), true);
        if ($c && !empty($c['token']) && !empty($c['exp']) && ($c['exp'] - $now) > 60) {
            echo json_encode(['url' => $backend, 'token' => $c['token'], 'name' => $name], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    $r = tok_refresh_no_db($prefix);   // login + TOTP + verify (ตาม prefix สาขา)
    if (empty($r['success'])) {
        http_response_code(502);
        echo json_encode(['url' => '', 'token' => '', 'name' => $name, 'error' => $r['message'] ?? 'refresh failed'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $token = $r['token'];
    $exp = tok_jwt_exp($token) ?: ($now + 600);
    @file_put_contents($cacheFile, json_encode(['token' => $token, 'exp' => $exp]));

    echo json_encode(['url' => $backend, 'token' => $token, 'name' => $name], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['url' => '', 'token' => '', 'name' => '', 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
