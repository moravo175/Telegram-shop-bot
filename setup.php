<?php
/**
 * اسکریپت راه‌اندازی اولیه ربات
 * آدرس: https://yourdomain.com/setup.php
 * پس از اجرا این فایل را حذف کنید!
 */

define('ROOT_PATH', __DIR__);
require_once 'config.php';
require_once 'database.php';
require_once 'helpers.php';

$action = $_GET['action'] ?? 'info';

echo "<!DOCTYPE html><html dir='rtl' lang='fa'><head><meta charset='UTF-8'><title>Setup</title>";
echo "<style>body{font-family:Tahoma;padding:20px;background:#f5f5f5;} .box{background:white;padding:20px;border-radius:10px;margin:10px 0;} pre{background:#333;color:#eee;padding:15px;border-radius:8px;overflow:auto;} .btn{display:inline-block;padding:10px 20px;background:#2196F3;color:white;text-decoration:none;border-radius:6px;margin:5px;}</style>";
echo "</head><body><h1>🤖 راه‌اندازی ربات فروشگاه فایل</h1>";

// Test DB connection
echo "<div class='box'><h2>🗄 دیتابیس</h2>";
try {
    $db = Database::getInstance();
    echo "✅ اتصال به دیتابیس برقرار شد<br>";
    echo "✅ جداول با موفقیت ایجاد شدند";
} catch (Exception $e) {
    echo "❌ خطا: " . $e->getMessage();
}
echo "</div>";

// Set webhook
echo "<div class='box'><h2>🔗 Webhook</h2>";
if ($action === 'set_webhook') {
    $result = sendRequest('setWebhook', [
        'url'             => WEBHOOK_URL,
        'allowed_updates' => ['message', 'callback_query'],
        'drop_pending_updates' => true,
    ]);
    if ($result && $result['ok']) {
        echo "✅ Webhook با موفقیت تنظیم شد:<br><code>" . WEBHOOK_URL . "</code>";
    } else {
        echo "❌ خطا در تنظیم webhook: " . json_encode($result);
    }
} elseif ($action === 'delete_webhook') {
    $result = sendRequest('deleteWebhook');
    echo $result['ok'] ? "✅ Webhook حذف شد" : "❌ خطا";
} else {
    $info = sendRequest('getWebhookInfo');
    if ($info && $info['ok']) {
        $w = $info['result'];
        echo "URL: <code>" . ($w['url'] ?: 'تنظیم نشده') . "</code><br>";
        echo "آخرین خطا: " . ($w['last_error_message'] ?? '—') . "<br>";
        echo "پیام‌های معلق: " . ($w['pending_update_count'] ?? 0) . "<br><br>";
    }
    echo "<a href='?action=set_webhook' class='btn'>✅ تنظیم Webhook</a> ";
    echo "<a href='?action=delete_webhook' class='btn' style='background:#f44336'>❌ حذف Webhook</a>";
}
echo "</div>";

// Bot info
echo "<div class='box'><h2>🤖 اطلاعات ربات</h2>";
$botInfo = sendRequest('getMe');
if ($botInfo && $botInfo['ok']) {
    $b = $botInfo['result'];
    echo "👤 نام: <b>{$b['first_name']}</b><br>";
    echo "📱 یوزرنیم: @{$b['username']}<br>";
    echo "🆔 آیدی: <code>{$b['id']}</code>";
} else {
    echo "❌ خطا در دریافت اطلاعات ربات. توکن را چک کنید.";
}
echo "</div>";

echo "<div class='box'><h2>⚠️ مهم</h2>";
echo "پس از راه‌اندازی این فایل را از سرور حذف کنید!<br>";
echo "Admin ID های تنظیم شده: " . implode(', ', ADMIN_IDS);
echo "</div>";

echo "</body></html>";
