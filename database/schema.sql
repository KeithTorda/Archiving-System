-- Atok Elementary School Digital Archiving System Database Schema

-- Create database
CREATE DATABASE IF NOT EXISTS atok_archiving_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE atok_archiving_system;

-- Users table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    role ENUM('admin', 'school_head', 'registrar') NOT NULL DEFAULT 'registrar',
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    last_login DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Students table
CREATE TABLE students (
    id INT PRIMARY KEY AUTO_INCREMENT,
    lrn VARCHAR(12) UNIQUE NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    middle_name VARCHAR(50) NULL,
    gender ENUM('male', 'female') NOT NULL,
    birth_date DATE NOT NULL,
    birth_place VARCHAR(100) NOT NULL,
    address TEXT NOT NULL,
    contact_number VARCHAR(20) NULL,
    email VARCHAR(100) NULL,
    guardian_name VARCHAR(100) NOT NULL,
    guardian_contact VARCHAR(20) NULL,
    status ENUM('enrolled', 'transferred', 'graduated', 'dropped') NOT NULL DEFAULT 'enrolled',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Student records table
CREATE TABLE student_records (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    school_year VARCHAR(9) NOT NULL,
    grade_level ENUM('Kinder', 'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6') NOT NULL,
    section VARCHAR(20) NULL,
    record_type ENUM('report_card', 'form_137', 'enrollment_form', 'other') NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT NOT NULL,
    uploaded_by INT NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Personnel table
CREATE TABLE personnel (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id VARCHAR(20) UNIQUE NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    middle_name VARCHAR(50) NULL,
    position VARCHAR(100) NOT NULL,
    department VARCHAR(100) NOT NULL,
    email VARCHAR(100) NULL,
    contact_number VARCHAR(20) NULL,
    hire_date DATE NOT NULL,
    status ENUM('active', 'retired', 'resigned', 'terminated') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Personnel records table
CREATE TABLE personnel_records (
    id INT PRIMARY KEY AUTO_INCREMENT,
    personnel_id INT NOT NULL,
    record_type ENUM('201_file', 'appointment', 'evaluation', 'other') NOT NULL,
    document_title VARCHAR(255) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT NOT NULL,
    uploaded_by INT NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (personnel_id) REFERENCES personnel(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
);

-- School forms table
CREATE TABLE school_forms (
    id INT PRIMARY KEY AUTO_INCREMENT,
    form_type ENUM('SF1', 'SF2', 'SF5', 'SF10', 'other') NOT NULL,
    school_year VARCHAR(9) NOT NULL,
    grade_level ENUM('Kinder', 'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6', 'All') NOT NULL,
    quarter ENUM('Q1', 'Q2', 'Q3', 'Q4', 'Annual') NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    file_name VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT NOT NULL,
    uploaded_by INT NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Activity logs table
CREATE TABLE activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    description TEXT NOT NULL,
    record_id INT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Download logs table
CREATE TABLE download_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    record_type ENUM('student', 'personnel', 'school_form') NOT NULL,
    record_id INT NOT NULL,
    downloaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45) NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Backup logs table
CREATE TABLE backup_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    backup_type ENUM('full', 'student', 'personnel', 'school_forms') NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Insert default admin user
INSERT INTO users (username, password, full_name, email, role) VALUES 
('admin', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin@atok.edu.ph', 'admin');

-- Create indexes for better performance
CREATE INDEX idx_students_lrn ON students(lrn);
CREATE INDEX idx_students_name ON students(last_name, first_name);
CREATE INDEX idx_student_records_student ON student_records(student_id);
CREATE INDEX idx_student_records_school_year ON student_records(school_year);
CREATE INDEX idx_personnel_employee_id ON personnel(employee_id);
CREATE INDEX idx_personnel_name ON personnel(last_name, first_name);
CREATE INDEX idx_personnel_records_personnel ON personnel_records(personnel_id);
CREATE INDEX idx_school_forms_type ON school_forms(form_type);
CREATE INDEX idx_school_forms_year ON school_forms(school_year);
CREATE INDEX idx_activity_logs_user ON activity_logs(user_id);
CREATE INDEX idx_activity_logs_created ON activity_logs(created_at);
CREATE INDEX idx_download_logs_user ON download_logs(user_id);
CREATE INDEX idx_download_logs_created ON download_logs(downloaded_at); 