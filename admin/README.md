# 🔐 Admin Panel - لوحة تحكم المشرف

## Setup Instructions - تعليمات الإعداد

### Step 1: Create Admin Table
Run the SQL file to create the admin users table:

```bash
mysql -u your_username -p worshipteam < ../database/admin_setup.sql
```

Or import it via phpMyAdmin:
- Go to phpMyAdmin
- Select `worshipteam` database
- Import `database/admin_setup.sql`

**If you already have the admin table**, run the migration instead:
```bash
mysql -u your_username -p worshipteam < ../database/admin_migration.sql
```

### Step 2: Default Login Credentials
**Username:** `admin`  
**Password:** `admin123`  
**Email:** `moody.gindy@gmail.com`

⚠️ **Important:** Change the password after first login!

### Step 3: Access Admin Panel
1. Open: `https://kdsc.fun/worshipTeam/admin/login.html`
2. Login with credentials above
3. You'll be redirected to the dashboard

### Step 4: Forgot Password Feature
- Click "نسيت كلمة المرور؟" on the login page
- Enter your email: `moody.gindy@gmail.com`
- Check your email for the reset link
- Reset link is valid for 1 hour

---

## Features - المميزات

✅ **Question Management:**
- 📝 Add new questions
- ✏️ Edit existing questions
- 🗑️ Delete questions
- 🔍 Search questions
- 🏷️ Filter by category

✅ **Security:**
- 🔐 Secure login with password hashing
- 🍪 Session-based authentication
- 🚪 Automatic logout on inactivity

---

## Changing Admin Password

To change the password, you can:

### Option 1: Via PHP Script
Create a file `change_password.php`:
```php
<?php
require_once __DIR__ . '/../vendor/autoload.php';
use QuizGame\Database;

$db = Database::getInstance();
$newPassword = 'your_new_password';
$hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

$db->query(
    "UPDATE admins SET password_hash = ? WHERE username = 'admin'",
    [$hashedPassword]
);

echo "Password updated successfully!";
?>
```

### Option 2: Via SQL
```sql
UPDATE admins 
SET password_hash = '$2y$10$YOUR_NEW_HASH_HERE' 
WHERE username = 'admin';
```

Generate hash using PHP:
```php
echo password_hash('your_password', PASSWORD_DEFAULT);
```

---

## API Endpoints

### Authentication
- `POST /api/admin-login` - Login
- `POST /api/admin-logout` - Logout
- `GET /api/admin-check` - Check authentication status
- `POST /api/admin-forgot-password` - Request password reset (sends email)
- `POST /api/admin-reset-password` - Reset password with token

### Questions
- `GET /api/admin-questions` - List all questions (with filters)
- `POST /api/admin-questions` - Create new question
- `PUT /api/admin-questions` - Update question
- `DELETE /api/admin-questions?id={id}` - Delete question

All admin endpoints require authentication via session (except login and password reset).

---

## File Structure

```
admin/
├── login.html      # Login page
├── dashboard.html  # Main admin dashboard
└── README.md       # This file
```

---

## Troubleshooting

**Can't login:**
- Check if admin table exists
- Verify default admin user exists in database
- Check browser console for errors
- Make sure session is working (check PHP session settings)
- Use "Forgot Password" feature if you forgot your password

**Forgot Password:**
- Make sure your email is set correctly in database (`moody.gindy@gmail.com`)
- Check spam/junk folder for reset email
- Reset link expires after 1 hour - request a new one if expired
- Verify PHP `mail()` function is configured on your server

**Email not sending:**
- Check PHP mail configuration (`php.ini`)
- For production, consider using SMTP (see Email Configuration below)
- Check server error logs for email sending errors
- On localhost, emails might not send - use SMTP for testing

**401 Unauthorized:**
- Session might have expired - try logging in again
- Check that cookies are enabled in browser
- Verify session_start() is called in API

**Questions not loading:**
- Check database connection
- Verify questions table exists
- Check browser console for API errors

## Email Configuration

The forgot password feature uses PHP's `mail()` function by default. For production servers, you may want to use SMTP.

### Using SMTP (Optional)

To use SMTP instead of PHP's mail(), you'll need to:
1. Install PHPMailer: `composer require phpmailer/phpmailer`
2. Update `sendPasswordResetEmail()` function in `api/index.php` to use PHPMailer

For now, the default `mail()` function works on most servers.

---

## Security Notes

- Admin passwords are hashed using PHP `password_hash()`
- Sessions use HTTP-only cookies
- All admin endpoints require authentication
- Change default password immediately!

---

## Need Help?

Check:
1. Browser console (F12) for JavaScript errors
2. PHP error logs for server errors
3. Database connection settings in `config/database.php`

