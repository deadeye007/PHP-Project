-- Sample data for Learning Platform

USE learning_platform;

-- Insert sample users
INSERT INTO users (username, email, password) VALUES
('admin', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'), -- password: password
('student1', 'student1@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('student2', 'student2@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Insert sample courses
INSERT INTO courses (title, description, instructor_id) VALUES
('Introduction to PHP', 'Learn the basics of PHP programming.', 1),
('Web Development Basics', 'Fundamentals of HTML, CSS, and JavaScript.', 1),
('Database Design', 'Understanding relational databases and SQL.', 1);

-- Insert sample lessons
INSERT INTO lessons (course_id, title, content, order_num) VALUES
(1, 'What is PHP?', 'PHP is a server-side scripting language...', 1),
(1, 'Variables and Data Types', 'In PHP, variables start with $...', 2),
(2, 'HTML Basics', 'HTML is the structure of web pages...', 1),
(2, 'CSS Styling', 'CSS controls the appearance...', 2),
(3, 'SQL Fundamentals', 'SQL is used to query databases...', 1);

-- Insert sample progress (for student1)
INSERT INTO user_progress (user_id, lesson_id, completed) VALUES
(2, 1, TRUE),
(2, 2, TRUE);