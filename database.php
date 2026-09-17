<?php
/**
 * کلاس مدیریت دیتابیس
 */
class Database
{
    private static $instance = null;
    private $pdo;

    private function __construct()
    {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        $this->createTables();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    private function createTables(): void
    {
        $sql = "
        CREATE TABLE IF NOT EXISTS users (
            id            BIGINT PRIMARY KEY,
            username      VARCHAR(100),
            first_name    VARCHAR(100),
            last_name     VARCHAR(100),
            wallet        BIGINT DEFAULT 0,
            state         INT DEFAULT 0,
            state_data    TEXT,
            is_banned     TINYINT DEFAULT 0,
            joined_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_seen     DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS files (
            id            INT AUTO_INCREMENT PRIMARY KEY,
            name          VARCHAR(255) NOT NULL,
            description   TEXT,
            price         BIGINT NOT NULL DEFAULT 0,
            file_id       VARCHAR(255) NOT NULL,
            file_type     VARCHAR(50),
            file_size     BIGINT DEFAULT 0,
            seller_id     BIGINT,
            downloads     INT DEFAULT 0,
            is_active     TINYINT DEFAULT 1,
            created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS purchases (
            id            INT AUTO_INCREMENT PRIMARY KEY,
            user_id       BIGINT NOT NULL,
            file_id       INT NOT NULL,
            amount        BIGINT NOT NULL,
            purchased_at  DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS transactions (
            id            INT AUTO_INCREMENT PRIMARY KEY,
            user_id       BIGINT NOT NULL,
            amount        BIGINT NOT NULL,
            type          ENUM('charge','purchase','refund') NOT NULL,
            status        ENUM('pending','success','failed') DEFAULT 'pending',
            authority     VARCHAR(100),
            ref_id        VARCHAR(100),
            description   TEXT,
            created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS settings (
            key_name      VARCHAR(100) PRIMARY KEY,
            value         TEXT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";

        foreach (explode(';', $sql) as $q) {
            $q = trim($q);
            if ($q) $this->pdo->exec($q);
        }

        // Default settings
        $defaults = [
            'zarinpal_merchant'   => '',
            'zarinpal_sandbox'    => '0',
            'zarinpal_active'     => '0',
            'shop_name'           => 'فروشگاه فایل',
            'shop_description'    => 'خرید و فروش فایل‌های دیجیتال',
            'bot_active'          => '1',
        ];
        foreach ($defaults as $k => $v) {
            $this->pdo->prepare("INSERT IGNORE INTO settings (key_name, value) VALUES (?, ?)")
                ->execute([$k, $v]);
        }
    }

    // ===== USER METHODS =====
    public function getUser(int $userId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    }

    public function upsertUser(int $userId, string $firstName, ?string $lastName, ?string $username): void
    {
        $this->pdo->prepare("
            INSERT INTO users (id, first_name, last_name, username)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                first_name = VALUES(first_name),
                last_name  = VALUES(last_name),
                username   = VALUES(username),
                last_seen  = CURRENT_TIMESTAMP
        ")->execute([$userId, $firstName, $lastName, $username]);
    }

    public function setState(int $userId, int $state, $data = null): void
    {
        $this->pdo->prepare("UPDATE users SET state = ?, state_data = ? WHERE id = ?")
            ->execute([$state, $data ? json_encode($data) : null, $userId]);
    }

    public function getState(int $userId): array
    {
        $stmt = $this->pdo->prepare("SELECT state, state_data FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return [
            'state' => (int)($row['state'] ?? 0),
            'data'  => $row['state_data'] ? json_decode($row['state_data'], true) : null,
        ];
    }

    public function getUserWallet(int $userId): int
    {
        $stmt = $this->pdo->prepare("SELECT wallet FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        return (int)($stmt->fetchColumn() ?? 0);
    }

    public function updateWallet(int $userId, int $amount): void
    {
        $this->pdo->prepare("UPDATE users SET wallet = wallet + ? WHERE id = ?")
            ->execute([$amount, $userId]);
    }

    public function getAllUsers(): array
    {
        return $this->pdo->query("SELECT * FROM users WHERE is_banned = 0 ORDER BY joined_at DESC")->fetchAll();
    }

    public function getUserCount(): int
    {
        return (int)$this->pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    }

    public function banUser(int $userId, int $ban = 1): void
    {
        $this->pdo->prepare("UPDATE users SET is_banned = ? WHERE id = ?")
            ->execute([$ban, $userId]);
    }

    public function searchUser(string $query): array
    {
        $q = "%$query%";
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id LIKE ? OR username LIKE ? OR first_name LIKE ? LIMIT 10");
        $stmt->execute([$q, $q, $q]);
        return $stmt->fetchAll();
    }

    // ===== FILE METHODS =====
    public function addFile(array $data): int
    {
        $this->pdo->prepare("
            INSERT INTO files (name, description, price, file_id, file_type, file_size, seller_id)
            VALUES (:name, :desc, :price, :file_id, :file_type, :file_size, :seller_id)
        ")->execute([
            ':name'      => $data['name'],
            ':desc'      => $data['description'] ?? '',
            ':price'     => $data['price'],
            ':file_id'   => $data['file_id'],
            ':file_type' => $data['file_type'] ?? 'document',
            ':file_size' => $data['file_size'] ?? 0,
            ':seller_id' => $data['seller_id'],
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function getFile(int $fileId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM files WHERE id = ?");
        $stmt->execute([$fileId]);
        return $stmt->fetch() ?: null;
    }

    public function getAllFiles(bool $activeOnly = true): array
    {
        $where = $activeOnly ? "WHERE is_active = 1" : "";
        return $this->pdo->query("SELECT * FROM files $where ORDER BY created_at DESC")->fetchAll();
    }

    public function updateFile(int $fileId, array $data): void
    {
        $sets = [];
        $vals = [];
        foreach ($data as $k => $v) {
            $sets[] = "$k = ?";
            $vals[] = $v;
        }
        $vals[] = $fileId;
        $this->pdo->prepare("UPDATE files SET " . implode(', ', $sets) . " WHERE id = ?")
            ->execute($vals);
    }

    public function deleteFile(int $fileId): void
    {
        $this->pdo->prepare("DELETE FROM files WHERE id = ?")->execute([$fileId]);
    }

    public function incrementDownloads(int $fileId): void
    {
        $this->pdo->prepare("UPDATE files SET downloads = downloads + 1 WHERE id = ?")
            ->execute([$fileId]);
    }

    public function getFileCount(): int
    {
        return (int)$this->pdo->query("SELECT COUNT(*) FROM files WHERE is_active = 1")->fetchColumn();
    }

    // ===== PURCHASE METHODS =====
    public function hasPurchased(int $userId, int $fileId): bool
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM purchases WHERE user_id = ? AND file_id = ?");
        $stmt->execute([$userId, $fileId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function addPurchase(int $userId, int $fileId, int $amount): void
    {
        $this->pdo->prepare("INSERT INTO purchases (user_id, file_id, amount) VALUES (?, ?, ?)")
            ->execute([$userId, $fileId, $amount]);
    }

    public function getUserPurchases(int $userId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT p.*, f.name, f.description, f.price, f.file_id, f.file_type
            FROM purchases p
            JOIN files f ON f.id = p.file_id
            WHERE p.user_id = ?
            ORDER BY p.purchased_at DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function getTotalRevenue(): int
    {
        return (int)$this->pdo->query("SELECT COALESCE(SUM(amount), 0) FROM purchases")->fetchColumn();
    }

    public function getPurchaseCount(): int
    {
        return (int)$this->pdo->query("SELECT COUNT(*) FROM purchases")->fetchColumn();
    }

    // ===== TRANSACTION METHODS =====
    public function createTransaction(int $userId, int $amount, string $type, string $desc = ''): int
    {
        $this->pdo->prepare("
            INSERT INTO transactions (user_id, amount, type, description)
            VALUES (?, ?, ?, ?)
        ")->execute([$userId, $amount, $type, $desc]);
        return (int)$this->pdo->lastInsertId();
    }

    public function setAuthority(int $txId, string $authority): void
    {
        $this->pdo->prepare("UPDATE transactions SET authority = ? WHERE id = ?")
            ->execute([$authority, $txId]);
    }

    public function getTransactionByAuthority(string $authority): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM transactions WHERE authority = ? AND status = 'pending'");
        $stmt->execute([$authority]);
        return $stmt->fetch() ?: null;
    }

    public function updateTransaction(int $txId, string $status, string $refId = ''): void
    {
        $this->pdo->prepare("UPDATE transactions SET status = ?, ref_id = ? WHERE id = ?")
            ->execute([$status, $refId, $txId]);
    }

    public function getUserTransactions(int $userId, int $limit = 10): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM transactions WHERE user_id = ?
            ORDER BY created_at DESC LIMIT ?
        ");
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll();
    }

    public function getRecentTransactions(int $limit = 20): array
    {
        $stmt = $this->pdo->prepare("
            SELECT t.*, u.first_name, u.username
            FROM transactions t
            JOIN users u ON u.id = t.user_id
            ORDER BY t.created_at DESC LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    // ===== SETTINGS METHODS =====
    public function getSetting(string $key, string $default = ''): string
    {
        $stmt = $this->pdo->prepare("SELECT value FROM settings WHERE key_name = ?");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return $val !== false ? $val : $default;
    }

    public function setSetting(string $key, string $value): void
    {
        $this->pdo->prepare("INSERT INTO settings (key_name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = ?")
            ->execute([$key, $value, $value]);
    }

    public function getAllSettings(): array
    {
        $rows = $this->pdo->query("SELECT key_name, value FROM settings")->fetchAll();
        $out = [];
        foreach ($rows as $r) $out[$r['key_name']] = $r['value'];
        return $out;
    }
}
