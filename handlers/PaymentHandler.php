<?php
/**
 * هندلر callback پرداخت زرین‌پال
 * این فایل جداگانه از webhook تلگرام استفاده می‌شود
 * یا می‌توان آن را در bot.php ادغام کرد
 */
class PaymentHandler
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * پردازش callback زرین‌پال
     * GET پارامترها: Authority, Status
     */
    public function handle(): void
    {
        $authority = $_GET['Authority'] ?? '';
        $status    = $_GET['Status'] ?? '';

        if (!$authority) {
            $this->redirect('❌ درخواست نامعتبر');
            return;
        }

        $tx = $this->db->getTransactionByAuthority($authority);
        if (!$tx) {
            $this->redirect('❌ تراکنش یافت نشد');
            return;
        }

        if ($status !== 'OK') {
            $this->db->updateTransaction((int)$tx['id'], 'failed');
            $this->notifyUser((int)$tx['user_id'], "❌ <b>پرداخت ناموفق</b>\n\nپرداخت شما لغو یا با خطا مواجه شد.\n💰 مبلغ: " . formatPrice((int)$tx['amount']));
            $this->redirect('❌ پرداخت لغو شد', false);
            return;
        }

        // Verify payment
        $zarinpal = ZarinPal::fromSettings($this->db);
        if (!$zarinpal) {
            $this->redirect('❌ خطای سرور');
            return;
        }

        $result = $zarinpal->verify($authority, (int)$tx['amount']);

        if ($result['success']) {
            // Update transaction
            $this->db->updateTransaction((int)$tx['id'], 'success', $result['ref_id']);

            // Charge wallet (unless it's a duplicate)
            if (!$result['duplicate']) {
                $this->db->updateWallet((int)$tx['user_id'], (int)$tx['amount']);
            }

            $msg = "✅ <b>پرداخت موفق!</b>\n\n";
            $msg .= "💰 مبلغ: <b>" . formatPrice((int)$tx['amount']) . "</b>\n";
            $msg .= "🔖 کد پیگیری: <code>" . $result['ref_id'] . "</code>\n";
            if ($result['duplicate']) {
                $msg .= "⚠️ این پرداخت قبلاً ثبت شده بود.";
            } else {
                $newBalance = $this->db->getUserWallet((int)$tx['user_id']);
                $msg .= "👛 موجودی جدید: <b>" . formatPrice($newBalance) . "</b>";
            }

            $this->notifyUser((int)$tx['user_id'], $msg);
            $this->redirect('✅ پرداخت موفق بود', true, $result['ref_id']);
        } else {
            $this->db->updateTransaction((int)$tx['id'], 'failed');
            $this->notifyUser((int)$tx['user_id'], "❌ <b>خطا در تأیید پرداخت</b>\n\n" . $result['error']);
            $this->redirect('❌ خطا در تأیید پرداخت', false);
        }
    }

    private function notifyUser(int $userId, string $message): void
    {
        sendMessage($userId, $message, [
            'reply_markup' => ['inline_keyboard' => [
                [['text' => '👛 مشاهده کیف پول', 'callback_data' => 'wallet_charge']],
                [['text' => '🛒 رفتن به فروشگاه', 'callback_data' => 'back_shop']],
            ]]
        ]);
    }

    private function redirect(string $message, bool $success = true, string $refId = ''): void
    {
        $color = $success ? '#4CAF50' : '#f44336';
        $icon  = $success ? '✅' : '❌';

        header('Content-Type: text/html; charset=utf-8');
        echo "<!DOCTYPE html>
<html dir='rtl' lang='fa'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>نتیجه پرداخت</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Tahoma', sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; background: #f5f5f5; }
        .card { background: white; border-radius: 16px; padding: 40px; text-align: center; max-width: 400px; width: 90%; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        .icon { font-size: 64px; margin-bottom: 20px; }
        h1 { color: {$color}; font-size: 24px; margin-bottom: 16px; }
        p { color: #666; margin-bottom: 10px; line-height: 1.6; }
        .ref { background: #f5f5f5; border-radius: 8px; padding: 10px; margin: 16px 0; font-family: monospace; font-size: 14px; }
        .btn { display: inline-block; background: #2196F3; color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; margin-top: 16px; }
    </style>
</head>
<body>
    <div class='card'>
        <div class='icon'>{$icon}</div>
        <h1>{$message}</h1>
        " . ($refId ? "<div class='ref'>کد پیگیری: {$refId}</div>" : '') . "
        <p>به ربات تلگرام بازگردید تا نتیجه را مشاهده کنید.</p>
        <a href='https://t.me/" . BOT_USERNAME . "' class='btn'>🤖 بازگشت به ربات</a>
    </div>
</body>
</html>";
        exit;
    }
}

// ===== Handle payment callback if called directly =====
if (isset($_GET['payment_callback'])) {
    define('ROOT_PATH', dirname(__DIR__));
    require_once ROOT_PATH . '/config.php';
    require_once ROOT_PATH . '/database.php';
    require_once ROOT_PATH . '/zarinpal.php';
    require_once ROOT_PATH . '/helpers.php';

    $db      = Database::getInstance();
    $handler = new PaymentHandler($db);
    $handler->handle();
}
