<?php
/**
 * تنظیمات ربات
 */

// ===== تنظیمات اصلی =====
define('BOT_TOKEN', 'YOUR_BOT_TOKEN_HERE');         // توکن ربات از BotFather
define('BOT_USERNAME', 'YOUR_BOT_USERNAME');         // یوزرنیم ربات بدون @
define('ADMIN_IDS', [123456789]);                    // آیدی عددی ادمین‌ها (می‌توانید چند آیدی بگذارید)
define('BOT_URL', 'https://api.telegram.org/bot' . BOT_TOKEN);
define('WEBHOOK_URL', 'https://yourdomain.com/bot.php'); // آدرس webhook شما

// ===== تنظیمات دیتابیس =====
define('DB_HOST', 'localhost');
define('DB_NAME', 'telegram_shop');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// ===== تنظیمات فایل =====
define('UPLOAD_PATH', ROOT_PATH . '/uploads/');
define('MAX_FILE_SIZE', 50 * 1024 * 1024); // 50MB
define('ALLOWED_EXTENSIONS', ['pdf', 'zip', 'rar', 'mp4', 'mp3', 'doc', 'docx', 'xlsx', 'png', 'jpg', 'jpeg']);

// ===== تنظیمات زرین‌پال (از پنل مدیریت قابل تغییر است) =====
define('ZARINPAL_REQUEST_URL', 'https://api.zarinpal.com/pg/v4/payment/request.json');
define('ZARINPAL_VERIFY_URL', 'https://api.zarinpal.com/pg/v4/payment/verify.json');
define('ZARINPAL_GATEWAY_URL', 'https://www.zarinpal.com/pg/StartPay/');
define('ZARINPAL_SANDBOX_REQUEST', 'https://sandbox.zarinpal.com/pg/v4/payment/request.json');
define('ZARINPAL_SANDBOX_GATEWAY', 'https://sandbox.zarinpal.com/pg/StartPay/');
define('ZARINPAL_SANDBOX_VERIFY', 'https://sandbox.zarinpal.com/pg/v4/payment/verify.json');

// ===== تنظیمات متفرقه =====
define('CURRENCY', 'تومان');
define('MIN_CHARGE', 1000);     // حداقل شارژ کیف پول (تومان)
define('MAX_CHARGE', 10000000); // حداکثر شارژ کیف پول (تومان)

// State keys for user sessions
define('STATE_NONE', 0);
define('STATE_ADMIN_ADD_FILE_NAME', 10);
define('STATE_ADMIN_ADD_FILE_DESC', 11);
define('STATE_ADMIN_ADD_FILE_PRICE', 12);
define('STATE_ADMIN_ADD_FILE_UPLOAD', 13);
define('STATE_ADMIN_EDIT_FILE_NAME', 20);
define('STATE_ADMIN_EDIT_FILE_PRICE', 21);
define('STATE_ADMIN_EDIT_FILE_DESC', 22);
define('STATE_ADMIN_SET_ZARINPAL_KEY', 30);
define('STATE_ADMIN_SET_ZARINPAL_SANDBOX', 31);
define('STATE_ADMIN_BROADCAST', 40);
define('STATE_USER_CHARGE_WALLET', 50);
