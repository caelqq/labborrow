CREATE TABLE users (
    id_number CHAR(9) PRIMARY KEY,
    first_name VARCHAR(21) NOT NULL,
    middle_name VARCHAR(21) NULL,
    family_name VARCHAR(21) NOT NULL,
    name_extension VARCHAR(10) NULL,
    birthdate DATE NOT NULL,
    purok_street VARCHAR(50) NOT NULL,
    barangay VARCHAR(50) NOT NULL,
    municipality VARCHAR(50) NOT NULL,
    province VARCHAR(50) NOT NULL,
    country VARCHAR(50) NOT NULL,
    zip_code CHAR(4) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    username VARCHAR(17) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    course VARCHAR(10) NOT NULL,
    year_level VARCHAR(3) NOT NULL,
    contact_number_enc VARCHAR(255) NOT NULL,
    guardian_name VARCHAR(50) NULL,
    role ENUM('user','admin') NOT NULL DEFAULT 'user',
    is_verified TINYINT(1) NOT NULL DEFAULT 0,
    consent_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE security_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id CHAR(9) NOT NULL,
    question_no TINYINT NOT NULL,
    answer_hash VARCHAR(255) NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id_number)
);

CREATE TABLE otps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id CHAR(9) NOT NULL,
    purpose ENUM('register','login','reset') NOT NULL,
    otp_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    attempts TINYINT NOT NULL DEFAULT 0,
    is_used TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id_number)
);

CREATE TABLE password_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id CHAR(9) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id_number)
);

CREATE TABLE activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(30) NOT NULL,
    username_typed VARCHAR(100) NULL,
    user_id CHAR(9) NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent VARCHAR(255) NULL,
    details VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE security_flags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pattern VARCHAR(30) NOT NULL,
    target VARCHAR(100) NOT NULL,
    severity VARCHAR(10) NOT NULL,
    details VARCHAR(255) NULL,
    flag_date DATE NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (pattern, target, flag_date)
);

CREATE TABLE ip_blocks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    blocked_until DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE login_attempts (
    username VARCHAR(100) PRIMARY KEY,
    failed_count INT NOT NULL DEFAULT 0,
    locked_until DATETIME NULL
);

-- Added for the staff role
ALTER TABLE users MODIFY role ENUM('user','staff','admin') NOT NULL DEFAULT 'user';