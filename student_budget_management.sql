-- Drop existing database if it exists
DROP DATABASE IF EXISTS student_budget_management;

-- Create new database
CREATE DATABASE student_budget_management;
USE student_budget_management;

-- Create users table with enhanced fields
CREATE TABLE users (
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
CREATE TABLE transactions (
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
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    preferred_currency VARCHAR(10) DEFAULT 'INR',
    monthly_budget DECIMAL(10,2) DEFAULT 0.00,
    notifications_enabled BOOLEAN DEFAULT TRUE,
    language VARCHAR(10) DEFAULT 'en',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create categories table
CREATE TABLE categories (
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

-- Insert 10 income and 10 expense records for user_id = 1 (July 2025)
INSERT INTO transactions (user_id, type, category, description, amount, transaction_date) VALUES
-- Income entries
(1, 'income', 'Scholarship', 'Received scholarship', 5000.00, '2025-07-01'),
(1, 'income', 'Part-Time Job', 'Library assistant', 2000.00, '2025-07-05'),
(1, 'income', 'Freelancing', 'Web dev gig', 3500.00, '2025-07-08'),
(1, 'income', 'Parent Support', 'Monthly allowance', 4000.00, '2025-07-10'),
(1, 'income', 'Gift', 'Birthday gift', 1500.00, '2025-07-12'),
(1, 'income', 'Stipend', 'Internship stipend', 3000.00, '2025-07-14'),
(1, 'income', 'Part-Time Job', 'Tutoring', 2500.00, '2025-07-16'),
(1, 'income', 'Bank Interest', 'Savings account', 500.00, '2025-07-18'),
(1, 'income', 'Scholarship', 'Merit scholarship', 6000.00, '2025-07-19'),
(1, 'income', 'Freelancing', 'Logo design', 1800.00, '2025-07-21'),
-- Expense entries
(1, 'expense', 'Food', 'Canteen lunch', 120.00, '2025-07-02'),
(1, 'expense', 'Books', 'Data Science book', 450.00, '2025-07-03'),
(1, 'expense', 'Transport', 'Bus pass', 300.00, '2025-07-06'),
(1, 'expense', 'Shopping', 'New shoes', 1800.00, '2025-07-09'),
(1, 'expense', 'Utilities', 'Phone recharge', 299.00, '2025-07-11'),
(1, 'expense', 'Entertainment', 'Movie night', 250.00, '2025-07-13'),
(1, 'expense', 'Medical', 'Doctor visit', 600.00, '2025-07-15'),
(1, 'expense', 'Food', 'Snacks', 90.00, '2025-07-17'),
(1, 'expense', 'Stationery', 'Pens & notebooks', 200.00, '2025-07-20'),
(1, 'expense', 'Subscription', 'Netflix', 499.00, '2025-07-22');

-- Insert sample transactions for other users (2-5)
INSERT INTO transactions (user_id, type, category, description, amount, transaction_date) VALUES
(2, 'income', 'Part-Time Job', 'Cafe work', 1800.00, '2025-07-03'),
(2, 'expense', 'Transport', 'Metro card', 400.00, '2025-07-04'),
(3, 'income', 'Freelancing', 'Graphic design', 4200.00, '2025-07-05'),
(3, 'expense', 'Books', 'Textbooks', 1200.00, '2025-07-06'),
(4, 'income', 'Parent Support', 'Monthly allowance', 3500.00, '2025-07-07'),
(4, 'expense', 'Food', 'Groceries', 800.00, '2025-07-08'),
(5, 'income', 'Scholarship', 'Sports quota', 4500.00, '2025-07-09'),
(5, 'expense', 'Equipment', 'Sports gear', 2200.00, '2025-07-10');

-- Insert more realistic categories for user_id=1
INSERT INTO categories (user_id, name) VALUES
(1, 'Food'),
(1, 'Transport'),
(1, 'Books'),
(1, 'Shopping'),
(1, 'Utilities'),
(1, 'Entertainment'),
(1, 'Medical'),
(1, 'Stationery'),
(1, 'Subscription'),
(1, 'Rent'),
(1, 'Tuition'),
(1, 'Part-Time Job'),
(1, 'Scholarship'),
(1, 'Internet'),
(1, 'Mobile'),
(1, 'Laundry'),
(1, 'Gifts'),
(1, 'Sports'),
(1, 'Miscellaneous');

-- Insert more realistic transactions for user_id=1 (July 2025)
INSERT INTO transactions (user_id, type, category, description, amount, transaction_date) VALUES
-- Income
(1, 'income', 'Scholarship', 'Merit-based scholarship', 12000.00, '2025-07-01'),
(1, 'income', 'Part-Time Job', 'Café barista salary', 3500.00, '2025-07-05'),
(1, 'income', 'Gifts', 'Birthday gift from uncle', 2000.00, '2025-07-10'),
(1, 'income', 'Freelancing', 'Tutoring high school math', 1800.00, '2025-07-15'),
(1, 'income', 'Parent Support', 'Monthly allowance', 5000.00, '2025-07-20'),
-- Expenses
(1, 'expense', 'Rent', 'July rent payment', 8000.00, '2025-07-02'),
(1, 'expense', 'Food', 'Groceries at supermarket', 1200.00, '2025-07-03'),
(1, 'expense', 'Food', 'Lunch at campus canteen', 150.00, '2025-07-04'),
(1, 'expense', 'Transport', 'Monthly metro pass', 600.00, '2025-07-05'),
(1, 'expense', 'Books', 'Textbook for Data Structures', 900.00, '2025-07-06'),
(1, 'expense', 'Utilities', 'Electricity bill', 700.00, '2025-07-07'),
(1, 'expense', 'Internet', 'WiFi bill', 500.00, '2025-07-08'),
(1, 'expense', 'Mobile', 'Mobile recharge', 299.00, '2025-07-09'),
(1, 'expense', 'Laundry', 'Laundry service', 200.00, '2025-07-10'),
(1, 'expense', 'Stationery', 'Notebooks and pens', 120.00, '2025-07-11'),
(1, 'expense', 'Entertainment', 'Movie night with friends', 350.00, '2025-07-12'),
(1, 'expense', 'Medical', 'Doctor visit', 600.00, '2025-07-13'),
(1, 'expense', 'Sports', 'Football club fee', 400.00, '2025-07-14'),
(1, 'expense', 'Subscription', 'Spotify monthly', 129.00, '2025-07-15'),
(1, 'expense', 'Shopping', 'New shoes', 1800.00, '2025-07-16'),
(1, 'expense', 'Miscellaneous', 'Snacks', 90.00, '2025-07-17');

ALTER TABLE settings
  ADD COLUMN monthly_budget_goal DECIMAL(10,2) DEFAULT 0.00,
  ADD COLUMN monthly_savings_goal DECIMAL(10,2) DEFAULT 0.00,
  ADD COLUMN weekly_spending_limit DECIMAL(10,2) DEFAULT 0.00,
  ADD COLUMN budget_reset_day VARCHAR(20) DEFAULT '1st of the month';

-- Account security & connected accounts
CREATE TABLE IF NOT EXISTS user_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_id VARCHAR(128) NOT NULL,
    device_name VARCHAR(120) NOT NULL DEFAULT 'Unknown Device',
    ip_address VARCHAR(45) DEFAULT NULL,
    location_label VARCHAR(120) DEFAULT 'Unknown',
    last_active TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_session (session_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS connected_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    provider ENUM('bank', 'paypal', 'card') NOT NULL,
    account_name VARCHAR(120) NOT NULL,
    account_identifier VARCHAR(120) DEFAULT NULL,
    last_four VARCHAR(4) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    connected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);