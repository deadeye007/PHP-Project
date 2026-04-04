-- Sample data for Learning Platform

USE learning_platform;

-- Insert sample users
INSERT INTO users (username, email, password, role, password_changed_at) VALUES
('admin', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', NOW()), -- password: password
('student1', 'student1@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', NOW()),
('student2', 'student2@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', NOW());

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

-- Sample certificates
INSERT INTO certificates (title, description, template_html, award_criteria, course_id, is_active) VALUES
('PHP Fundamentals Certificate', 'Awarded for completing the Introduction to PHP course', '<div style="text-align: center; padding: 50px; font-family: Arial, sans-serif;"><h1 style="color: #333; margin-bottom: 20px;">Certificate of Completion</h1><p style="font-size: 18px; margin: 20px 0;">This certifies that</p><h2 style="color: #007bff; margin: 30px 0;">{{student_name}}</h2><p style="font-size: 16px; margin: 20px 0;">has successfully completed</p><h3 style="margin: 30px 0;">{{course_title}}</h3><p style="font-size: 14px; margin: 40px 0;">Awarded on {{completion_date}}</p><div style="margin-top: 50px;"><p>Verification Code: {{verification_code}}</p></div></div>', 'course_completion', 1, TRUE),
('Web Development Certificate', 'Awarded for completing the Web Development Basics course', '<div style="text-align: center; padding: 50px; font-family: Arial, sans-serif;"><h1 style="color: #333; margin-bottom: 20px;">Certificate of Completion</h1><p style="font-size: 18px; margin: 20px 0;">This certifies that</p><h2 style="color: #007bff; margin: 30px 0;">{{student_name}}</h2><p style="font-size: 16px; margin: 20px 0;">has successfully completed</p><h3 style="margin: 30px 0;">{{course_title}}</h3><p style="font-size: 14px; margin: 40px 0;">Awarded on {{completion_date}}</p><div style="margin-top: 50px;"><p>Verification Code: {{verification_code}}</p></div></div>', 'course_completion', 2, TRUE);

-- Sample badges
INSERT INTO badges (name, description, award_criteria, course_id, color, is_active) VALUES
('PHP Beginner', 'Completed Introduction to PHP course', 'course_completion', 1, '#007bff', TRUE),
('Web Developer', 'Completed Web Development Basics course', 'course_completion', 2, '#28a745', TRUE),
('Database Expert', 'Completed Database Design course', 'course_completion', 3, '#dc3545', TRUE),
('Quick Learner', 'Completed a lesson', 'lesson_completion', NULL, '#ffc107', TRUE),
('Dedicated Student', '7-day learning streak', 'streak', NULL, '#6f42c1', TRUE);