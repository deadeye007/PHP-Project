# Learning Platform

A PHP-based learning platform with courses, lessons, quizzes, and user progress tracking.

## Features
- User registration and login
- Course management
- Lesson viewing with progress tracking
- Quizzes
- Discussion forums (basic)
- Accessible design with light/dark mode support

## Setup with Docker
1. Install Docker and Docker Compose.
2. Copy `.env.example` to `.env` and update values.
3. Run `docker-compose up --build` in the project directory.
4. Open http://localhost:8080 in your browser.

## Manual Setup (without Docker)
1. Install PHP and MySQL (e.g., via Homebrew on macOS: `brew install php mysql`).
2. Start MySQL service: `brew services start mysql`.
3. Create the database: Run `mysql -u root -p < database/setup.sql`.
4. (Optional) Insert sample data: `mysql -u root -p learning_platform < database/sample_data.sql`.
5. Update database credentials in `includes/db.php` if needed.
6. Run the application: `php -S localhost:8000` in the project directory.
7. Open http://localhost:8000 in your browser.

## Security Notes
- Passwords are hashed using `password_hash()`.
- SQL queries use prepared statements to prevent injection.
- User input is sanitized.
- Session cookies are configured for security.
- Never commit `.env` files to version control.

## Technologies
- PHP
- MySQL
- HTML/CSS/JavaScript
- Bootstrap (for responsive design)
- Docker
