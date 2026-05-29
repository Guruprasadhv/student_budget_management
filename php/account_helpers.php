<?php

function ensure_account_tables(mysqli $conn): void
{
    $conn->query("CREATE TABLE IF NOT EXISTS user_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        session_id VARCHAR(128) NOT NULL,
        device_name VARCHAR(120) NOT NULL DEFAULT 'Unknown Device',
        ip_address VARCHAR(45) DEFAULT NULL,
        location_label VARCHAR(120) DEFAULT 'Unknown',
        last_active TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_session (session_id),
        KEY idx_user_sessions_user (user_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS connected_accounts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        provider ENUM('bank', 'paypal', 'card') NOT NULL,
        account_name VARCHAR(120) NOT NULL,
        account_identifier VARCHAR(120) DEFAULT NULL,
        last_four VARCHAR(4) DEFAULT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        connected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_connected_user (user_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $col = $conn->query("SHOW COLUMNS FROM users LIKE 'two_factor_pin_hash'");
    if ($col && $col->num_rows === 0) {
        $conn->query("ALTER TABLE users ADD COLUMN two_factor_pin_hash VARCHAR(255) NULL DEFAULT NULL");
    }

    $col = $conn->query("SHOW COLUMNS FROM users LIKE 'password_updated_at'");
    if ($col && $col->num_rows === 0) {
        $conn->query("ALTER TABLE users ADD COLUMN password_updated_at TIMESTAMP NULL DEFAULT NULL");
    }
}

function parse_device_name(?string $userAgent): string
{
    $ua = $userAgent ?? '';
    $os = 'Unknown OS';
    if (stripos($ua, 'Windows') !== false) {
        $os = 'Windows';
    } elseif (stripos($ua, 'Mac') !== false) {
        $os = 'Mac';
    } elseif (stripos($ua, 'Android') !== false) {
        $os = 'Android';
    } elseif (stripos($ua, 'iPhone') !== false || stripos($ua, 'iPad') !== false) {
        $os = 'iOS';
    } elseif (stripos($ua, 'Linux') !== false) {
        $os = 'Linux';
    }

    $browser = 'Browser';
    if (stripos($ua, 'Edg/') !== false) {
        $browser = 'Edge';
    } elseif (stripos($ua, 'Chrome/') !== false) {
        $browser = 'Chrome';
    } elseif (stripos($ua, 'Firefox/') !== false) {
        $browser = 'Firefox';
    } elseif (stripos($ua, 'Safari/') !== false) {
        $browser = 'Safari';
    }

    return trim($os . ' ' . $browser);
}

function resolve_location_label(?string $ip): string
{
    if (!$ip || $ip === '127.0.0.1' || $ip === '::1') {
        return 'Localhost';
    }
    if (strpos($ip, '192.168.') === 0 || strpos($ip, '10.') === 0) {
        return 'Local Network';
    }
    return 'Unknown Location';
}

function register_user_session(mysqli $conn, int $userId, string $sessionId): void
{
    ensure_account_tables($conn);

    $device = parse_device_name($_SERVER['HTTP_USER_AGENT'] ?? '');
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $location = resolve_location_label($ip);

    $stmt = $conn->prepare("INSERT INTO user_sessions (user_id, session_id, device_name, ip_address, location_label, last_active)
        VALUES (?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE device_name = VALUES(device_name), ip_address = VALUES(ip_address),
        location_label = VALUES(location_label), last_active = NOW()");
    $stmt->bind_param("issss", $userId, $sessionId, $device, $ip, $location);
    $stmt->execute();
    $stmt->close();
}

function touch_user_session(mysqli $conn, int $userId, string $sessionId): void
{
    $stmt = $conn->prepare("UPDATE user_sessions SET last_active = NOW() WHERE user_id = ? AND session_id = ?");
    $stmt->bind_param("is", $userId, $sessionId);
    $stmt->execute();
    $stmt->close();
}

function require_no_pending_2fa(): void
{
    if (!empty($_SESSION['needs_2fa'])) {
        header('Location: verify_2fa.php');
        exit();
    }
}

function account_json_response(bool $success, string $message, array $extra = []): void
{
    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit();
}
