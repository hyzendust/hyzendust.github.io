<?php
/**
 * auth.php — Login & Sign-Up backend for freedoms4
 *
 * Place this file at:  /var/www/freedoms4/api/auth.php
 */

// ── Config ──────────────────────────────────────────────────────────────────
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '5432');
define('DB_NAME', 'freedoms4');
define('DB_USER', 'freedoms4_user');
define('DB_PASS', 'CHANGE_THIS_PASSWORD');   // ← change before deploying

define('SESSION_NAME',     'f4_session');
define('SESSION_SECURE',   true);
define('SESSION_SAMESITE', 'None');           // cross-origin cookies require None

// ── CORS ─────────────────────────────────────────────────────────────────────
$origin          = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed_origins = ['https://freedoms4.org', 'https://www.freedoms4.org'];

if ($origin && !in_array($origin, $allowed_origins, true)) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Forbidden.']);
    exit;
}

if ($origin) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Allow-Credentials: true');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── Helpers ──────────────────────────────────────────────────────────────────
function json_out(array $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function start_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => SESSION_SECURE,
            'httponly' => true,
            'samesite' => SESSION_SAMESITE,
        ]);
        session_start();
    }
}

function db_connect(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', DB_HOST, DB_PORT, DB_NAME);

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        error_log('DB connection failed: ' . $e->getMessage());
        json_out(['success' => false, 'message' => 'Database unavailable. Please try again later.'], 503);
    }

    return $pdo;
}

// ── Only accept POST ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['success' => false, 'message' => 'Method not allowed.'], 405);
}

// ── Parse JSON body ───────────────────────────────────────────────────────────
$body = json_decode(file_get_contents('php://input'), true);

if (!is_array($body)) {
    json_out(['success' => false, 'message' => 'Invalid request body.'], 400);
}

$action = $body['action'] ?? '';

// ── Rate limiting (per-IP via session) ────────────────────────────────────────
start_session();
$now    = time();
$rl_key = 'rl_' . hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
$rl     = $_SESSION[$rl_key] ?? ['count' => 0, 'window_start' => $now];

if ($now - $rl['window_start'] > 900) {
    $rl = ['count' => 0, 'window_start' => $now];
}

$rl['count']++;
$_SESSION[$rl_key] = $rl;

if ($rl['count'] > 20) {
    json_out(['success' => false, 'message' => 'Too many requests. Please wait a few minutes.'], 429);
}

// ════════════════════════════════════════════════════════════════════════════
// ACTION: login
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'login') {
    $username = trim($body['username'] ?? '');
    $password = $body['password'] ?? '';

    if ($username === '' || $password === '') {
        json_out(['success' => false, 'message' => 'Username and password are required.']);
    }

    $pdo  = db_connect();
    $stmt = $pdo->prepare('SELECT id, username, password_hash FROM users WHERE username = :u LIMIT 1');
    $stmt->execute([':u' => $username]);
    $user = $stmt->fetch();

    $hash = $user['password_hash'] ?? '$2y$12$invalidhashpadding000000000000000000000000000000000000000';
    if (!$user || !password_verify($password, $hash)) {
        json_out(['success' => false, 'message' => 'Invalid username or password.']);
    }

    session_regenerate_id(true);
    $_SESSION['user_id']  = $user['id'];
    $_SESSION['username'] = $user['username'];

    json_out(['success' => true, 'redirect' => '/']);
}

// ════════════════════════════════════════════════════════════════════════════
// ACTION: signup
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'signup') {
    $username = trim($body['username'] ?? '');
    $email    = trim($body['email']    ?? '');
    $password = $body['password']      ?? '';

    if ($username === '') {
        json_out(['success' => false, 'message' => 'Username is required.']);
    }
    if (!preg_match('/^[a-zA-Z0-9_\-]{3,32}$/', $username)) {
        json_out(['success' => false, 'message' => 'Username must be 3–32 characters: letters, numbers, _ or -.']);
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_out(['success' => false, 'message' => 'A valid email address is required.']);
    }
    if (strlen($password) < 8) {
        json_out(['success' => false, 'message' => 'Password must be at least 8 characters.']);
    }

    $pdo  = db_connect();
    $stmt = $pdo->prepare('SELECT 1 FROM users WHERE username = :u OR email = :e LIMIT 1');
    $stmt->execute([':u' => $username, ':e' => $email]);

    if ($stmt->fetch()) {
        json_out(['success' => false, 'message' => 'Username or email is already taken.']);
    }

    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $stmt = $pdo->prepare(
        'INSERT INTO users (username, email, password_hash, created_at) VALUES (:u, :e, :h, NOW())'
    );
    $stmt->execute([':u' => $username, ':e' => $email, ':h' => $hash]);

    json_out(['success' => true]);
}

// ════════════════════════════════════════════════════════════════════════════
// ACTION: logout
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'logout') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    json_out(['success' => true, 'redirect' => '/']);
}

json_out(['success' => false, 'message' => 'Unknown action.'], 400);
