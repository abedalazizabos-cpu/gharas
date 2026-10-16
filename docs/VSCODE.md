# فتح المشروع وتشغيله في VS Code (Windows)

## مرة واحدة
1. ثبّت **VS Code** من code.visualstudio.com، و**XAMPP** (أو Laragon) لتوفير PHP 8.2+ وMySQL، و**Composer** من getcomposer.org.
2. تأكد أن PHP مضاف إلى PATH: افتح موجه الأوامر واكتب `php -v` و`composer -V`.
3. فك ضغط `gharas.zip` في مكان ثابت، مثل `C:\gharas`.
4. في VS Code: **File ← Open Folder** واختر المجلد `gharas`. اضغط **Yes, I trust the authors** إن ظهر السؤال.
5. ستظهر رسالة بتثبيت الإضافات المقترحة (Laravel وPHP وBlade)، فاضغط **Install**.
6. شغّل MySQL من لوحة XAMPP، وأنشئ قاعدة بيانات باسم `gharas` (من phpMyAdmin).
7. افتح الطرفية من **Terminal ← New Terminal** ونفّذ:
   ```bat
   copy .env.example .env
   ```
   ثم افتح ملف `.env` وعدّل `DB_DATABASE=gharas` و`DB_USERNAME=root` و`DB_PASSWORD=` حسب جهازك.
8. **Terminal ← Run Task ← «تثبيت أول مرة»**.

## كل يوم
- **Ctrl+Shift+B** يشغّل الموقع (مهمة «تشغيل الموقع»)، ثم افتح http://127.0.0.1:8000
- بعد تنزيل نسخة جديدة من المشروع: **Terminal ← Run Task ← «تحديث قاعدة البيانات (migrate)»**.
- المهام الأخرى في القائمة نفسها: الاختبارات، أرشفة الأسابيع، النسخة الاحتياطية.

## عند تحديث المشروع بنسخة جديدة
انسخ ملفات النسخة الجديدة فوق المجلد القديم، **مع الإبقاء على ملف `.env` ومجلد `storage`** (فيهما إعداداتك والملفات المؤرشفة والشعار المرفوع)، ثم شغّل مهمة «تحديث بعد نسخة جديدة» (تثبّت الحزم الجديدة وتحدّث قاعدة البيانات).

ملفات PDF تحتاج إضافة `gd` في PHP: افتح `php.ini` من XAMPP (Config ← PHP) واحذف `;` من أول السطر `extension=gd`، ثم أعد تشغيل Apache.

## أين أعدّل؟
| ما تريد تعديله | المكان |
|---|---|
| الصفحات والتصميم | `resources/views` |
| الروابط والصلاحيات | `routes/web.php` و`app/Policies` |
| منطق الصفحات | `app/Http/Controllers` |
| الجداول | `database/migrations` |
| النصوص الافتراضية (الأدعية...) | `app/Models/Setting.php` |
