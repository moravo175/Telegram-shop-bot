<?php
/**
 * Telegram File Shop Bot
 * ربات فروشگاه فایل تلگرام
 */

define('ROOT_PATH', __DIR__);
require_once ROOT_PATH . '/config.php';
require_once ROOT_PATH . '/database.php';
require_once ROOT_PATH . '/zarinpal.php';
require_once ROOT_PATH . '/helpers.php';
require_once ROOT_PATH . '/handlers/UserHandler.php';
require_once ROOT_PATH . '/handlers/AdminHandler.php';
require_once ROOT_PATH . '/handlers/PaymentHandler.php';

// Get incoming update
$input = file_get_contents('php://input');
$update = json_decode($input, true);

if (!$update) {
    exit;
}

// Initialize DB
$db = Database::getInstance();

// Route update
$bot = new BotCore($db, $update);
$bot->handle();
