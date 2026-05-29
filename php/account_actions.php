<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/account_helpers.php';

if (!isset($_SESSION['user_id'])) {
    account_json_response(false, 'Not authenticated.');
}

$user_id = (int)$_SESSION['user_id'];
ensure_account_tables($conn);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        account_json_response(false, 'Invalid security token. Please refresh the page.');
    }
}

switch ($action) {
    case 'change_password':
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (strlen($new) < 8) {
            account_json_response(false, 'New password must be at least 8 characters.');
        }
        if ($new !== $confirm) {
            account_json_response(false, 'New passwords do not match.');
        }

        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row || !password_verify($current, $row['password'])) {
            account_json_response(false, 'Current password is incorrect.');
        }

        $hash = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ?, password_updated_at = NOW(), updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("si", $hash, $user_id);
        $ok = $stmt->execute();
        $stmt->close();

        account_json_response($ok, $ok ? 'Password updated successfully.' : 'Failed to update password.');
        break;

    case 'toggle_2fa':
        $enable = ($_POST['enable'] ?? '') === '1';
        $pin = preg_replace('/\D/', '', $_POST['pin'] ?? '');
        $current_password = $_POST['current_password'] ?? '';

        $stmt = $conn->prepare("SELECT password, two_factor_enabled FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user || !password_verify($current_password, $user['password'])) {
            account_json_response(false, 'Password is incorrect.');
        }

        if ($enable) {
            if (!preg_match('/^\d{6}$/', $pin)) {
                account_json_response(false, 'Enter a valid 6-digit security PIN.');
            }
            $pinHash = password_hash($pin, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET two_factor_enabled = 1, two_factor_pin_hash = ? WHERE id = ?");
            $stmt->bind_param("si", $pinHash, $user_id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET two_factor_enabled = 0, two_factor_pin_hash = NULL WHERE id = ?");
            $stmt->bind_param("i", $user_id);
        }

        $ok = $stmt->execute();
        $stmt->close();
        account_json_response($ok, $ok ? ($enable ? 'Two-factor authentication enabled.' : 'Two-factor authentication disabled.') : 'Could not update 2FA settings.', [
            'enabled' => $enable
        ]);
        break;

    case 'list_sessions':
        $currentSession = session_id();
        $stmt = $conn->prepare("SELECT id, session_id, device_name, location_label, last_active FROM user_sessions WHERE user_id = ? ORDER BY last_active DESC");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $sessions = [];
        while ($row = $result->fetch_assoc()) {
            $sessions[] = [
                'id' => (int)$row['id'],
                'session_id' => $row['session_id'],
                'device_name' => $row['device_name'],
                'location_label' => $row['location_label'],
                'last_active' => $row['last_active'],
                'is_current' => $row['session_id'] === $currentSession
            ];
        }
        $stmt->close();
        account_json_response(true, 'Sessions loaded.', ['sessions' => $sessions]);
        break;

    case 'revoke_session':
        $target = $_POST['session_id'] ?? '';
        if ($target === '') {
            account_json_response(false, 'Session not specified.');
        }

        $stmt = $conn->prepare("DELETE FROM user_sessions WHERE user_id = ? AND session_id = ?");
        $stmt->bind_param("is", $user_id, $target);
        $stmt->execute();
        $stmt->close();

        $isCurrent = ($target === session_id());
        account_json_response(true, 'Device logged out successfully.', ['is_current' => $isCurrent]);
        break;

    case 'revoke_all_sessions':
        $current = session_id();
        $stmt = $conn->prepare("DELETE FROM user_sessions WHERE user_id = ? AND session_id != ?");
        $stmt->bind_param("is", $user_id, $current);
        $stmt->execute();
        $stmt->close();
        account_json_response(true, 'All other devices have been logged out.');
        break;

    case 'list_connected':
        $stmt = $conn->prepare("SELECT id, provider, account_name, account_identifier, last_four, connected_at FROM connected_accounts WHERE user_id = ? AND is_active = 1 ORDER BY connected_at DESC");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $accounts = [];
        while ($row = $result->fetch_assoc()) {
            $accounts[] = [
                'id' => (int)$row['id'],
                'provider' => $row['provider'],
                'account_name' => $row['account_name'],
                'account_identifier' => $row['account_identifier'],
                'last_four' => $row['last_four'],
                'connected_at' => $row['connected_at']
            ];
        }
        $stmt->close();
        account_json_response(true, 'Connected accounts loaded.', ['accounts' => $accounts]);
        break;

    case 'connect_account':
        $provider = $_POST['provider'] ?? '';
        $allowed = ['bank', 'paypal', 'card'];
        if (!in_array($provider, $allowed, true)) {
            account_json_response(false, 'Invalid account type.');
        }

        $account_name = trim($_POST['account_name'] ?? '');
        $identifier = trim($_POST['account_identifier'] ?? '');
        $last_four = preg_replace('/\D/', '', $_POST['last_four'] ?? '');

        if ($account_name === '') {
            account_json_response(false, 'Account name is required.');
        }
        if ($last_four !== '' && strlen($last_four) !== 4) {
            account_json_response(false, 'Last four digits must be exactly 4 numbers.');
        }

        $stmt = $conn->prepare("INSERT INTO connected_accounts (user_id, provider, account_name, account_identifier, last_four) VALUES (?, ?, ?, ?, ?)");
        $last_fourVal = $last_four !== '' ? $last_four : null;
        $identifierVal = $identifier !== '' ? $identifier : null;
        $stmt->bind_param("issss", $user_id, $provider, $account_name, $identifierVal, $last_fourVal);
        $ok = $stmt->execute();
        $newId = $stmt->insert_id;
        $stmt->close();

        account_json_response($ok, $ok ? 'Account connected successfully.' : 'Failed to connect account.', [
            'id' => $newId
        ]);
        break;

    case 'disconnect_account':
        $accountId = (int)($_POST['account_id'] ?? 0);
        $stmt = $conn->prepare("UPDATE connected_accounts SET is_active = 0 WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $accountId, $user_id);
        $ok = $stmt->execute() && $stmt->affected_rows > 0;
        $stmt->close();
        account_json_response($ok, $ok ? 'Account disconnected.' : 'Account not found.');
        break;

    default:
        account_json_response(false, 'Unknown action.');
}
