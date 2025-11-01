# 🔧 دليل إصلاح كلمة المرور - Password Fix Guide

إذا واجهت مشاكل في كلمة المرور، استخدم إحدى الطرق التالية:

## الطريقة 1: استخدام سكريبت إعادة التعيين (الأسهل) ⭐

1. افتح المتصفح واذهب إلى:
   ```
   http://localhost:8888/worshipTeam/admin/reset_admin_password.php
   ```

2. أدخل:
   - اسم المستخدم: `admin`
   - كلمة المرور الجديدة

3. اضغط "تحديث كلمة المرور"

4. **احذف الملف بعد الاستخدام!**

---

## الطريقة 2: استخدام مولد الهاش

1. افتح:
   ```
   http://localhost:8888/worshipTeam/admin/generate_password_hash.php
   ```

2. أدخل كلمة المرور المطلوبة

3. انسخ كود SQL المعروض

4. نفذه في phpMyAdmin

---

## الطريقة 3: SQL مباشرة (phpMyAdmin)

### لإعادة تعيين إلى `admin123`:

```sql
USE worshipteam;

UPDATE admins 
SET password_hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'
WHERE username = 'admin';
```

ثم سجل الدخول بـ:
- Username: `admin`
- Password: `admin123`

### لإنشاء هاش جديد:

1. أنشئ ملف PHP مؤقت:
```php
<?php
echo password_hash('your_password_here', PASSWORD_DEFAULT);
?>
```

2. نفذه واحصل على الهاش

3. استخدمه في SQL:
```sql
UPDATE admins 
SET password_hash = 'YOUR_HASH_HERE'
WHERE username = 'admin';
```

---

## الطريقة 4: تغيير كلمة المرور من لوحة التحكم (إذا كنت مسجل دخول)

1. سجل الدخول إلى لوحة التحكم

2. اضغط على "🔒 تغيير كلمة المرور" في الأعلى

3. أدخل:
   - كلمة المرور الحالية
   - كلمة المرور الجديدة
   - تأكيد كلمة المرور

4. احفظ

---

## ملاحظات مهمة:

⚠️ **احذف هذه الملفات بعد الاستخدام:**
- `reset_admin_password.php`
- `generate_password_hash.php`

✅ **الطريقة الأكثر أماناً:** استخدام "تغيير كلمة المرور" من لوحة التحكم بعد تسجيل الدخول

---

## التحقق من أن كلمة المرور تعمل:

1. اذهب إلى: `http://localhost:8888/worshipTeam/admin/login.html`

2. سجل الدخول بالبيانات الجديدة

3. إذا لم تعمل، جرب:
   - مسح الكوكيز (Cookies)
   - استخدام نافذة خاصة (Incognito)
   - التحقق من قاعدة البيانات مباشرة

---

## مشاكل شائعة:

**"كلمة المرور غير صحيحة" بعد التحديث:**
- تأكد من استخدام `password_hash()` وليس `md5()` أو `sha1()`
- تحقق من أن الهاش يبدأ بـ `$2y$` أو `$2a$`

**الهاش لا يعمل:**
- استخدم `reset_admin_password.php` مباشرة
- تأكد من تحديث السجل الصحيح في قاعدة البيانات

**نسيت كلمة المرور تماماً:**
- استخدم `reset_admin_password.php`
- أو أعد تعيين إلى `admin123` باستخدام SQL أعلاه

