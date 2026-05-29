# Student Budget Management System

A web-based application to help students track their income, expenses, and manage their budget efficiently. The system provides intuitive forms, visual reports, and a user-friendly interface for personal finance management.

---

## Table of Contents
- [Features](#features)
- [Requirements](#requirements)
- [Installation & Setup](#installation--setup)
- [File Structure](#file-structure)
- [Libraries Used](#libraries-used)
- [Screenshots](#screenshots)
- [Troubleshooting](#troubleshooting)
- [License](#license)

---

## Features
- **User Authentication**: Secure user registration, login, and robust password reset functionality.
- **Transaction Tracking**: Add, view, and categorize income and expenses effortlessly.
- **Visual Analytics**: Dynamic, interactive charts and graphs (income vs expenses, category breakdown, and monthly trends) powered by Chart.js.
- **Comprehensive Account Management**: Manage personal preferences, security settings, active sessions, and profile customizations.
- **Consolidated 14-Language Localization**: Full localized interface support for 14 regional and international languages (English, Hindi, Kannada, Telugu, Tamil, Malayalam, Marathi, Gujarati, Bengali, Spanish, French, German, Arabic, and Thai) housed in a single, high-reliability monolithic dictionary.
- **Real-Time Phonetic Transliteration**: Proper nouns and custom registered user names dynamically transliterate to native scripts (such as Kannada, Hindi, and Telugu) in real-time using Google Input Tools integration, with a built-in session-based cache for instantaneous subsequent load speeds and offline-resilience.
- **Instant Language Switching**: Dynamic page-wide translation updates automatically trigger the moment a language select option is chosen, without requiring manual form submissions.

---

## Requirements
- PHP 7.x or higher
- MySQL (MariaDB)
- XAMPP (recommended for local development)
- Web browser

---

## Installation & Setup

### 1. Clone or Download the Project
- Place the project folder (`student_budget_management`) in your XAMPP `htdocs` directory:
  - Example: `C:/xampp/htdocs/student_budget_management`

### 2. Start XAMPP Services
- Open XAMPP Control Panel
- Start **Apache** and **MySQL**

### 3. Import the Database
- Open [phpMyAdmin](http://localhost/phpmyadmin)
- Create a new database named: `student_budget_management`
- Click the database, then go to the **Import** tab
- Select the file `student_budget_management.sql` from the project folder and import it

### 4. Configure Database Connection (if needed)
- By default, the database connection settings are in `php/db.php`
- Ensure the username, password, and database name match your local setup (default for XAMPP is user: `root`, password: empty)

### 5. Access the Application
- In your browser, go to: [http://localhost/student_budget_management/](http://localhost/student_budget_management/)
- Register a new account and start using the app!

---

## File Structure
- `index.php` - Login page
- `register.php` - User registration
- `dashboard.php` - Main dashboard after login
- `add_income.php` / `add_expense.php` - Add transactions
- `history.php` - View transaction history
- `report.php` - Visual reports and charts
- `account.php` - Account details
- `settings.php` - User settings
- `reset_password.php`, `forgot_password.php` - Password reset features
- `php/languages.php` - Unified, monolithic 14-language dictionary and phonetic translation engine. Handles dynamic language loading, session initialization, fallback mappings, and Google Transliterate API calls.
- `php/` - Backend PHP scripts (database, authentication, etc.)
- `assets/` - CSS, JS, and icon assets
- `screenshot/` - Project screenshots for documentation

---

## Libraries Used
- [Bootstrap](https://getbootstrap.com/) (UI styling)
- [Chart.js](https://www.chartjs.org/) (charts and graphs)
- [Bootstrap Icons](https://icons.getbootstrap.com/)

---

## Screenshots

Below are sample screenshots of the main pages in the Student Budget Management System:

> **Note:** Screenshot filenames use lowercase and hyphens for clarity (e.g., `add-income.png`).

### 1. Login Page
![Login Page](screenshot/login.png)
*The user login screen where registered users can access their accounts.*

### 2. Dashboard
![Dashboard](screenshot/dashboard.png)
*The main dashboard provides an overview of your financial status, including quick stats and navigation.*

### 3. Add Income
![Add Income](screenshot/add-income.png)
*Form for adding new income entries, specifying amount, source, and date.*

### 4. Add Expense
![Add Expense](screenshot/add-expense.png)
*Form for recording expenses, including category, amount, and date.*

### 5. History Page
![History](screenshot/history.png)
*View a detailed history of all your transactions.*

### 6. Reports Page
![Reports Page](screenshot/reports.png)
*Visual reports and charts summarizing your income, expenses, and spending trends.*

### 7. Settings Page
![Settings Page](screenshot/settings.png)
*User settings page for updating account information and preferences.*

---

## Troubleshooting
- **Database errors:** Ensure the database is imported and connection settings are correct in `php/db.php`.
- **Blank pages or errors:** Check XAMPP's Apache error log for details.
- **Port conflicts:** Make sure Apache and MySQL are running on their default ports (80/3306) or update your configuration accordingly.

## Using a remote (online) MySQL database

This project supports connecting to a remote MySQL host (for example, a cloud or shared hosting database). There are two recommended ways to provide credentials:

1. Environment variables provided by the host (recommended)
2. A local `.env` file in the project root (convenient for development)

The following environment variables are used by the application (see `php/db.php`):

- `DB_HOST` — host or IP of the MySQL server (or socket path)
- `DB_PORT` — TCP port (default 3306)
- `DB_USER` — database username
- `DB_PASS` — database password
- `DB_NAME` — database name

Quick steps to use a remote MySQL host:

- Copy `.env.example` to `.env` in the project root and fill in your remote credentials.
- Make sure `.env` is NOT committed (this repo already includes `.gitignore` to ignore it).
- If your hosting provider gives you environment variables (e.g., on platforms like Heroku, Render, or managed hosting), set those instead of using a `.env` file.
- Upload/import the `student_budget_management.sql` into your remote database (use phpMyAdmin, MySQL Workbench, or the host's import tools).

Tips & troubleshooting when using remote MySQL:

- Ensure the remote MySQL server allows connections from your web host (some hosts block external IPs).
- If using a managed PHP host, use the credentials provided by their control panel and set them as environment variables when the host supports it.
- Check for common errors in your web server logs if a connection fails.


---

## License
This project is for educational purposes. 