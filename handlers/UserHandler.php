<?php
/**
 * هندلر کاربران عادی
 */
class UserHandler
{
    private Database $db;
    private int $userId;
    private int $chatId;
    private string $firstName;

    public function __construct(Database $db, int $userId, int $chatId, string $firstName)
    {
        $this->db        = $db;
        $this->userId    = $userId;
        $this->chatId    = $chatId;
        $this->firstName = $firstName;
    }

    // ===== MAIN MENU =====
    public function handleMessage(array $msg): void
    {
        $text = $msg['text'] ?? '';

        switch ($text) {
            case '/start':
                $this->db->upsertUser(
                    $this->userId,
                    $msg['from']['first_name'] ?? '',
                    $msg['from']['last_name'] ?? null,
                    $msg['from']['username'] ?? null
                );
                $this->sendMainMenu();
                break;

            case '🛒 فروشگاه':
            case '/shop':
                $this->showShop();
                break;

            case '👛 کیف پول':
            case '/wallet':
                $this->showWallet();
                break;

            case '📦 خریدهای من':
            case '/purchases':
                $this->showMyPurchases();
                break;

            case '❓ راهنما':
            case '/help':
                $this->showHelp();
                break;

            default:
                $this->sendMainMenu();
        }
    }

    public function handleState(array $msg, int $state, ?array $data): bool
    {
        if ($state === STATE_USER_CHARGE_WALLET) {
            $this->processChargeAmount($msg);
            return true;
        }
        return false;
    }

    // ===== CALLBACK HANDLER =====
    public function handleCallback(string $cbId, string $data, int $msgId): void
    {
        $parts = explode(':', $data);
        $action = $parts[0];

        switch ($action) {
            case 'shop_page':
                $page = (int)($parts[1] ?? 1);
                answerCallback($cbId);
                $this->showShop($page, $msgId);
                break;

            case 'file_detail':
                $fileId = (int)($parts[1] ?? 0);
                answerCallback($cbId);
                $this->showFileDetail($fileId, $msgId);
                break;

            case 'buy_file':
                $fileId = (int)($parts[1] ?? 0);
                answerCallback($cbId);
                $this->buyFile($fileId, $msgId);
                break;

            case 'confirm_buy':
                $fileId = (int)($parts[1] ?? 0);
                answerCallback($cbId);
                $this->confirmBuy($fileId, $msgId);
                break;

            case 'download_file':
                $fileId = (int)($parts[1] ?? 0);
                answerCallback($cbId);
                $this->downloadFile($fileId);
                break;

            case 'wallet_charge':
                answerCallback($cbId);
                $this->askChargeAmount($msgId);
                break;

            case 'wallet_history':
                answerCallback($cbId);
                $this->showTransactionHistory($msgId);
                break;

            case 'back_main':
                answerCallback($cbId);
                $this->sendMainMenu($msgId);
                break;

            case 'back_shop':
                answerCallback($cbId);
                $this->showShop(1, $msgId);
                break;

            default:
                answerCallback($cbId, '⚠️ دستور نامعتبر');
        }
    }

    // ===== MAIN MENU =====
    private function sendMainMenu(?int $editMsgId = null): void
    {
        $user = $this->db->getUser($this->userId);
        $shopName = $this->db->getSetting('shop_name', 'فروشگاه فایل');

        $text = "🏪 <b>{$shopName}</b>\n\n";
        $text .= "سلام <b>" . escapeHtml($this->firstName) . "</b> عزیز! 👋\n\n";
        $text .= "💰 موجودی کیف پول: <b>" . formatPrice((int)($user['wallet'] ?? 0)) . "</b>\n\n";
        $text .= "از منوی زیر انتخاب کنید:";

        $keyboard = replyKeyboard([
            ['🛒 فروشگاه', '👛 کیف پول'],
            ['📦 خریدهای من', '❓ راهنما'],
        ]);

        if ($editMsgId) {
            editMessage($this->chatId, $editMsgId, $text, ['reply_markup' => $keyboard]);
        } else {
            sendMessage($this->chatId, $text, ['reply_markup' => $keyboard]);
        }
    }

    // ===== SHOP =====
    private function showShop(int $page = 1, ?int $editMsgId = null): void
    {
        $perPage = 5;
        $files   = $this->db->getAllFiles(true);
        $total   = count($files);

        if ($total === 0) {
            $text = "🛒 <b>فروشگاه</b>\n\n📭 در حال حاضر هیچ فایلی برای فروش موجود نیست.";
            if ($editMsgId) editMessage($this->chatId, $editMsgId, $text);
            else sendMessage($this->chatId, $text);
            return;
        }

        $totalPages = ceil($total / $perPage);
        $page       = max(1, min($page, $totalPages));
        $slice      = array_slice($files, ($page - 1) * $perPage, $perPage);

        $shopName = $this->db->getSetting('shop_name', 'فروشگاه فایل');
        $text = "🛒 <b>{$shopName}</b> — صفحه {$page} از {$totalPages}\n\n";
        $text .= "📁 <b>فایل‌های موجود برای فروش:</b>\n\n";

        $buttons = [];
        foreach ($slice as $f) {
            $text .= "🔹 <b>" . escapeHtml($f['name']) . "</b>\n";
            $text .= "   💰 قیمت: " . formatPrice((int)$f['price']) . "\n";
            $text .= "   📥 دانلود: " . $f['downloads'] . " بار\n\n";
            $buttons[] = [['text' => '📄 ' . $f['name'], 'callback_data' => 'file_detail:' . $f['id']]];
        }

        // Pagination
        $nav = [];
        if ($page > 1)          $nav[] = ['text' => '◀️ قبلی', 'callback_data' => 'shop_page:' . ($page - 1)];
        if ($page < $totalPages) $nav[] = ['text' => 'بعدی ▶️', 'callback_data' => 'shop_page:' . ($page + 1)];
        if ($nav) $buttons[] = $nav;

        $markup = ['inline_keyboard' => $buttons];
        if ($editMsgId) editMessage($this->chatId, $editMsgId, $text, ['reply_markup' => $markup]);
        else sendMessage($this->chatId, $text, ['reply_markup' => $markup]);
    }

    private function showFileDetail(int $fileId, ?int $editMsgId = null): void
    {
        $file = $this->db->getFile($fileId);
        if (!$file) {
            sendMessage($this->chatId, '⚠️ فایل یافت نشد.');
            return;
        }

        $purchased = $this->db->hasPurchased($this->userId, $fileId);
        $wallet    = $this->db->getUserWallet($this->userId);

        $text = "📄 <b>" . escapeHtml($file['name']) . "</b>\n\n";
        if ($file['description']) $text .= "📝 " . escapeHtml($file['description']) . "\n\n";
        $text .= "💰 قیمت: <b>" . formatPrice((int)$file['price']) . "</b>\n";
        $text .= "📥 تعداد دانلود: " . $file['downloads'] . " بار\n";
        $text .= "📅 تاریخ انتشار: " . formatDate($file['created_at']) . "\n\n";

        $buttons = [];
        if ($purchased) {
            $text .= "✅ <b>شما این فایل را خریداری کرده‌اید</b>";
            $buttons[] = [['text' => '⬇️ دانلود فایل', 'callback_data' => 'download_file:' . $fileId]];
        } else {
            $text .= "💳 موجودی کیف پول: <b>" . formatPrice($wallet) . "</b>";
            $buttons[] = [['text' => '🛍 خرید فایل', 'callback_data' => 'buy_file:' . $fileId]];
            $buttons[] = [['text' => '👛 شارژ کیف پول', 'callback_data' => 'wallet_charge']];
        }
        $buttons[] = [['text' => '🔙 بازگشت به فروشگاه', 'callback_data' => 'back_shop']];

        $markup = ['inline_keyboard' => $buttons];
        if ($editMsgId) editMessage($this->chatId, $editMsgId, $text, ['reply_markup' => $markup]);
        else sendMessage($this->chatId, $text, ['reply_markup' => $markup]);
    }

    // ===== PURCHASE =====
    private function buyFile(int $fileId, ?int $editMsgId = null): void
    {
        $file   = $this->db->getFile($fileId);
        $wallet = $this->db->getUserWallet($this->userId);

        if (!$file) {
            sendMessage($this->chatId, '⚠️ فایل یافت نشد.');
            return;
        }

        if ($this->db->hasPurchased($this->userId, $fileId)) {
            sendMessage($this->chatId, '✅ شما قبلاً این فایل را خریداری کرده‌اید.');
            return;
        }

        $price = (int)$file['price'];
        $text  = "🛍 <b>تأیید خرید</b>\n\n";
        $text .= "📄 فایل: <b>" . escapeHtml($file['name']) . "</b>\n";
        $text .= "💰 قیمت: <b>" . formatPrice($price) . "</b>\n";
        $text .= "👛 موجودی کیف پول: <b>" . formatPrice($wallet) . "</b>\n\n";

        if ($wallet >= $price) {
            $remaining = $wallet - $price;
            $text .= "✅ موجودی کافی است.\n";
            $text .= "💳 پس از خرید: <b>" . formatPrice($remaining) . "</b>\n\n";
            $text .= "آیا مطمئن هستید؟";
            $buttons = [
                [
                    ['text' => '✅ بله، خرید کن', 'callback_data' => 'confirm_buy:' . $fileId],
                    ['text' => '❌ انصراف', 'callback_data' => 'file_detail:' . $fileId],
                ]
            ];
        } else {
            $needed = $price - $wallet;
            $text .= "❌ موجودی کافی نیست.\n";
            $text .= "🔸 کمبود: <b>" . formatPrice($needed) . "</b>\n\n";
            $text .= "لطفاً ابتدا کیف پول خود را شارژ کنید.";
            $buttons = [
                [['text' => '👛 شارژ کیف پول', 'callback_data' => 'wallet_charge']],
                [['text' => '🔙 بازگشت', 'callback_data' => 'file_detail:' . $fileId]],
            ];
        }

        $markup = ['inline_keyboard' => $buttons];
        if ($editMsgId) editMessage($this->chatId, $editMsgId, $text, ['reply_markup' => $markup]);
        else sendMessage($this->chatId, $text, ['reply_markup' => $markup]);
    }

    private function confirmBuy(int $fileId, ?int $editMsgId = null): void
    {
        $file   = $this->db->getFile($fileId);
        $wallet = $this->db->getUserWallet($this->userId);

        if (!$file) {
            sendMessage($this->chatId, '⚠️ فایل یافت نشد.');
            return;
        }

        $price = (int)$file['price'];
        if ($wallet < $price) {
            sendMessage($this->chatId, '❌ موجودی کافی نیست. لطفاً کیف پول را شارژ کنید.');
            return;
        }

        if ($this->db->hasPurchased($this->userId, $fileId)) {
            sendMessage($this->chatId, '✅ شما قبلاً این فایل را خریداری کرده‌اید.');
            return;
        }

        // Deduct wallet
        $this->db->updateWallet($this->userId, -$price);
        $this->db->addPurchase($this->userId, $fileId, $price);
        $this->db->createTransaction($this->userId, $price, 'purchase', 'خرید فایل: ' . $file['name']);

        $text = "🎉 <b>خرید موفق!</b>\n\n";
        $text .= "✅ فایل <b>" . escapeHtml($file['name']) . "</b> با موفقیت خریداری شد.\n";
        $text .= "💰 مبلغ پرداخت شده: <b>" . formatPrice($price) . "</b>\n";
        $text .= "👛 موجودی باقیمانده: <b>" . formatPrice($wallet - $price) . "</b>\n\n";
        $text .= "برای دریافت فایل روی دکمه زیر کلیک کنید:";

        $buttons = [
            [['text' => '⬇️ دانلود فایل', 'callback_data' => 'download_file:' . $fileId]],
            [['text' => '🔙 بازگشت به فروشگاه', 'callback_data' => 'back_shop']],
        ];

        $markup = ['inline_keyboard' => $buttons];
        if ($editMsgId) editMessage($this->chatId, $editMsgId, $text, ['reply_markup' => $markup]);
        else sendMessage($this->chatId, $text, ['reply_markup' => $markup]);
    }

    private function downloadFile(int $fileId): void
    {
        if (!$this->db->hasPurchased($this->userId, $fileId)) {
            sendMessage($this->chatId, '❌ شما این فایل را خریداری نکرده‌اید.');
            return;
        }

        $file = $this->db->getFile($fileId);
        if (!$file) {
            sendMessage($this->chatId, '⚠️ فایل یافت نشد.');
            return;
        }

        $this->db->incrementDownloads($fileId);
        sendDocument($this->chatId, $file['file_id'], "📄 <b>" . escapeHtml($file['name']) . "</b>\n\n✅ فایل شما آماده دانلود است.");
    }

    // ===== MY PURCHASES =====
    private function showMyPurchases(): void
    {
        $purchases = $this->db->getUserPurchases($this->userId);

        if (empty($purchases)) {
            sendMessage($this->chatId, "📦 <b>خریدهای من</b>\n\n📭 شما هنوز هیچ فایلی خریداری نکرده‌اید.\n\n🛒 برای خرید به فروشگاه بروید.");
            return;
        }

        $text = "📦 <b>خریدهای من</b> (" . count($purchases) . " مورد)\n\n";
        $buttons = [];

        foreach ($purchases as $p) {
            $text .= "✅ <b>" . escapeHtml($p['name']) . "</b>\n";
            $text .= "   💰 " . formatPrice((int)$p['amount']) . " | 📅 " . formatDate($p['purchased_at']) . "\n\n";
            $buttons[] = [['text' => '⬇️ ' . $p['name'], 'callback_data' => 'download_file:' . $p['file_id']]];
        }

        sendMessage($this->chatId, $text, ['reply_markup' => ['inline_keyboard' => $buttons]]);
    }

    // ===== WALLET =====
    private function showWallet(?int $editMsgId = null): void
    {
        $wallet = $this->db->getUserWallet($this->userId);
        $txList = $this->db->getUserTransactions($this->userId, 5);
        $zarinpalActive = $this->db->getSetting('zarinpal_active') == '1';

        $text = "👛 <b>کیف پول</b>\n\n";
        $text .= "💰 موجودی: <b>" . formatPrice($wallet) . "</b>\n\n";

        if (!empty($txList)) {
            $text .= "📋 <b>آخرین تراکنش‌ها:</b>\n";
            foreach (array_slice($txList, 0, 3) as $tx) {
                $icon = match($tx['type']) {
                    'charge'   => '⬆️',
                    'purchase' => '⬇️',
                    'refund'   => '🔄',
                    default    => '•',
                };
                $sign = $tx['type'] === 'charge' ? '+' : '-';
                $text .= "{$icon} {$sign}" . formatPrice((int)$tx['amount']) . " — " . formatDate($tx['created_at']) . "\n";
            }
            $text .= "\n";
        }

        $buttons = [];
        if ($zarinpalActive) {
            $buttons[] = [['text' => '➕ شارژ کیف پول', 'callback_data' => 'wallet_charge']];
        } else {
            $text .= "⚠️ درگاه پرداخت در حال حاضر فعال نیست.";
        }
        $buttons[] = [['text' => '📋 تاریخچه تراکنش‌ها', 'callback_data' => 'wallet_history']];

        $markup = ['inline_keyboard' => $buttons];
        if ($editMsgId) editMessage($this->chatId, $editMsgId, $text, ['reply_markup' => $markup]);
        else sendMessage($this->chatId, $text, ['reply_markup' => $markup]);
    }

    private function askChargeAmount(?int $editMsgId = null): void
    {
        $this->db->setState($this->userId, STATE_USER_CHARGE_WALLET);
        $text = "💳 <b>شارژ کیف پول</b>\n\n";
        $text .= "لطفاً مبلغ مورد نظر را به تومان وارد کنید:\n\n";
        $text .= "📌 حداقل: " . formatPrice(MIN_CHARGE) . "\n";
        $text .= "📌 حداکثر: " . formatPrice(MAX_CHARGE) . "\n\n";
        $text .= "مثال: <code>50000</code>";

        if ($editMsgId) editMessage($this->chatId, $editMsgId, $text);
        else sendMessage($this->chatId, $text);
    }

    private function processChargeAmount(array $msg): void
    {
        $text   = trim($msg['text'] ?? '');
        $amount = (int)preg_replace('/[^0-9]/', '', $text);

        if ($amount < MIN_CHARGE || $amount > MAX_CHARGE) {
            sendMessage($this->chatId, "❌ مبلغ نامعتبر.\n\nلطفاً مبلغی بین " . formatPrice(MIN_CHARGE) . " و " . formatPrice(MAX_CHARGE) . " وارد کنید.");
            return;
        }

        $this->db->setState($this->userId, STATE_NONE);

        $zarinpal = ZarinPal::fromSettings($this->db);
        if (!$zarinpal) {
            sendMessage($this->chatId, '⚠️ درگاه پرداخت فعال نیست. لطفاً بعداً تلاش کنید.');
            return;
        }

        $callbackUrl = WEBHOOK_URL . '?payment_callback=1';
        $txId = $this->db->createTransaction($this->userId, $amount, 'charge', 'شارژ کیف پول');

        $result = $zarinpal->request(
            $amount,
            $callbackUrl,
            'شارژ کیف پول - ' . formatPrice($amount),
        );

        if ($result['success']) {
            $this->db->setAuthority($txId, $result['authority']);

            $text = "🔗 <b>لینک پرداخت</b>\n\n";
            $text .= "💰 مبلغ: <b>" . formatPrice($amount) . "</b>\n\n";
            $text .= "برای پرداخت روی دکمه زیر کلیک کنید:\n\n";
            $text .= "⚠️ این لینک ۱۵ دقیقه اعتبار دارد.";

            sendMessage($this->chatId, $text, [
                'reply_markup' => ['inline_keyboard' => [
                    [['text' => '💳 پرداخت آنلاین', 'url' => $result['url']]],
                ]]
            ]);
        } else {
            sendMessage($this->chatId, "❌ خطا در ایجاد لینک پرداخت:\n" . $result['error']);
        }
    }

    private function showTransactionHistory(?int $editMsgId = null): void
    {
        $txList = $this->db->getUserTransactions($this->userId, 10);

        if (empty($txList)) {
            $text = "📋 <b>تاریخچه تراکنش‌ها</b>\n\n📭 هیچ تراکنشی یافت نشد.";
        } else {
            $text = "📋 <b>تاریخچه تراکنش‌ها</b>\n\n";
            foreach ($txList as $tx) {
                $icon = match($tx['type']) {
                    'charge'   => '⬆️',
                    'purchase' => '⬇️',
                    'refund'   => '🔄',
                    default    => '•',
                };
                $statusIcon = match($tx['status']) {
                    'success' => '✅',
                    'pending' => '⏳',
                    'failed'  => '❌',
                    default   => '•',
                };
                $sign = $tx['type'] === 'charge' ? '+' : '-';
                $text .= "{$icon} {$statusIcon} {$sign}" . formatPrice((int)$tx['amount']) . "\n";
                $text .= "   📅 " . formatDate($tx['created_at']) . "\n";
                if ($tx['description']) $text .= "   📝 " . escapeHtml($tx['description']) . "\n";
                if ($tx['ref_id']) $text .= "   🔖 کد پیگیری: <code>" . $tx['ref_id'] . "</code>\n";
                $text .= "\n";
            }
        }

        $buttons = [[['text' => '🔙 بازگشت', 'callback_data' => 'wallet_charge']]];
        if ($editMsgId) editMessage($this->chatId, $editMsgId, $text, ['reply_markup' => ['inline_keyboard' => $buttons]]);
        else sendMessage($this->chatId, $text, ['reply_markup' => ['inline_keyboard' => $buttons]]);
    }

    // ===== HELP =====
    private function showHelp(): void
    {
        $shopName = $this->db->getSetting('shop_name', 'فروشگاه فایل');
        $text = "❓ <b>راهنمای ربات {$shopName}</b>\n\n";
        $text .= "🛒 <b>فروشگاه:</b> مشاهده و خرید فایل‌های موجود\n\n";
        $text .= "👛 <b>کیف پول:</b> مشاهده موجودی و شارژ از طریق زرین‌پال\n\n";
        $text .= "📦 <b>خریدهای من:</b> مشاهده و دانلود فایل‌های خریداری‌شده\n\n";
        $text .= "💡 <b>نحوه خرید:</b>\n";
        $text .= "1️⃣ ابتدا کیف پول خود را شارژ کنید\n";
        $text .= "2️⃣ فایل دلخواه را از فروشگاه انتخاب کنید\n";
        $text .= "3️⃣ روی دکمه خرید کلیک کنید\n";
        $text .= "4️⃣ فایل را دانلود کنید\n\n";
        $text .= "📞 در صورت بروز مشکل با پشتیبانی تماس بگیرید.";

        sendMessage($this->chatId, $text);
    }
}
