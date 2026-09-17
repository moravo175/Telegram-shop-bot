<?php
/**
 * هندلر پنل مدیریت ادمین
 */
class AdminHandler
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

    // ===== MESSAGE HANDLER =====
    public function handleMessage(array $msg): void
    {
        $text = $msg['text'] ?? '';

        switch ($text) {
            case '/start':
                $this->sendAdminMenu();
                break;
            case '🏠 پنل مدیریت':
            case '/admin':
                $this->sendAdminMenu();
                break;
            case '📁 مدیریت فایل‌ها':
                $this->showFileManager();
                break;
            case '👥 مدیریت کاربران':
                $this->showUserManager();
                break;
            case '💳 تنظیمات درگاه':
                $this->showPaymentSettings();
                break;
            case '📊 آمار و گزارش':
                $this->showStats();
                break;
            case '⚙️ تنظیمات ربات':
                $this->showBotSettings();
                break;
            case '📢 ارسال پیام همگانی':
                $this->startBroadcast();
                break;
            default:
                $this->sendAdminMenu();
        }
    }

    public function handleState(array $msg, int $state, ?array $data): bool
    {
        switch ($state) {
            case STATE_ADMIN_ADD_FILE_NAME:
                $this->processAddFileName($msg);
                return true;
            case STATE_ADMIN_ADD_FILE_DESC:
                $this->processAddFileDesc($msg, $data);
                return true;
            case STATE_ADMIN_ADD_FILE_PRICE:
                $this->processAddFilePrice($msg, $data);
                return true;
            case STATE_ADMIN_ADD_FILE_UPLOAD:
                $this->processAddFileUpload($msg, $data);
                return true;
            case STATE_ADMIN_EDIT_FILE_NAME:
                $this->processEditFileName($msg, $data);
                return true;
            case STATE_ADMIN_EDIT_FILE_PRICE:
                $this->processEditFilePrice($msg, $data);
                return true;
            case STATE_ADMIN_EDIT_FILE_DESC:
                $this->processEditFileDesc($msg, $data);
                return true;
            case STATE_ADMIN_SET_ZARINPAL_KEY:
                $this->processSetZarinpalKey($msg);
                return true;
            case STATE_ADMIN_BROADCAST:
                $this->processBroadcast($msg);
                return true;
        }
        return false;
    }

    // ===== CALLBACK HANDLER =====
    public function handleCallback(string $cbId, string $data, int $msgId): void
    {
        $parts  = explode(':', $data);
        $action = $parts[0];

        switch ($action) {
            case 'admin_files':
                answerCallback($cbId);
                $this->showFileManager($msgId);
                break;

            case 'admin_add_file':
                answerCallback($cbId);
                $this->startAddFile($msgId);
                break;

            case 'admin_file_list':
                $page = (int)($parts[1] ?? 1);
                answerCallback($cbId);
                $this->showAdminFileList($page, $msgId);
                break;

            case 'admin_file_detail':
                $fileId = (int)($parts[1] ?? 0);
                answerCallback($cbId);
                $this->showAdminFileDetail($fileId, $msgId);
                break;

            case 'admin_toggle_file':
                $fileId = (int)($parts[1] ?? 0);
                answerCallback($cbId);
                $this->toggleFile($fileId, $msgId);
                break;

            case 'admin_delete_file':
                $fileId = (int)($parts[1] ?? 0);
                answerCallback($cbId);
                $this->confirmDeleteFile($fileId, $msgId);
                break;

            case 'admin_confirm_delete':
                $fileId = (int)($parts[1] ?? 0);
                answerCallback($cbId, '🗑 فایل حذف شد.');
                $this->deleteFile($fileId, $msgId);
                break;

            case 'admin_edit_file_name':
                $fileId = (int)($parts[1] ?? 0);
                answerCallback($cbId);
                $this->startEditFileName($fileId, $msgId);
                break;

            case 'admin_edit_file_price':
                $fileId = (int)($parts[1] ?? 0);
                answerCallback($cbId);
                $this->startEditFilePrice($fileId, $msgId);
                break;

            case 'admin_edit_file_desc':
                $fileId = (int)($parts[1] ?? 0);
                answerCallback($cbId);
                $this->startEditFileDesc($fileId, $msgId);
                break;

            case 'admin_users':
                answerCallback($cbId);
                $this->showUserManager($msgId);
                break;

            case 'admin_user_list':
                $page = (int)($parts[1] ?? 1);
                answerCallback($cbId);
                $this->showUserList($page, $msgId);
                break;

            case 'admin_user_detail':
                $uid = (int)($parts[1] ?? 0);
                answerCallback($cbId);
                $this->showUserDetail($uid, $msgId);
                break;

            case 'admin_ban_user':
                $uid = (int)($parts[1] ?? 0);
                answerCallback($cbId);
                $this->toggleBanUser($uid, $msgId);
                break;

            case 'admin_payment':
                answerCallback($cbId);
                $this->showPaymentSettings($msgId);
                break;

            case 'admin_set_merchant':
                answerCallback($cbId);
                $this->askMerchantKey($msgId);
                break;

            case 'admin_toggle_zarinpal':
                answerCallback($cbId);
                $this->toggleZarinpal($msgId);
                break;

            case 'admin_toggle_sandbox':
                answerCallback($cbId);
                $this->toggleSandbox($msgId);
                break;

            case 'admin_stats':
                answerCallback($cbId);
                $this->showStats($msgId);
                break;

            case 'admin_transactions':
                answerCallback($cbId);
                $this->showTransactions($msgId);
                break;

            case 'admin_broadcast':
                answerCallback($cbId);
                $this->startBroadcast($msgId);
                break;

            case 'admin_settings':
                answerCallback($cbId);
                $this->showBotSettings($msgId);
                break;

            case 'admin_back':
                answerCallback($cbId);
                $this->sendAdminMenu($msgId);
                break;

            default:
                answerCallback($cbId, '⚠️ دستور نامعتبر');
        }
    }

    // ===== ADMIN MENU =====
    private function sendAdminMenu(?int $editMsgId = null): void
    {
        $shopName = $this->db->getSetting('shop_name', 'فروشگاه فایل');
        $userCount = $this->db->getUserCount();
        $fileCount = $this->db->getFileCount();
        $revenue   = $this->db->getTotalRevenue();
        $zarinpalActive = $this->db->getSetting('zarinpal_active') == '1' ? '✅ فعال' : '❌ غیرفعال';

        $text = "🔧 <b>پنل مدیریت {$shopName}</b>\n\n";
        $text .= "👥 کاربران: <b>{$userCount}</b>\n";
        $text .= "📁 فایل‌های فعال: <b>{$fileCount}</b>\n";
        $text .= "💰 کل درآمد: <b>" . formatPrice($revenue) . "</b>\n";
        $text .= "💳 درگاه زرین‌پال: <b>{$zarinpalActive}</b>\n\n";
        $text .= "از منوی زیر انتخاب کنید:";

        $keyboard = replyKeyboard([
            ['📁 مدیریت فایل‌ها', '👥 مدیریت کاربران'],
            ['💳 تنظیمات درگاه', '📊 آمار و گزارش'],
            ['⚙️ تنظیمات ربات', '📢 ارسال پیام همگانی'],
            ['🏠 پنل مدیریت'],
        ]);

        if ($editMsgId) editMessage($this->chatId, $editMsgId, $text);
        else sendMessage($this->chatId, $text, ['reply_markup' => $keyboard]);
    }

    // ===== FILE MANAGER =====
    private function showFileManager(?int $editMsgId = null): void
    {
        $fileCount = $this->db->getFileCount();
        $allFiles  = $this->db->getAllFiles(false);
        $active    = count(array_filter($allFiles, fn($f) => $f['is_active']));
        $inactive  = count($allFiles) - $active;

        $text = "📁 <b>مدیریت فایل‌ها</b>\n\n";
        $text .= "📊 مجموع فایل‌ها: <b>" . count($allFiles) . "</b>\n";
        $text .= "✅ فعال: <b>{$active}</b> | ❌ غیرفعال: <b>{$inactive}</b>";

        $buttons = [
            [['text' => '➕ افزودن فایل جدید', 'callback_data' => 'admin_add_file']],
            [['text' => '📋 لیست فایل‌ها', 'callback_data' => 'admin_file_list:1']],
            [['text' => '🔙 بازگشت', 'callback_data' => 'admin_back']],
        ];

        $markup = ['inline_keyboard' => $buttons];
        if ($editMsgId) editMessage($this->chatId, $editMsgId, $text, ['reply_markup' => $markup]);
        else sendMessage($this->chatId, $text, ['reply_markup' => $markup]);
    }

    private function showAdminFileList(int $page = 1, ?int $editMsgId = null): void
    {
        $files   = $this->db->getAllFiles(false);
        $perPage = 8;
        $total   = count($files);
        $totalPages = max(1, ceil($total / $perPage));
        $page    = max(1, min($page, $totalPages));
        $slice   = array_slice($files, ($page - 1) * $perPage, $perPage);

        $text = "📋 <b>لیست فایل‌ها</b> (صفحه {$page}/{$totalPages})\n\n";
        $buttons = [];

        foreach ($slice as $f) {
            $status = $f['is_active'] ? '✅' : '❌';
            $text .= "{$status} <b>" . escapeHtml($f['name']) . "</b> — " . formatPrice((int)$f['price']) . "\n";
            $buttons[] = [['text' => "{$status} " . $f['name'], 'callback_data' => 'admin_file_detail:' . $f['id']]];
        }

        $nav = [];
        if ($page > 1)           $nav[] = ['text' => '◀️', 'callback_data' => 'admin_file_list:' . ($page - 1)];
        if ($page < $totalPages) $nav[] = ['text' => '▶️', 'callback_data' => 'admin_file_list:' . ($page + 1)];
        if ($nav) $buttons[] = $nav;
        $buttons[] = [['text' => '🔙 بازگشت', 'callback_data' => 'admin_files']];

        $markup = ['inline_keyboard' => $buttons];
        if ($editMsgId) editMessage($this->chatId, $editMsgId, $text, ['reply_markup' => $markup]);
        else sendMessage($this->chatId, $text, ['reply_markup' => $markup]);
    }

    private function showAdminFileDetail(int $fileId, ?int $editMsgId = null): void
    {
        $file = $this->db->getFile($fileId);
        if (!$file) {
            sendMessage($this->chatId, '⚠️ فایل یافت نشد.');
            return;
        }

        $status = $file['is_active'] ? '✅ فعال' : '❌ غیرفعال';
        $text = "📄 <b>جزئیات فایل</b>\n\n";
        $text .= "🔹 نام: <b>" . escapeHtml($file['name']) . "</b>\n";
        $text .= "📝 توضیحات: " . escapeHtml($file['description'] ?: '—') . "\n";
        $text .= "💰 قیمت: <b>" . formatPrice((int)$file['price']) . "</b>\n";
        $text .= "📥 دانلودها: <b>" . $file['downloads'] . "</b>\n";
        $text .= "🔄 وضعیت: <b>{$status}</b>\n";
        $text .= "📅 تاریخ: " . formatDate($file['created_at']) . "\n";
        if ($file['file_size']) $text .= "📦 حجم: " . formatFileSize((int)$file['file_size']) . "\n";

        $toggleLabel = $file['is_active'] ? '🔴 غیرفعال کردن' : '🟢 فعال کردن';
        $buttons = [
            [
                ['text' => '✏️ ویرایش نام', 'callback_data' => 'admin_edit_file_name:' . $fileId],
                ['text' => '💰 ویرایش قیمت', 'callback_data' => 'admin_edit_file_price:' . $fileId],
            ],
            [
                ['text' => '📝 ویرایش توضیحات', 'callback_data' => 'admin_edit_file_desc:' . $fileId],
                ['text' => $toggleLabel, 'callback_data' => 'admin_toggle_file:' . $fileId],
            ],
            [['text' => '🗑 حذف فایل', 'callback_data' => 'admin_delete_file:' . $fileId]],
            [['text' => '🔙 بازگشت', 'callback_data' => 'admin_file_list:1']],
        ];

        $markup = ['inline_keyboard' => $buttons];
        if ($editMsgId) editMessage($this->chatId, $editMsgId, $text, ['reply_markup' => $markup]);
        else sendMessage($this->chatId, $text, ['reply_markup' => $markup]);
    }

    // ===== ADD FILE FLOW =====
    private function startAddFile(?int $editMsgId = null): void
    {
        $this->db->setState($this->userId, STATE_ADMIN_ADD_FILE_NAME);
        $text = "➕ <b>افزودن فایل جدید</b>\n\nمرحله 1/4: لطفاً <b>نام فایل</b> را وارد کنید:";
        if ($editMsgId) editMessage($this->chatId, $editMsgId, $text);
        else sendMessage($this->chatId, $text);
    }

    private function processAddFileName(array $msg): void
    {
        $name = trim($msg['text'] ?? '');
        if (strlen($name) < 2) {
            sendMessage($this->chatId, '⚠️ نام فایل باید حداقل ۲ کاراکتر باشد.');
            return;
        }
        $this->db->setState($this->userId, STATE_ADMIN_ADD_FILE_DESC, ['name' => $name]);
        sendMessage($this->chatId, "✅ نام: <b>" . escapeHtml($name) . "</b>\n\nمرحله 2/4: <b>توضیحات فایل</b> را وارد کنید (یا /skip برای رد کردن):");
    }

    private function processAddFileDesc(array $msg, ?array $data): void
    {
        $desc = trim($msg['text'] ?? '');
        if ($desc === '/skip') $desc = '';

        $data['description'] = $desc;
        $this->db->setState($this->userId, STATE_ADMIN_ADD_FILE_PRICE, $data);
        sendMessage($this->chatId, "مرحله 3/4: لطفاً <b>قیمت فایل</b> را به تومان وارد کنید (برای رایگان ۰ بزنید):");
    }

    private function processAddFilePrice(array $msg, ?array $data): void
    {
        $price = (int)preg_replace('/[^0-9]/', '', $msg['text'] ?? '');
        if ($price < 0) {
            sendMessage($this->chatId, '⚠️ قیمت نمی‌تواند منفی باشد.');
            return;
        }
        $data['price'] = $price;
        $this->db->setState($this->userId, STATE_ADMIN_ADD_FILE_UPLOAD, $data);
        sendMessage($this->chatId, "✅ قیمت: <b>" . formatPrice($price) . "</b>\n\nمرحله 4/4: لطفاً <b>فایل</b> را آپلود کنید:");
    }

    private function processAddFileUpload(array $msg, ?array $data): void
    {
        $fileInfo = null;
        $fileType = 'document';

        if (isset($msg['document'])) {
            $doc = $msg['document'];
            $fileInfo = ['file_id' => $doc['file_id'], 'file_size' => $doc['file_size'] ?? 0];
            $fileType = 'document';
        } elseif (isset($msg['photo'])) {
            $photo = end($msg['photo']);
            $fileInfo = ['file_id' => $photo['file_id'], 'file_size' => $photo['file_size'] ?? 0];
            $fileType = 'photo';
        } elseif (isset($msg['video'])) {
            $vid = $msg['video'];
            $fileInfo = ['file_id' => $vid['file_id'], 'file_size' => $vid['file_size'] ?? 0];
            $fileType = 'video';
        } elseif (isset($msg['audio'])) {
            $aud = $msg['audio'];
            $fileInfo = ['file_id' => $aud['file_id'], 'file_size' => $aud['file_size'] ?? 0];
            $fileType = 'audio';
        }

        if (!$fileInfo) {
            sendMessage($this->chatId, '⚠️ لطفاً یک فایل ارسال کنید.');
            return;
        }

        $fileId = $this->db->addFile([
            'name'        => $data['name'],
            'description' => $data['description'] ?? '',
            'price'       => $data['price'],
            'file_id'     => $fileInfo['file_id'],
            'file_type'   => $fileType,
            'file_size'   => $fileInfo['file_size'],
            'seller_id'   => $this->userId,
        ]);

        $this->db->setState($this->userId, STATE_NONE);

        $text = "✅ <b>فایل با موفقیت اضافه شد!</b>\n\n";
        $text .= "🆔 شناسه: <b>{$fileId}</b>\n";
        $text .= "📄 نام: <b>" . escapeHtml($data['name']) . "</b>\n";
        $text .= "💰 قیمت: <b>" . formatPrice((int)$data['price']) . "</b>\n";
        $text .= "📦 حجم: " . formatFileSize((int)$fileInfo['file_size']);

        sendMessage($this->chatId, $text, [
            'reply_markup' => ['inline_keyboard' => [
                [['text' => '📋 مشاهده فایل', 'callback_data' => 'admin_file_detail:' . $fileId]],
                [['text' => '➕ افزودن فایل دیگر', 'callback_data' => 'admin_add_file']],
            ]]
        ]);
    }

    // ===== EDIT FILE =====
    private function startEditFileName(int $fileId, ?int $editMsgId = null): void
    {
        $file = $this->db->getFile($fileId);
        $this->db->setState($this->userId, STATE_ADMIN_EDIT_FILE_NAME, ['file_id' => $fileId]);
        $text = "✏️ نام فعلی: <b>" . escapeHtml($file['name']) . "</b>\n\nنام جدید را وارد کنید:";
        if ($editMsgId) editMessage($this->chatId, $editMsgId, $text);
        else sendMessage($this->chatId, $text);
    }

    private function processEditFileName(array $msg, ?array $data): void
    {
        $name = trim($msg['text'] ?? '');
        if (strlen($name) < 2) { sendMessage($this->chatId, '⚠️ نام خیلی کوتاه است.'); return; }
        $fileId = $data['file_id'];
        $this->db->updateFile($fileId, ['name' => $name]);
        $this->db->setState($this->userId, STATE_NONE);
        sendMessage($this->chatId, "✅ نام با موفقیت به <b>" . escapeHtml($name) . "</b> تغییر یافت.", [
            'reply_markup' => ['inline_keyboard' => [[['text' => '🔙 بازگشت به فایل', 'callback_data' => 'admin_file_detail:' . $fileId]]]]
        ]);
    }

    private function startEditFilePrice(int $fileId, ?int $editMsgId = null): void
    {
        $file = $this->db->getFile($fileId);
        $this->db->setState($this->userId, STATE_ADMIN_EDIT_FILE_PRICE, ['file_id' => $fileId]);
        $text = "💰 قیمت فعلی: <b>" . formatPrice((int)$file['price']) . "</b>\n\nقیمت جدید را به تومان وارد کنید:";
        if ($editMsgId) editMessage($this->chatId, $editMsgId, $text);
        else sendMessage($this->chatId, $text);
    }

    private function processEditFilePrice(array $msg, ?array $data): void
    {
        $price = (int)preg_replace('/[^0-9]/', '', $msg['text'] ?? '');
        $fileId = $data['file_id'];
        $this->db->updateFile($fileId, ['price' => $price]);
        $this->db->setState($this->userId, STATE_NONE);
        sendMessage($this->chatId, "✅ قیمت به <b>" . formatPrice($price) . "</b> تغییر یافت.", [
            'reply_markup' => ['inline_keyboard' => [[['text' => '🔙 بازگشت به فایل', 'callback_data' => 'admin_file_detail:' . $fileId]]]]
        ]);
    }

    private function startEditFileDesc(int $fileId, ?int $editMsgId = null): void
    {
        $this->db->setState($this->userId, STATE_ADMIN_EDIT_FILE_DESC, ['file_id' => $fileId]);
        $text = "📝 توضیحات جدید را وارد کنید (یا /skip برای حذف توضیحات):";
        if ($editMsgId) editMessage($this->chatId, $editMsgId, $text);
        else sendMessage($this->chatId, $text);
    }

    private function processEditFileDesc(array $msg, ?array $data): void
    {
        $desc = trim($msg['text'] ?? '');
        if ($desc === '/skip') $desc = '';
        $fileId = $data['file_id'];
        $this->db->updateFile($fileId, ['description' => $desc]);
        $this->db->setState($this->userId, STATE_NONE);
        sendMessage($this->chatId, "✅ توضیحات با موفقیت به‌روز شد.", [
            'reply_markup' => ['inline_keyboard' => [[['text' => '🔙 بازگشت به فایل', 'callback_data' => 'admin_file_detail:' . $fileId]]]]
        ]);
    }

    private function toggleFile(int $fileId, ?int $editMsgId = null): void
    {
        $file = $this->db->getFile($fileId);
        $newStatus = $file['is_active'] ? 0 : 1;
        $this->db->updateFile($fileId, ['is_active' => $newStatus]);
        $label = $newStatus ? '✅ فعال شد' : '❌ غیرفعال شد';
        answerCallback('', $label);
        $this->showAdminFileDetail($fileId, $editMsgId);
    }

    private function confirmDeleteFile(int $fileId, ?int $editMsgId = null): void
    {
        $file = $this->db->getFile($fileId);
        $text = "🗑 <b>حذف فایل</b>\n\nآیا از حذف فایل «<b>" . escapeHtml($file['name']) . "</b>» مطمئن هستید؟\n\n⚠️ این عمل قابل بازگشت نیست!";
        $buttons = [
            [
                ['text' => '✅ بله، حذف کن', 'callback_data' => 'admin_confirm_delete:' . $fileId],
                ['text' => '❌ انصراف', 'callback_data' => 'admin_file_detail:' . $fileId],
            ]
        ];
        $markup = ['inline_keyboard' => $buttons];
        if ($editMsgId) editMessage($this->chatId, $editMsgId, $text, ['reply_markup' => $markup]);
        else sendMessage($this->chatId, $text, ['reply_markup' => $markup]);
    }

    private function deleteFile(int $fileId, ?int $editMsgId = null): void
    {
        $this->db->deleteFile($fileId);
        $text = "✅ فایل با موفقیت حذف شد.";
        $buttons = [[['text' => '📋 لیست فایل‌ها', 'callback_data' => 'admin_file_list:1']]];
        $markup = ['inline_keyboard' => $buttons];
        if ($editMsgId) editMessage($this->chatId, $editMsgId, $text, ['reply_markup' => $markup]);
        else sendMessage($this->chatId, $text, ['reply_markup' => $markup]);
    }

    // ===== USER MANAGER =====
    private function showUserManager(?int $editMsgId = null): void
    {
        $count  = $this->db->getUserCount();
        $text = "👥 <b>مدیریت کاربران</b>\n\n📊 تعداد کل کاربران: <b>{$count}</b>";

        $buttons = [
            [['text' => '📋 لیست کاربران', 'callback_data' => 'admin_user_list:1']],
            [['text' => '🔙 بازگشت', 'callback_data' => 'admin_back']],
        ];
        $markup = ['inline_keyboard' => $buttons];
        if ($editMsgId) editMessage($this->chatId, $editMsgId, $text, ['reply_markup' => $markup]);
        else sendMessage($this->chatId, $text, ['reply_markup' => $markup]);
    }

    private function showUserList(int $page = 1, ?int $editMsgId = null): void
    {
        $users   = $this->db->getAllUsers();
        $perPage = 8;
        $total   = count($users);
        $totalPages = max(1, ceil($total / $perPage));
        $page    = max(1, min($page, $totalPages));
        $slice   = array_slice($users, ($page - 1) * $perPage, $perPage);

        $text = "👥 <b>لیست کاربران</b> (صفحه {$page}/{$totalPages})\n\n";
        $buttons = [];

        foreach ($slice as $u) {
            $name = escapeHtml($u['first_name'] . ($u['last_name'] ? ' ' . $u['last_name'] : ''));
            $ban  = $u['is_banned'] ? ' ⛔' : '';
            $text .= "👤 <b>{$name}</b>{$ban}\n";
            $text .= "   🆔 {$u['id']} | 💰 " . formatPrice((int)$u['wallet']) . "\n\n";
            $buttons[] = [['text' => "👤 {$u['first_name']}{$ban}", 'callback_data' => 'admin_user_detail:' . $u['id']]];
        }

        $nav = [];
        if ($page > 1)           $nav[] = ['text' => '◀️', 'callback_data' => 'admin_user_list:' . ($page - 1)];
        if ($page < $totalPages) $nav[] = ['text' => '▶️', 'callback_data' => 'admin_user_list:' . ($page + 1)];
        if ($nav) $buttons[] = $nav;
        $buttons[] = [['text' => '🔙 بازگشت', 'callback_data' => 'admin_users']];

        $markup = ['inline_keyboard' => $buttons];
        if ($editMsgId) editMessage($this->chatId, $editMsgId, $text, ['reply_markup' => $markup]);
        else sendMessage($this->chatId, $text, ['reply_markup' => $markup]);
    }

    private function showUserDetail(int $uid, ?int $editMsgId = null): void
    {
        $user = $this->db->getUser($uid);
        if (!$user) { sendMessage($this->chatId, '⚠️ کاربر یافت نشد.'); return; }

        $purchases = $this->db->getUserPurchases($uid);
        $banLabel  = $user['is_banned'] ? '⛔ مسدود' : '✅ فعال';
        $banBtn    = $user['is_banned'] ? '🔓 رفع مسدودی' : '⛔ مسدود کردن';

        $text = "👤 <b>اطلاعات کاربر</b>\n\n";
        $text .= "🆔 آیدی: <code>{$uid}</code>\n";
        $text .= "👤 نام: <b>" . escapeHtml($user['first_name'] . ' ' . $user['last_name']) . "</b>\n";
        if ($user['username']) $text .= "📱 یوزرنیم: @" . $user['username'] . "\n";
        $text .= "💰 کیف پول: <b>" . formatPrice((int)$user['wallet']) . "</b>\n";
        $text .= "📦 خریدها: <b>" . count($purchases) . " مورد</b>\n";
        $text .= "🔄 وضعیت: <b>{$banLabel}</b>\n";
        $text .= "📅 عضویت: " . formatDate($user['joined_at']) . "\n";
        $text .= "🕐 آخرین بازدید: " . formatDate($user['last_seen']);

        $buttons = [
            [['text' => $banBtn, 'callback_data' => 'admin_ban_user:' . $uid]],
            [['text' => '🔙 بازگشت', 'callback_data' => 'admin_user_list:1']],
        ];
        $markup = ['inline_keyboard' => $buttons];
        if ($editMsgId) editMessage($this->chatId, $editMsgId, $text, ['reply_markup' => $markup]);
        else sendMessage($this->chatId, $text, ['reply_markup' => $markup]);
    }

    private function toggleBanUser(int $uid, ?int $editMsgId = null): void
    {
        $user = $this->db->getUser($uid);
        $newBan = $user['is_banned'] ? 0 : 1;
        $this->db->banUser($uid, $newBan);
        $this->showUserDetail($uid, $editMsgId);
    }

    // ===== PAYMENT SETTINGS =====
    private function showPaymentSettings(?int $editMsgId = null): void
    {
        $merchant = $this->db->getSetting('zarinpal_merchant');
        $active   = $this->db->getSetting('zarinpal_active') == '1';
        $sandbox  = $this->db->getSetting('zarinpal_sandbox') == '1';

        $text = "💳 <b>تنظیمات درگاه زرین‌پال</b>\n\n";
        $text .= "🔑 Merchant ID: <code>" . ($merchant ? substr($merchant, 0, 8) . '...' : 'تنظیم نشده') . "</code>\n";
        $text .= "🟢 وضعیت درگاه: <b>" . ($active ? '✅ فعال' : '❌ غیرفعال') . "</b>\n";
        $text .= "🧪 حالت آزمایشی: <b>" . ($sandbox ? '✅ فعال (Sandbox)' : '❌ غیرفعال (Production)') . "</b>\n\n";
        $text .= "📌 برای فعال کردن درگاه، ابتدا Merchant ID را وارد کنید سپس درگاه را فعال کنید.";

        $buttons = [
            [['text' => '🔑 تنظیم Merchant ID', 'callback_data' => 'admin_set_merchant']],
            [['text' => ($active ? '🔴 غیرفعال کردن' : '🟢 فعال کردن') . ' درگاه', 'callback_data' => 'admin_toggle_zarinpal']],
            [['text' => ($sandbox ? '🔵 خروج از Sandbox' : '🧪 فعال کردن Sandbox'), 'callback_data' => 'admin_toggle_sandbox']],
            [['text' => '🔙 بازگشت', 'callback_data' => 'admin_back']],
        ];

        $markup = ['inline_keyboard' => $buttons];
        if ($editMsgId) editMessage($this->chatId, $editMsgId, $text, ['reply_markup' => $markup]);
        else sendMessage($this->chatId, $text, ['reply_markup' => $markup]);
    }

    private function askMerchantKey(?int $editMsgId = null): void
    {
        $this->db->setState($this->userId, STATE_ADMIN_SET_ZARINPAL_KEY);
        $text = "🔑 <b>تنظیم Merchant ID زرین‌پال</b>\n\n";
        $text .= "لطفاً Merchant ID خود را از پنل زرین‌پال کپی و ارسال کنید:\n\n";
        $text .= "📌 فرمت: <code>xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx</code>\n\n";
        $text .= "برای دسترسی به Merchant ID وارد پنل زرین‌پال شوید:\n";
        $text .= "🔗 https://my.zarinpal.com/";

        if ($editMsgId) editMessage($this->chatId, $editMsgId, $text);
        else sendMessage($this->chatId, $text);
    }

    private function processSetZarinpalKey(array $msg): void
    {
        $key = trim($msg['text'] ?? '');

        // Basic validation for UUID format
        if (!preg_match('/^[a-f0-9\-]{36}$/i', $key)) {
            sendMessage($this->chatId, "⚠️ فرمت Merchant ID نامعتبر است.\n\nباید به فرمت <code>xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx</code> باشد.");
            return;
        }

        $this->db->setSetting('zarinpal_merchant', $key);
        $this->db->setState($this->userId, STATE_NONE);

        sendMessage($this->chatId, "✅ Merchant ID با موفقیت ذخیره شد.\n\nکلید: <code>" . substr($key, 0, 8) . "...</code>\n\nحالا می‌توانید درگاه را فعال کنید.", [
            'reply_markup' => ['inline_keyboard' => [
                [['text' => '💳 تنظیمات درگاه', 'callback_data' => 'admin_payment']],
            ]]
        ]);
    }

    private function toggleZarinpal(?int $editMsgId = null): void
    {
        $current = $this->db->getSetting('zarinpal_active');
        $merchant = $this->db->getSetting('zarinpal_merchant');

        if (!$current && !$merchant) {
            sendMessage($this->chatId, '⚠️ ابتدا Merchant ID را وارد کنید.');
            return;
        }

        $new = $current == '1' ? '0' : '1';
        $this->db->setSetting('zarinpal_active', $new);
        $this->showPaymentSettings($editMsgId);
    }

    private function toggleSandbox(?int $editMsgId = null): void
    {
        $current = $this->db->getSetting('zarinpal_sandbox');
        $this->db->setSetting('zarinpal_sandbox', $current == '1' ? '0' : '1');
        $this->showPaymentSettings($editMsgId);
    }

    // ===== STATS =====
    private function showStats(?int $editMsgId = null): void
    {
        $userCount   = $this->db->getUserCount();
        $fileCount   = $this->db->getFileCount();
        $revenue     = $this->db->getTotalRevenue();
        $purchases   = $this->db->getPurchaseCount();
        $txList      = $this->db->getRecentTransactions(5);

        $text = "📊 <b>آمار و گزارشات</b>\n\n";
        $text .= "👥 کل کاربران: <b>{$userCount}</b>\n";
        $text .= "📁 فایل‌های فعال: <b>{$fileCount}</b>\n";
        $text .= "🛍 خریدها: <b>{$purchases}</b>\n";
        $text .= "💰 کل درآمد: <b>" . formatPrice($revenue) . "</b>\n\n";

        if (!empty($txList)) {
            $text .= "📋 <b>آخرین تراکنش‌ها:</b>\n";
            foreach ($txList as $tx) {
                $icon = $tx['type'] === 'charge' ? '⬆️' : '⬇️';
                $name = escapeHtml($tx['first_name'] ?? '');
                $text .= "{$icon} {$name} — " . formatPrice((int)$tx['amount']) . " ({$tx['status']})\n";
            }
        }

        $buttons = [
            [['text' => '📋 تمام تراکنش‌ها', 'callback_data' => 'admin_transactions']],
            [['text' => '🔙 بازگشت', 'callback_data' => 'admin_back']],
        ];
        $markup = ['inline_keyboard' => $buttons];
        if ($editMsgId) editMessage($this->chatId, $editMsgId, $text, ['reply_markup' => $markup]);
        else sendMessage($this->chatId, $text, ['reply_markup' => $markup]);
    }

    private function showTransactions(?int $editMsgId = null): void
    {
        $txList = $this->db->getRecentTransactions(20);

        $text = "📋 <b>تراکنش‌های اخیر</b>\n\n";
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
            $name = escapeHtml($tx['first_name'] ?? $tx['user_id']);
            $text .= "{$icon} {$statusIcon} <b>{$name}</b> — " . formatPrice((int)$tx['amount']) . "\n";
            $text .= "   📅 " . formatDate($tx['created_at']);
            if ($tx['ref_id']) $text .= " | 🔖 " . $tx['ref_id'];
            $text .= "\n\n";
        }

        $buttons = [[['text' => '🔙 بازگشت', 'callback_data' => 'admin_stats']]];
        $markup = ['inline_keyboard' => $buttons];
        if ($editMsgId) editMessage($this->chatId, $editMsgId, $text, ['reply_markup' => $markup]);
        else sendMessage($this->chatId, $text, ['reply_markup' => $markup]);
    }

    // ===== BOT SETTINGS =====
    private function showBotSettings(?int $editMsgId = null): void
    {
        $settings = $this->db->getAllSettings();
        $text = "⚙️ <b>تنظیمات ربات</b>\n\n";
        $text .= "🏪 نام فروشگاه: <b>" . escapeHtml($settings['shop_name'] ?? '') . "</b>\n";
        $text .= "📝 توضیحات: " . escapeHtml($settings['shop_description'] ?? '') . "\n";
        $text .= "🔄 وضعیت ربات: <b>" . ($settings['bot_active'] == '1' ? '✅ فعال' : '❌ غیرفعال') . "</b>\n\n";
        $text .= "📌 برای تغییر تنظیمات با /set_name، /set_desc استفاده کنید.";

        $buttons = [[['text' => '🔙 بازگشت', 'callback_data' => 'admin_back']]];
        $markup = ['inline_keyboard' => $buttons];
        if ($editMsgId) editMessage($this->chatId, $editMsgId, $text, ['reply_markup' => $markup]);
        else sendMessage($this->chatId, $text, ['reply_markup' => $markup]);
    }

    // ===== BROADCAST =====
    private function startBroadcast(?int $editMsgId = null): void
    {
        $this->db->setState($this->userId, STATE_ADMIN_BROADCAST);
        $count = $this->db->getUserCount();
        $text = "📢 <b>ارسال پیام همگانی</b>\n\n";
        $text .= "👥 تعداد گیرندگان: <b>{$count}</b> کاربر\n\n";
        $text .= "پیام خود را ارسال کنید (متن، عکس، فایل پشتیبانی می‌شود):";

        if ($editMsgId) editMessage($this->chatId, $editMsgId, $text);
        else sendMessage($this->chatId, $text);
    }

    private function processBroadcast(array $msg): void
    {
        $this->db->setState($this->userId, STATE_NONE);
        $users = $this->db->getAllUsers();
        $success = 0;
        $fail    = 0;

        sendMessage($this->chatId, "⏳ در حال ارسال پیام به " . count($users) . " کاربر...");

        foreach ($users as $user) {
            if ($user['id'] == $this->userId) continue;

            try {
                if (isset($msg['text'])) {
                    $result = sendMessage($user['id'], $msg['text']);
                } elseif (isset($msg['photo'])) {
                    $photo = end($msg['photo']);
                    $result = sendRequest('sendPhoto', [
                        'chat_id'    => $user['id'],
                        'photo'      => $photo['file_id'],
                        'caption'    => $msg['caption'] ?? '',
                        'parse_mode' => 'HTML',
                    ]);
                } elseif (isset($msg['document'])) {
                    $result = sendRequest('sendDocument', [
                        'chat_id'    => $user['id'],
                        'document'   => $msg['document']['file_id'],
                        'caption'    => $msg['caption'] ?? '',
                        'parse_mode' => 'HTML',
                    ]);
                } else {
                    continue;
                }

                if ($result && $result['ok']) $success++;
                else $fail++;
            } catch (\Exception $e) {
                $fail++;
            }

            usleep(50000); // 50ms delay to avoid rate limiting
        }

        sendMessage($this->chatId, "✅ <b>ارسال تمام شد!</b>\n\n✅ موفق: <b>{$success}</b>\n❌ ناموفق: <b>{$fail}</b>");
    }
}
