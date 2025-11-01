-- Admin Authentication Setup
-- Run this file to create admin user table

USE worshipteam;

-- Admin users table
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    reset_token VARCHAR(255) NULL,
    reset_token_expires DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default admin (username: admin, password: admin123)
-- You should change this password after first login!
INSERT INTO admins (username, password_hash, email) 
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'moody.gindy@gmail.com')
ON DUPLICATE KEY UPDATE 
    username = username,
    email = 'moody.gindy@gmail.com';

-- Password reset tokens table (optional - can also store in admins table)
-- Note: Default password is 'admin123'
-- To create a new password hash, use PHP: password_hash('your_password', PASSWORD_DEFAULT)

