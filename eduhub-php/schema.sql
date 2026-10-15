-- EduHub database. Import in phpMyAdmin (Import tab) or: mysql -u root -p < schema.sql
CREATE DATABASE IF NOT EXISTS eduhub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE eduhub;

CREATE TABLE users(
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  pass_hash VARCHAR(255) NOT NULL,                 -- bcrypt via password_hash()
  role ENUM('learner','teacher','admin') NOT NULL DEFAULT 'learner',
  failed_attempts TINYINT NOT NULL DEFAULT 0,      -- login lockout counter
  locked_until DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE courses(
  id INT AUTO_INCREMENT PRIMARY KEY,
  teacher_id INT NOT NULL,
  title VARCHAR(150) NOT NULL,
  description TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(teacher_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE TABLE enrollments(
  course_id INT NOT NULL, user_id INT NOT NULL,
  PRIMARY KEY(course_id,user_id),
  FOREIGN KEY(course_id) REFERENCES courses(id) ON DELETE CASCADE,
  FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE TABLE slides(
  id INT AUTO_INCREMENT PRIMARY KEY,
  course_id INT NOT NULL,
  title VARCHAR(150) NOT NULL,
  content TEXT NOT NULL,
  position INT NOT NULL DEFAULT 1,
  FOREIGN KEY(course_id) REFERENCES courses(id) ON DELETE CASCADE
);
CREATE TABLE slide_progress(                       -- which slides a learner has read
  user_id INT NOT NULL, slide_id INT NOT NULL,
  PRIMARY KEY(user_id,slide_id),
  FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY(slide_id) REFERENCES slides(id) ON DELETE CASCADE
);
CREATE TABLE assessments(
  id INT AUTO_INCREMENT PRIMARY KEY,
  course_id INT NOT NULL,
  title VARCHAR(150) NOT NULL,
  type ENUM('quiz','test','assignment') NOT NULL,
  due_date DATE NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(course_id) REFERENCES courses(id) ON DELETE CASCADE
);
CREATE TABLE questions(
  id INT AUTO_INCREMENT PRIMARY KEY,
  assessment_id INT NOT NULL,
  kind ENUM('mcq','short') NOT NULL,               -- mcq = auto-marked, short = teacher marks
  text TEXT NOT NULL,
  options TEXT NULL,                               -- JSON array for mcq
  correct_index TINYINT NULL,
  points INT NOT NULL DEFAULT 10,
  FOREIGN KEY(assessment_id) REFERENCES assessments(id) ON DELETE CASCADE
);
CREATE TABLE submissions(
  id INT AUTO_INCREMENT PRIMARY KEY,
  assessment_id INT NOT NULL, user_id INT NOT NULL,
  answers TEXT NOT NULL,                           -- JSON {question_id: answer}
  marks TEXT NULL,                                 -- JSON {question_id: mark}
  score INT NULL, max_score INT NOT NULL,
  status ENUM('pending','graded') NOT NULL DEFAULT 'pending',
  feedback TEXT NULL,
  submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY one_attempt(assessment_id,user_id),
  FOREIGN KEY(assessment_id) REFERENCES assessments(id) ON DELETE CASCADE,
  FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
);
-- Make your first admin: register normally, then run:
-- UPDATE users SET role='admin' WHERE email='you@example.com';
