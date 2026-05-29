<?php
session_start();
require_once __DIR__ . '/php/languages.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('contact_support') ?> - <?= __('student_budget_tracker') ?></title>
    <link href="assets/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/bootstrap-icons-1.13.1/bootstrap-icons.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #f8fafc;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        .support-card {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 24px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            max-width: 550px;
            width: 100%;
            padding: 3rem;
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .support-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.4);
            border-color: rgba(99, 102, 241, 0.3);
        }
        .support-icon-wrapper {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            box-shadow: 0 10px 20px rgba(79, 70, 229, 0.3);
        }
        .support-icon {
            font-size: 2.5rem;
            color: #ffffff;
        }
        .support-title {
            font-weight: 700;
            letter-spacing: -0.025em;
            margin-bottom: 0.5rem;
            background: linear-gradient(to right, #ffffff, #cbd5e1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .support-subtitle {
            color: #94a3b8;
            font-size: 0.95rem;
            margin-bottom: 2.5rem;
        }
        .contact-channel {
            background: rgba(15, 23, 42, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 16px;
            padding: 1.25rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            text-align: left;
            transition: all 0.2s ease;
            text-decoration: none;
            color: inherit;
        }
        .contact-channel:hover {
            background: rgba(99, 102, 241, 0.1);
            border-color: rgba(99, 102, 241, 0.3);
            transform: scale(1.02);
            color: #ffffff;
        }
        .channel-icon-box {
            width: 48px;
            height: 48px;
            background: rgba(99, 102, 241, 0.15);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1.25rem;
            color: #818cf8;
            font-size: 1.5rem;
        }
        .contact-channel:hover .channel-icon-box {
            background: #4f46e5;
            color: #ffffff;
        }
        .channel-info h5 {
            font-size: 1rem;
            font-weight: 600;
            margin: 0 0 0.25rem 0;
        }
        .channel-info p {
            font-size: 0.85rem;
            color: #94a3b8;
            margin: 0;
        }
        .contact-channel:hover .channel-info p {
            color: #cbd5e1;
        }
        .back-btn {
            background: transparent;
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 12px;
            color: #94a3b8;
            font-size: 0.9rem;
            font-weight: 500;
            padding: 0.75rem 1.5rem;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            margin-top: 2rem;
        }
        .back-btn:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(255, 255, 255, 0.3);
            color: #ffffff;
        }
    </style>
</head>
<body>
    <div class="container d-flex justify-content-center align-items-center">
        <div class="support-card">
            <div class="support-icon-wrapper">
                <i class="bi bi-chat-left-text support-icon"></i>
            </div>
            <h2 class="support-title"><?= __('contact_support') ?></h2>
            <p class="support-subtitle"><?= __('we_are_here_to_help') ?></p>
            
            <!-- Website Channel -->
            <a href="https://guruprasad-hv.netlify.app/" target="_blank" class="contact-channel">
                <div class="channel-icon-box">
                    <i class="bi bi-globe"></i>
                </div>
                <div class="channel-info">
                    <h5><?= __('visit_our_website') ?></h5>
                    <p>https://guruprasad-hv.netlify.app/</p>
                </div>
                <i class="bi bi-arrow-right-short ms-auto fs-4 text-muted"></i>
            </a>

            <!-- Email Channel -->
            <a href="mailto:guruprasadhv10@gmail.com?subject=Student Budget Tracker Support" class="contact-channel">
                <div class="channel-icon-box">
                    <i class="bi bi-envelope"></i>
                </div>
                <div class="channel-info">
                    <h5><?= __('email_support') ?></h5>
                    <p>guruprasadhv10@gmail.com</p>
                </div>
                <i class="bi bi-arrow-right-short ms-auto fs-4 text-muted"></i>
            </a>

            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="dashboard.php" class="back-btn">
                    <i class="bi bi-arrow-left me-2"></i> <?= __('back_to_dashboard') ?>
                </a>
            <?php else: ?>
                <a href="index.php" class="back-btn">
                    <i class="bi bi-arrow-left me-2"></i> <?= __('back_to_login') ?>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <script src="assets/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
