<?php
/**
 * توابع کمکی و کلاس اصلی ربات
 */

// ===== API CALLS =====
function sendRequest(string $method, array $params = []): ?array
{
    $url = BOT_URL . '/' . $method;
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($params),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    ]);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result ? json_decode($result, true) : null;
}

function sendMessage(int $chatId, string $text, array $extra = []): ?array
{
    return sendRequest('sendMessage', array_merge([
        'chat_id'    => $chatId,
        'text'       => $text,
        'parse_mode' => 'HTML',
    ], $extra));
}

function editMessage(int $chatId, int $msgId, string $text, array $extra = []): ?array
{
    return sendRequest('editMessageText', array_merge([
        'chat_id'    => $chatId,
        'message_id' => $msgId,
        'text'       => $text,
        'parse_mode' => 'HTML',
    ], $extra));
}

function answerCallback(string $callbackId, string $text = '', bool $alert = false): void
{
    sendRequest('answerCallbackQuery', [
        'callback_query_id' => $callbackId,
        'text'              => $text,
        'show_alert'        => $alert,
    ]);
}

function sendDocument(int $chatId, string $fileId, string $caption = ''): ?array
{
    return sendRequest('sendDocument', [
        'chat_id'    => $chatId,
        'document'   => $fileId,
        'caption'    => $caption,
        'parse_mode' => 'HTML',
    ]);
}

function deleteMessage(int $chatId, int $msgId): void
{
    sendRequest('deleteMessage', ['chat_id' => $chatId, 'message_id' => $msgId]);
}

// ===== KEYBOARD HELPERS =====
function inlineKeyboard(array $buttons): array
{
    return ['inline_keyboard' => $buttons];
}

function replyKeyboard(array $buttons, bool $resize = true): array
{
    $rows = [];
    foreach ($buttons as $row) {
        $r = [];
        foreach ((array)$row as $btn) {
            $r[] = ['text' => $btn];
        }
        $rows[] = $r;
    }
    return ['keyboard' => $rows, 'resize_keyboard' => $resize, 'one_time_keyboard' => false];
}

function removeKeyboard(): array
{
    return ['remove_keyboard' => true];
}

// ===== FORMATTING =====
function formatPrice(int $price): string
{
    return number_format($price) . ' ' . CURRENCY;
}

function formatFileSize(int $bytes): string
{
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
    return round($bytes / 1048576, 1) . ' MB';
}

function formatDate(string $datetime): string
{
    return date('Y/m/d H:i', strtotime($datetime));
}

function isAdmin(int $userId): bool
{
    return in_array($userId, ADMIN_IDS);
}

function escapeHtml(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

// ===== MAIN BOT CORE =====
class BotCore
{
    private Database $db;
    private array $update;
    private int $chatId = 0;
    private int $userId = 0;
    private string $firstName = '';
    private ?string $username = null;

    public function __construct(Database $db, array $update)
    {
        $this->db     = $db;
        $this->update = $update;
    }

    public function handle(): void
    {
        if (isset($this->update['message'])) {
            $this->handleMessage($this->update['message']);
        } elseif (isset($this->update['callback_query'])) {
            $this->handleCallback($this->update['callback_query']);
        }
    }

    private function handleMessage(array $msg): void
    {
        $this->chatId    = $msg['chat']['id'];
        $this->userId    = $msg['from']['id'];
        $this->firstName = $msg['from']['first_name'] ?? '';
        $this->username  = $msg['from']['username'] ?? null;

        // Register/update user
        $this->db->upsertUser(
            $this->userId,
            $this->firstName,
            $msg['from']['last_name'] ?? null,
            $this->username
        );

        $user = $this->db->getUser($this->userId);
        if ($user && $user['is_banned']) {
            sendMessage($this->chatId, '⛔ حساب شما مسدود شده است.');
            return;
        }

        // Check user state for multi-step flows
        $stateInfo = $this->db->getState($this->userId);
        $state     = $stateInfo['state'];
        $stateData = $stateInfo['data'];

        // Route to admin or user handler
        if (isAdmin($this->userId)) {
            $handler = new AdminHandler($this->db, $this->userId, $this->chatId, $this->firstName);
            $handled = $handler->handleState($msg, $state, $stateData);
            if (!$handled) {
                $handler->handleMessage($msg);
            }
        } else {
            $handler = new UserHandler($this->db, $this->userId, $this->chatId, $this->firstName);
            $handled = $handler->handleState($msg, $state, $stateData);
            if (!$handled) {
                $handler->handleMessage($msg);
            }
        }
    }

    private function handleCallback(array $cb): void
    {
        $this->chatId    = $cb['message']['chat']['id'];
        $this->userId    = $cb['from']['id'];
        $this->firstName = $cb['from']['first_name'] ?? '';

        $user = $this->db->getUser($this->userId);
        if ($user && $user['is_banned']) {
            answerCallback($cb['id'], '⛔ حساب شما مسدود است.', true);
            return;
        }

        $data  = $cb['data'];
        $msgId = $cb['message']['message_id'];

        if (isAdmin($this->userId)) {
            $handler = new AdminHandler($this->db, $this->userId, $this->chatId, $this->firstName);
            $handler->handleCallback($cb['id'], $data, $msgId);
        } else {
            $handler = new UserHandler($this->db, $this->userId, $this->chatId, $this->firstName);
            $handler->handleCallback($cb['id'], $data, $msgId);
        }
    }
}
