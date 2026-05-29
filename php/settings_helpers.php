<?php

function ensure_user_settings(mysqli $conn, int $userId): array
{
    $stmt = $conn->prepare("SELECT * FROM settings WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $settings = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$settings) {
        $stmt = $conn->prepare("INSERT INTO settings (user_id, preferred_currency, language) VALUES (?, 'INR', 'en')");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("SELECT * FROM settings WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $settings = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }

    ensure_settings_budget_columns($conn);

    return $settings ?: [
        'monthly_budget_goal' => 0,
        'monthly_savings_goal' => 0,
        'weekly_spending_limit' => 0,
        'budget_reset_day' => '1st of the month',
    ];
}

function ensure_settings_budget_columns(mysqli $conn): void
{
    $columns = [
        'monthly_budget_goal' => "DECIMAL(10,2) DEFAULT 0.00",
        'monthly_savings_goal' => "DECIMAL(10,2) DEFAULT 0.00",
        'weekly_spending_limit' => "DECIMAL(10,2) DEFAULT 0.00",
        'budget_reset_day' => "VARCHAR(20) DEFAULT '1st of the month'",
    ];

    foreach ($columns as $name => $definition) {
        $check = $conn->query("SHOW COLUMNS FROM settings LIKE '$name'");
        if ($check && $check->num_rows === 0) {
            $conn->query("ALTER TABLE settings ADD COLUMN $name $definition");
        }
    }
}

function settings_redirect(string $hash, string $message = '', array $errors = []): void
{
    if ($message !== '') {
        $_SESSION['settings_message'] = $message;
    }
    if (!empty($errors)) {
        $_SESSION['settings_errors'] = $errors;
    }
    header('Location: settings.php' . $hash);
    exit();
}
