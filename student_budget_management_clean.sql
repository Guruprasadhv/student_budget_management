-- Cleaned SQL for importing into an existing database
-- Notes:
-- 1) This file does NOT drop or create the database. Import into the database
--    (for example: if0_41968547_student_budget) using phpMyAdmin or mysql CLI.
-- 2) The final ALTER TABLE has been split into separate statements to avoid
--    parser issues on some phpMyAdmin installations.

-- Create users table with enhanced fields
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    theme VARCHAR(50) DEFAULT 'light',
    currency VARCHAR(10) DEFAULT 'USD',
    avatar VARCHAR(255) DEFAULT 'default.png',
    budget_goals TEXT,
    two_factor_enabled BOOLEAN DEFAULT FALSE,
    data_sharing BOOLEAN DEFAULT FALSE,
    email_notifications BOOLEAN DEFAULT 1,
    push_notifications BOOLEAN DEFAULT 1,
    low_balance_alert BOOLEAN DEFAULT 1,
    large_expense_alert BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create transactions table with improved structure
CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    type ENUM('income', 'expense') NOT NULL,
    category VARCHAR(100) NOT NULL,
    description TEXT,
    transaction_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX (transaction_date),
    INDEX (category)
);

-- Create settings table with additional preferences
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    preferred_currency VARCHAR(10) DEFAULT 'INR',
    monthly_budget DECIMAL(10,2) DEFAULT 0.00,
    notifications_enabled BOOLEAN DEFAULT TRUE,
    language VARCHAR(10) DEFAULT 'en',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create categories table
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_category (user_id, name)
);

-- Insert sample admin user with hashed password
INSERT INTO users (name, email, password, two_factor_enabled) VALUES
('Admin', 'admin@example.com', '$2y$10$8JzbEw0Y9lODnb2qaPl6v.LNsXbFjZx83Io.R7v6hph1e1QY7i3CW', TRUE);

-- Insert 10 sample users with hashed passwords
INSERT INTO users (name, email, password, theme, data_sharing) VALUES
('Amit Sharma', 'amit@example.com', '$2y$10$8JzbEw0Y9lODnb2qaPl6v.LNsXbFjZx83Io.R7v6hph1e1QY7i3CW', 'dark', TRUE),
('Priya Verma', 'priya@example.com', '$2y$10$Dly0JSPfiy7Ssq79wdZL6OLibMo2tJZIfMRoZKny7w48s7Aq0fQAO', 'light', FALSE),
('Ravi Kumar', 'ravi@example.com', '$2y$10$5FcKhGcgUrU3K5AZTnI9eOrAW1AIImNPMymZsZ8AdQ3KCCrJzZdWe', 'dark', TRUE),
('Sneha Rao', 'sneha@example.com', '$2y$10$3zJTDuC1cdQMnzMkC5ZPf.zSTY8xydUBephBJUE/qZ2CZn9TnLemG', 'light', FALSE),
('Manish Yadav', 'manish@example.com', '$2y$10$CrgFwMjyZW8YIGkRIWvmSO2ENY.lTxZqGS9CZwqEfD7SzqZ0Cv6ce', 'dark', TRUE),
('Divya Joshi', 'divya@example.com', '$2y$10$WTxQm1EbXzY8MRO4sKx7nOvdrhTxNZvS.CbAWMDG8NxsMcoJ2aHDy', 'light', FALSE),
('Rahul Mehta', 'rahul@example.com', '$2y$10$GnNa8BjysZG6pA/eiYbF1eR9XUmK7L4EwCFMC1jNF8zPl4klblUKS', 'dark', TRUE),
('Anjali Kapoor', 'anjali@example.com', '$2y$10$9hL2RSP1g8ly/FsA7UD0xOAHbJXbDGKJz.yqIffnGZT7QeJipU6GK', 'light', FALSE),
('Karan Singh', 'karan@example.com', '$2y$10$wEQTO4vCSwDkT9E4dAr5q.nAPKZpoB7DCfCISpWThuwlp4uYrQ7c6', 'dark', TRUE),
('Neha Reddy', 'neha@example.com', '$2y$10$xU1wTx7yWxAw7pkJSl43duJjZ8RhENb9vFiqfgk9l5/dC7DK6xZ3O', 'light', FALSE);

-- Insert settings for all users
INSERT INTO settings (user_id, preferred_currency, monthly_budget) VALUES
(1, 'INR', 10000.00),
(2, 'INR', 8000.00),
(3, 'USD', 500.00),
(4, 'INR', 7500.00),
(5, 'EUR', 400.00),
(6, 'INR', 9000.00),
(7, 'INR', 8500.00),
(8, 'GBP', 300.00),
(9, 'INR', 7000.00),
(10, 'INR', 9500.00);

-- Insert sample transactions (some duplicates removed for brevity)
INSERT INTO transactions (user_id, type, category, description, amount, transaction_date) VALUES
(1, 'income', 'Scholarship', 'Received scholarship', 5000.00, '2025-07-01'),
(1, 'income', 'Part-Time Job', 'Library assistant', 2000.00, '2025-07-05'),
(1, 'expense', 'Food', 'Canteen lunch', 120.00, '2025-07-02'),
(1, 'expense', 'Books', 'Data Science book', 450.00, '2025-07-03'),
(1, 'expense', 'Transport', 'Bus pass', 300.00, '2025-07-06');

-- Insert more realistic categories for user_id=1
INSERT INTO categories (user_id, name) VALUES
(1, 'Food'),
(1, 'Transport'),
(1, 'Books'),
(1, 'Shopping'),
(1, 'Utilities');

-- If you need to add extra columns to settings table, run the following separate ALTER statements:
ALTER TABLE settings ADD COLUMN IF NOT EXISTS monthly_budget_goal DECIMAL(10,2) DEFAULT 0.00;
ALTER TABLE settings ADD COLUMN IF NOT EXISTS monthly_savings_goal DECIMAL(10,2) DEFAULT 0.00;
ALTER TABLE settings ADD COLUMN IF NOT EXISTS weekly_spending_limit DECIMAL(10,2) DEFAULT 0.00;
ALTER TABLE settings ADD COLUMN IF NOT EXISTS budget_reset_day VARCHAR(20) DEFAULT '1st of the month';
