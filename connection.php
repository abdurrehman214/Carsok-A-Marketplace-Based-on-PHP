<?php
// ============================================================
//  CarSoko Pakistan — connection.php
// ============================================================

// ============================================================
// DATABASE CREDENTIALS — InfinityFree
// ============================================================
define('DB_HOST',   '');
define('DB_USER',   '');
define('DB_PASS',   '');
define('DB_NAME',   '');
define('APP_ENV',   'production');
define('APP_DEBUG', true);
define('BASE_URL',  'https://CARSOKO.page.gd');

// ============================================================
// APP CONSTANTS
// ============================================================
define('UPLOAD_PATH',      __DIR__ . '/uploads/');
define('UPLOAD_URL',       BASE_URL . '/uploads/');
define('MAX_IMAGE_SIZE',   5 * 1024 * 1024);
define('MAX_IMAGES',       40);
define('THUMB_WIDTH',      400);
define('THUMB_HEIGHT',     300);
define('SESSION_LIFETIME', 7 * 24 * 3600);
define('CSRF_TOKEN_NAME',  '_csrf');
define('ADMIN_PASSWORD',   'AR@12345');

// ============================================================
// ERROR HANDLING
// ============================================================
error_reporting(0);
ini_set('display_errors', '0');
ini_set('log_errors',     '1');
ini_set('error_log',      __DIR__ . '/logs/error.log');

// ============================================================
// SESSION
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_strict_mode',  '1');
    ini_set('session.cookie_samesite', 'None');
    ini_set('session.cookie_secure',   '1');
    session_start();
}

// ============================================================
// DATABASE CLASS
// ============================================================
class DB {
    private static ?PDO $pdo       = null;
    private static bool $attempted = false;

    public static function conn(): ?PDO {
        if (self::$attempted) return self::$pdo;
        self::$attempted = true;
        try {
            self::$pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER, DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::ATTR_TIMEOUT            => 5,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
                ]
            );
            self::$pdo->exec("SET time_zone='+05:00'");
            // Auto-fix schema for user convenience
            self::autoFixSchema();
        } catch (PDOException $e) {
            self::$pdo = null;
            error_log('[CarSoko] DB connection failed: ' . $e->getMessage());
        }
        return self::$pdo;
    }

    public static function isConnected(): bool {
        return self::conn() !== null;
    }

    public static function select(string $sql, array $params = []): array {
        $pdo = self::conn();
        if (!$pdo) return [];
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll() ?: [];
        } catch (PDOException $e) {
            self::logErr($e, $sql);
            return [];
        }
    }

    public static function selectOne(string $sql, array $params = []): ?array {
        $rows = self::select($sql, $params);
        return $rows[0] ?? null;
    }

    public static function value(string $sql, array $params = []) {
        $pdo = self::conn();
        if (!$pdo) return null;
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $v = $stmt->fetchColumn();
            return ($v === false) ? null : $v;
        } catch (PDOException $e) {
            self::logErr($e, $sql);
            return null;
        }
    }

    /** INSERT/UPDATE/DELETE — returns affected rows */
    public static function execute(string $sql, array $params = []): int {
        $pdo = self::conn();
        if (!$pdo) return 0;
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            self::logErr($e, $sql);
            // Re-throw inside a transaction so the caller's catch block gets
            // the real MySQL error instead of a silent 0-return.
            if ($pdo->inTransaction()) throw $e;
            return 0;
        }
    }

    /** INSERT — returns new row ID (lastInsertId), 0 on failure */
    public static function insert(string $sql, array $params = []): int {
        $pdo = self::conn();
        if (!$pdo) return 0;
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return (int) $pdo->lastInsertId();
        } catch (PDOException $e) {
            self::logErr($e, $sql);
            // Re-throw inside a transaction so the real MySQL error surfaces.
            if ($pdo->inTransaction()) throw $e;
            return 0;
        }
    }

    /** Get the last inserted ID directly */
    public static function lastId(): int {
        $pdo = self::conn();
        if (!$pdo) return 0;
        return (int) $pdo->lastInsertId();
    }

    public static function find(string $table, int $id): ?array {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        return self::selectOne("SELECT * FROM `$table` WHERE id = ?", [$id]);
    }

    public static function exists(string $sql, array $params = []): bool {
        return self::value($sql, $params) !== null;
    }

    public static function beginTransaction(): void {
        $pdo = self::conn();
        if ($pdo && !$pdo->inTransaction()) $pdo->beginTransaction();
    }
    public static function commit(): void {
        $pdo = self::conn();
        if ($pdo && $pdo->inTransaction()) $pdo->commit();
    }
    public static function rollback(): void {
        $pdo = self::conn();
        if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    }

    private static function logErr(PDOException $e, string $sql = ''): void {
        error_log('[CarSoko DB] ' . $e->getMessage() . ' | SQL: ' . substr($sql, 0, 300));
    }

    private static function autoFixSchema(): void {
        static $done = false;
        if ($done) return;
        $done = true;
        try {
            // Fix settings
            // Fix settings table columns
            $sCols = self::$pdo->query("DESCRIBE settings")->fetchAll(PDO::FETCH_COLUMN);
            if (in_array('key', $sCols)) {
                self::$pdo->exec("ALTER TABLE settings CHANGE `key` setting_key varchar(255)");
            }
            if (in_array('value', $sCols)) {
                self::$pdo->exec("ALTER TABLE settings CHANGE `value` setting_value text");
            }
            
            $pk = self::$pdo->query("SHOW KEYS FROM settings WHERE Key_name = 'PRIMARY'")->fetch();
            if (!$pk) {
                // Ensure column exists before adding PK
                self::$pdo->exec("DELETE s1 FROM settings s1 INNER JOIN settings s2 WHERE s1.updated_at < s2.updated_at AND s1.setting_key = s2.setting_key");
                self::$pdo->exec("ALTER TABLE settings ADD PRIMARY KEY (setting_key)");
            }
            // Fix blog_posts
            $cols = self::$pdo->query("DESCRIBE blog_posts")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('tags', $cols)) {
                self::$pdo->exec("ALTER TABLE blog_posts ADD COLUMN tags varchar(255) DEFAULT NULL AFTER category");
            }
            if (!in_array('published_at', $cols)) {
                self::$pdo->exec("ALTER TABLE blog_posts ADD COLUMN published_at datetime DEFAULT NULL AFTER status");
                self::$pdo->exec("UPDATE blog_posts SET published_at = NOW() WHERE published_at IS NULL");
            }
            // Ensure all existing posts have a published_at date
            self::$pdo->exec("UPDATE blog_posts SET published_at = NOW() WHERE published_at IS NULL AND status = 'published'");
        } catch (Exception $e) {}
    }
}

// ============================================================
// AUTH CLASS
// ============================================================
class Auth {

    public static function user(): ?array {
        if (!isset($_SESSION['user_id'])) return null;
        if (!isset($_SESSION['user_data'])) {
            $_SESSION['user_data'] = DB::selectOne(
                "SELECT id, name, email, phone, role, profile_photo, status,
                        email_verified, is_verified_seller, city, business_name
                 FROM users WHERE id = ? AND status = 'active' LIMIT 1",
                [$_SESSION['user_id']]
            );
        }
        return $_SESSION['user_data'] ?: null;
    }

    public static function check(): bool {
        return isset($_SESSION['user_id']) && self::user() !== null;
    }

    public static function id(): int {
        return (int)($_SESSION['user_id'] ?? 0);
    }

    public static function is(string ...$roles): bool {
        $user = self::user();
        return $user !== null && in_array($user['role'], $roles, true);
    }

    public static function isAdmin(): bool     { return self::is('admin'); }
    public static function isModerator(): bool { return self::is('admin', 'moderator'); }
    public static function isDealer(): bool    { return self::is('dealer'); }
    public static function isSeller(): bool    { return self::is('dealer', 'private_seller'); }

    public static function login(array $user, bool $remember = false): void {
        session_regenerate_id(true);
        $_SESSION['user_id']      = $user['id'];
        $_SESSION['user_data']    = null;
        $_SESSION['logged_in_at'] = time();

        if ($remember) {
            try {
                $token = bin2hex(random_bytes(32));
                DB::execute(
                    "UPDATE users SET remember_token = ?, last_login = NOW(), last_login_ip = ? WHERE id = ?",
                    [password_hash($token, PASSWORD_BCRYPT), $_SERVER['REMOTE_ADDR'] ?? '', $user['id']]
                );
                setcookie('remember_token', $user['id'] . ':' . $token, [
                    'expires'  => time() + SESSION_LIFETIME,
                    'path'     => '/',
                    'httponly' => true,
                    'samesite' => 'None',
                    'secure'   => true,
                ]);
            } catch (Throwable $e) {
                error_log('[CarSoko] remember_token save failed: ' . $e->getMessage());
            }
        }
    }

    public static function logout(): void {
        setcookie('remember_token', '', time() - 3600, '/', '', true, true);
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
    }

    public static function checkRememberCookie(): void {
        try {
            if (self::check() || empty($_COOKIE['remember_token'])) return;
            $parts = explode(':', $_COOKIE['remember_token'], 2);
            if (count($parts) !== 2) return;
            [$userId, $token] = $parts;
            $userId = (int)$userId;
            if ($userId < 1 || empty(trim($token))) return;
            $user = DB::selectOne(
                "SELECT * FROM users WHERE id = ? AND status = 'active' AND remember_token IS NOT NULL LIMIT 1",
                [$userId]
            );
            if ($user && password_verify($token, $user['remember_token'])) {
                self::login($user, true);
            } else {
                setcookie('remember_token', '', time() - 3600, '/');
            }
        } catch (Throwable $e) {
            setcookie('remember_token', '', time() - 3600, '/');
            error_log('[CarSoko] checkRememberCookie failed: ' . $e->getMessage());
        }
    }

    public static function requireLogin(string $redirectTo = '/login.php'): void {
        if (!self::check()) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
            header('Location: ' . BASE_URL . $redirectTo);
            exit;
        }
    }

    public static function requireAdmin(): void {
        self::requireLogin();
        if (!self::isModerator()) {
            header('Location: ' . BASE_URL . '/403.php');
            exit;
        }
    }

    public static function hashPassword(string $plain): string {
        return password_hash($plain, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public static function verifyPassword(string $plain, string $hash): bool {
        return password_verify($plain, $hash);
    }
}

// ============================================================
// CSRF
// ============================================================
class CSRF {
    public static function token(): string {
        if (empty($_SESSION[CSRF_TOKEN_NAME])) {
            $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
        }
        return $_SESSION[CSRF_TOKEN_NAME];
    }

    public static function field(): string {
        return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . self::token() . '">';
    }

    public static function verify(): bool {
        $token = $_POST[CSRF_TOKEN_NAME] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        return !empty($token) && hash_equals($_SESSION[CSRF_TOKEN_NAME] ?? '', $token);
    }

    public static function check(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !self::verify()) {
            http_response_code(403);
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || !empty($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) {
                header('Content-Type: application/json');
                die(json_encode(['success' => false, 'message' => 'Invalid security token. Refresh and try again.']));
            }
            die('<p style="font-family:sans-serif;padding:40px">Invalid security token. <a href="javascript:history.back()">Go back</a> and try again.</p>');
        }
    }
}

// ============================================================
// RATE LIMITER
// ============================================================
class RateLimit {
    public static function check(string $action, int $maxAttempts = 5, int $windowSeconds = 300): bool {
        $key = 'rl_' . $action . '_' . ($_SERVER['REMOTE_ADDR'] ?? 'cli');
        $now = time();
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['count' => 0, 'window_start' => $now];
        }
        if ($now - $_SESSION[$key]['window_start'] > $windowSeconds) {
            $_SESSION[$key] = ['count' => 0, 'window_start' => $now];
        }
        $_SESSION[$key]['count']++;
        return $_SESSION[$key]['count'] <= $maxAttempts;
    }

    public static function remaining(string $action, int $maxAttempts = 5): int {
        $key  = 'rl_' . $action . '_' . ($_SERVER['REMOTE_ADDR'] ?? 'cli');
        $used = $_SESSION[$key]['count'] ?? 0;
        return max(0, $maxAttempts - $used);
    }
}

// ============================================================
// HELPERS
// ============================================================
function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function cleanInput($value): string {
    return trim(strip_tags((string)$value));
}

function normalizePhone(string $phone): ?string {
    $phone = preg_replace('/\D/', '', $phone);
    // Pakistan formats: 03xxxxxxxxx or 923xxxxxxxxx
    if (strlen($phone) === 10 && $phone[0] === '3')                $phone = '92' . $phone;
    if (strlen($phone) === 11 && $phone[0] === '0')               $phone = '92' . substr($phone, 1);
    if (strlen($phone) === 12 && substr($phone, 0, 2) === '92')   return '+' . $phone;
    return null;
}

function makeSlug(string $text, int $maxLength = 80): string {
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    return substr(trim($text, '-'), 0, $maxLength);
}

function generateCarSlug(string $make, string $model, int $year, string $city): string {
    $base = makeSlug("$make $model $year $city");
    $slug = $base;
    $i    = 1;
    while (DB::exists("SELECT 1 FROM cars WHERE slug = ?", [$slug])) {
        $slug = $base . '-' . $i++;
    }
    return $slug;
}

function formatPKR(float $amount, bool $compact = false): string {
    if ($compact) {
        if ($amount >= 10_000_000) return 'Rs. ' . rtrim(rtrim(number_format($amount / 10_000_000, 1), '0'), '.') . ' Cr';
        if ($amount >= 100_000)    return 'Rs. ' . rtrim(rtrim(number_format($amount / 100_000, 1), '0'), '.') . ' Lac';
    }
    return 'Rs. ' . number_format($amount, 0, '.', ',');
}

function formatMileage(int $km): string {
    return number_format($km) . ' km';
}

function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)     return 'Just now';
    if ($diff < 3600)   return floor($diff / 60)    . ' min ago';
    if ($diff < 86400)  return floor($diff / 3600)  . ' hr ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    return date('d M Y', strtotime($datetime));
}



function redirect(string $url, int $code = 302) {
    header("Location: $url", true, $code);
    exit;
}

function jsonResponse(bool $success, string $message = '', $data = null, int $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    $out = ['success' => $success, 'message' => $message];
    if ($data !== null) $out['data'] = $data;
    echo json_encode($out);
    exit;
}

function flash(string $key, string $message = ''): ?string {
    if ($message !== '') {
        $_SESSION['flash'][$key] = $message;
        return null;
    }
    $msg = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $msg;
}

function showFlash(string $key): void {
    $msg = flash($key);
    if (!$msg) return;
    $type = ($key === 'success') ? 'success' : 'error';
    echo '<div class="alert alert-' . $type . '">' . e($msg) . '</div>';
}

function isIPBanned(): bool {
    if (!DB::isConnected()) return false;
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if (!$ip) return false;
        return (bool) DB::value(
            "SELECT 1 FROM banned_ips WHERE ip = ? AND (expires_at IS NULL OR expires_at > NOW()) LIMIT 1",
            [$ip]
        );
    } catch (Throwable $e) {
        return false;
    }
}

function logActivity(string $action, ?int $targetId = null, ?string $targetType = null, ?string $details = null): void {
    if (!DB::isConnected()) return;
    try {
        DB::execute(
            "INSERT INTO activity_log (user_id, action, target_type, target_id, details, ip)
             VALUES (?, ?, ?, ?, ?, ?)",
            [Auth::id() ?: null, $action, $targetType, $targetId, $details, $_SERVER['REMOTE_ADDR'] ?? null]
        );
    } catch (Throwable $e) {
        // activity_log table missing — skip silently
    }
}

/** Get site settings from database with static cache */
function setting(string $key, $default = null) {
static $settings = null;
    if ($settings === null) {
        $settings = [];
        if (DB::isConnected()) {
            try {
                $rows = DB::select("SELECT setting_key, setting_value FROM settings");
                if ($rows) {
                    foreach ($rows as $r) {
                        $settings[$r['setting_key']] = $r['setting_value'];
                    }
                }
            } catch (Throwable $e) {
                error_log('[CarSoko] setting() error: ' . $e->getMessage());
            }
        }
    }
    return $settings[$key] ?? $default;
}

function getUnreadCount(): int {
    if (!Auth::check() || !DB::isConnected()) return 0;
    try {
        return (int) DB::value(
            "SELECT COUNT(*) FROM messages m
             JOIN conversations c ON c.id = m.conversation_id
             WHERE m.is_seen = 0
               AND m.sender_id != ?
               AND (c.buyer_id = ? OR c.seller_id = ?)",
            [Auth::id(), Auth::id(), Auth::id()]
        );
    } catch (Throwable $e) {
        return 0;
    }
}

function getNotificationCount(): int {
    if (!Auth::check() || !DB::isConnected()) return 0;
    try {
        return (int) DB::value(
            "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0 LIMIT 99",
            [Auth::id()]
        );
    } catch (Throwable $e) {
        return 0;
    }
}

function carImageUrl(?string $path, bool $thumb = false): string {
    if (empty($path)) return BASE_URL . '/assets/img/car-placeholder.jpg';
    if (filter_var($path, FILTER_VALIDATE_URL)) return $path;
    return UPLOAD_URL . ($thumb ? 'thumbs/' : '') . ltrim($path, '/');
}

// ============================================================
// BOOT
// ============================================================
try { Auth::checkRememberCookie(); } catch (Throwable $e) {
    error_log('[CarSoko] Boot checkRememberCookie: ' . $e->getMessage());
}

try {
    if (DB::isConnected() && isIPBanned()) {
        $self = $_SERVER['PHP_SELF'] ?? '';
        if (!str_contains($self, '/errors/') && !str_contains($self, 'login')) {
            http_response_code(403);
            die('<div style="font-family:sans-serif;text-align:center;padding:80px"><h1 style="color:#ef4444">Access Denied</h1><p>Your IP has been blocked. Contact <a href="mailto:support@carsoko.pk">support@carsoko.pk</a></p></div>');
        }
    }
} catch (Throwable $e) {
    error_log('[CarSoko] Boot isIPBanned: ' . $e->getMessage());
}
