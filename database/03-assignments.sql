-- ===== ASSIGNMENTS SYSTEM (Expanded) =====
-- Comprehensive assignment system supporting quizzes, essays, projects, discussions, and peer review

-- Assignments table (unified assignment system)
CREATE TABLE assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lesson_id INT NOT NULL,
    course_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    assignment_type ENUM('quiz', 'essay', 'file_submission', 'project', 'discussion', 'peer_review') NOT NULL DEFAULT 'essay',
    
    -- Submission settings
    allow_file_upload BOOLEAN DEFAULT TRUE,
    allowed_file_types VARCHAR(255), -- Comma-separated: pdf,doc,docx,jpg,png
    max_file_size_mb INT DEFAULT 10,
    submission_deadline DATETIME NULL,
    late_submission_allowed BOOLEAN DEFAULT FALSE,
    late_submission_penalty_percent INT DEFAULT 0, -- Percentage points deducted per day late
    
    -- Grading settings
    points_possible INT NOT NULL DEFAULT 100,
    grading_type ENUM('points', 'pass_fail', 'rubric') DEFAULT 'points',
    allow_resubmission BOOLEAN DEFAULT TRUE,
    max_resubmissions INT NULL, -- NULL = unlimited
    
    -- Discussion/Peer Review settings
    min_peer_reviews INT DEFAULT 0, -- For peer review assignments
    peer_review_deadline DATETIME NULL,
    
    -- Status
    is_published BOOLEAN DEFAULT FALSE,
    is_draft BOOLEAN DEFAULT TRUE,
    show_to_students BOOLEAN DEFAULT FALSE,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (lesson_id) REFERENCES lessons(id),
    FOREIGN KEY (course_id) REFERENCES courses(id),
    INDEX idx_lesson (lesson_id),
    INDEX idx_course (course_id)
);

-- Grading rubrics for rubric-based assignments
CREATE TABLE grading_rubrics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    
    -- Rubric configuration
    total_points INT NOT NULL DEFAULT 100,
    is_active BOOLEAN DEFAULT TRUE,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (assignment_id) REFERENCES assignments(id),
    UNIQUE KEY unique_assignment_rubric (assignment_id)
);

-- Rubric criteria (descriptors/scales within a rubric)
CREATE TABLE rubric_criteria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rubric_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    points_possible INT NOT NULL,
    order_num INT DEFAULT 1,
    
    FOREIGN KEY (rubric_id) REFERENCES grading_rubrics(id),
    INDEX idx_rubric (rubric_id)
);

-- Rubric levels (e.g., Excellent, Good, Fair, Poor)
CREATE TABLE rubric_levels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    criteria_id INT NOT NULL,
    level_name VARCHAR(100) NOT NULL, -- "Excellent", "Good", "Fair", "Poor"
    points INT NOT NULL,
    description TEXT,
    order_num INT DEFAULT 1,
    
    FOREIGN KEY (criteria_id) REFERENCES rubric_criteria(id),
    INDEX idx_criteria (criteria_id)
);

-- Student submissions table
CREATE TABLE submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT NOT NULL,
    user_id INT NOT NULL,
    submission_number INT DEFAULT 1, -- For resubmissions
    
    -- Submission content
    text_content TEXT, -- For essay assignments
    file_path VARCHAR(255), -- For file submissions
    file_name VARCHAR(255),
    
    -- Peer review assignments
    discussion_content TEXT, -- For discussion assignments
    
    -- Status
    is_submitted BOOLEAN DEFAULT FALSE,
    submitted_at TIMESTAMP NULL,
    is_graded BOOLEAN DEFAULT FALSE,
    graded_at TIMESTAMP NULL,
    
    -- Late submission tracking
    days_late INT DEFAULT 0,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (assignment_id) REFERENCES assignments(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_assignment (assignment_id),
    INDEX idx_user (user_id),
    INDEX idx_submitted_at (submitted_at),
    UNIQUE KEY unique_latest_submission (assignment_id, user_id, submission_number)
);

-- Grades/Marks for assignments
CREATE TABLE submission_grades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    submission_id INT NOT NULL UNIQUE,
    grader_id INT NOT NULL,
    
    -- Grade data
    points_earned INT NULL,
    points_possible INT NOT NULL,
    percentage DECIMAL(5, 2) NULL,
    late_penalty_points INT DEFAULT 0,
    final_points INT NULL,
    
    -- Feedback
    feedback_text TEXT,
    rubric_scores JSON, -- For rubric-based grades: {"criteria_id": "level_id", ...}
    
    -- Status
    is_draft BOOLEAN DEFAULT TRUE, -- Draft grades can be edited; published cannot
    is_published BOOLEAN DEFAULT FALSE,
    published_at TIMESTAMP NULL,
    
    -- Timestamps
    graded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (submission_id) REFERENCES submissions(id),
    FOREIGN KEY (grader_id) REFERENCES users(id),
    INDEX idx_submission (submission_id),
    INDEX idx_grader (grader_id)
);

-- Peer reviews (for peer review and discussion assignments)
CREATE TABLE peer_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT NOT NULL,
    submission_id INT NOT NULL, -- The submission being reviewed
    reviewer_id INT NOT NULL, -- Student providing review
    
    -- Review content
    review_text TEXT,
    helpful_rating INT NULL, -- 1-5 scale
    
    -- Status
    is_submitted BOOLEAN DEFAULT FALSE,
    submitted_at TIMESTAMP NULL,
    is_anonymous BOOLEAN DEFAULT FALSE,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (assignment_id) REFERENCES assignments(id),
    FOREIGN KEY (submission_id) REFERENCES submissions(id),
    FOREIGN KEY (reviewer_id) REFERENCES users(id),
    INDEX idx_assignment (assignment_id),
    INDEX idx_submission (submission_id),
    INDEX idx_reviewer (reviewer_id)
);

-- Submission file attachments (for storing multiple files or version history)
CREATE TABLE submission_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    submission_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_size INT, -- In bytes
    mime_type VARCHAR(100),
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (submission_id) REFERENCES submissions(id),
    INDEX idx_submission (submission_id)
);
