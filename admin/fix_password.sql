-- Quick Password Reset SQL
-- Run this in phpMyAdmin or MySQL command line to reset admin password

USE worshipteam;

-- Option 1: Reset password to 'admin123' (hash for 'admin123')
UPDATE admins 
SET password_hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'
WHERE username = 'admin';

-- Option 2: Generate a new hash for your desired password
-- First, create a PHP file with this code to generate hash:
-- <?php echo password_hash('your_new_password', PASSWORD_DEFAULT); ?>
-- Then replace the hash below:

-- UPDATE admins 
-- SET password_hash = 'PASTE_YOUR_NEW_HASH_HERE'
-- WHERE username = 'admin';

-- To create a password hash, use PHP:
-- <?php
-- require_once __DIR__ . '/../vendor/autoload.php';
-- $password = 'your_password_here';
-- echo password_hash($password, PASSWORD_DEFAULT);
-- ?>

-- After running, you can login with:
-- Username: admin
-- Password: admin123 (if you used Option 1)

