# تشغيل MRI على IIS وMySQL في Windows

يستخدم هذا الإعداد IIS على `http://127.0.0.1:8040` وقاعدة MySQL مستقلة عن XAMPP. الربط محلي على الجهاز فقط. لا يشغّل Laravel باستخدام `artisan serve` ولا يعتمد على PHP أو MySQL داخل مجلد XAMPP.

## المتطلبات

ثبّت أو فعّل العناصر التالية بصلاحيات مسؤول Windows:

- IIS وميزة **CGI** (FastCGI).
- أدوات إدارة IIS وميزة **IIS URL Rewrite Module**؛ قواعد Laravel في `public/web.config` تعتمد عليها.
- PHP 8.4 NTS x64 مستقل خارج مجلد XAMPP، ويحتوي على `php.exe` و`php-cgi.exe`. نزّل الحزمة الرسمية من [Windows PHP downloads](https://windows.php.net/download/)، فكها مثلًا إلى `C:\PHP`، وانسخ `php.ini-production` إلى `php.ini`، واضبط `extension_dir="C:\PHP\ext"`. فعّل الامتدادات المطلوبة للمشروع (`pdo_mysql`, `fileinfo`, `gd`, `mbstring`, `openssl`, `simplexml`, `xml`, `zip`).
- خادم MySQL مستقل يعمل على `127.0.0.1:3306` وعميل `mysql.exe`. يلزم اتصال إداري أثناء التهيئة.
- Node.js وnpm لبناء ملفات الواجهة.

ثبّت MySQL Community Server كخدمة Windows من [MySQL Installer](https://dev.mysql.com/downloads/installer/)، واجعله يستمع على `127.0.0.1:3306`. فعّل ميزات **IIS CGI** و**IIS Management Scripts and Tools** و**IIS URL Rewrite**. لا تستخدم أي ملف تنفيذي من `D:\xampp`.

## التهيئة والاستيراد

1. جهّز نسخة SQL موثوقة محليًا؛ ملفات SQL وExcel التي قد تحتوي بيانات مرضى غير مرفقة بالمستودع العام.
2. في نافذة PowerShell عادية من مجلد المشروع، استعد حزم الواجهة وابنها:

```powershell
npm ci
npm run build
```

3. افتح PowerShell **كمسؤول** من مجلد المشروع، ثم نفّذ السكربت مع المسارات الصحيحة:

```powershell
Set-ExecutionPolicy -Scope Process Bypass
.\scripts\setup-iis-mysql.ps1 `
  -SqlFile "C:\path\to\mri.sql" `
  -PhpCgiPath "C:\PHP\php-cgi.exe" `
  -MySqlClientPath "C:\Program Files\MySQL\MySQL Server 8.0\bin\mysql.exe"
```

سيطلب السكربت كلمة مرور مدير MySQL، وينشئ قاعدة `mri` وحساب تطبيق محدودًا بقاعدة المشروع، ثم يستورد الملف. كما يبني الواجهة، يضبط `.env` محليًا، يسجل PHP عبر FastCGI، ويعيد استخدام موقع IIS المرتبط بمجلد `public` عند وجوده؛ وإلا ينشئ موقعًا جديدًا على المنفذ `8040`.

**حماية البيانات:** ملف SQL يحتوي أوامر `DROP TABLE`. السكربت يرفض المتابعة إذا كانت قاعدة `mri` أو حساب `mri_app` موجودين مسبقًا، ولا يستورد فوق قاعدة حالية. إذا فشل الاستيراد بعد إنشاء قاعدة جديدة، افحصها يدويًا قبل حذفها أو إعادة المحاولة.

لاستيراد حجوزات Excel يدويًا، استخدم ملفًا محليًا منقحًا من بيانات المرضى:

```powershell
php artisan mri:import-excel "C:\path\to\sanitized-bookings.xlsx"
```

## التحقق وإزالة XAMPP

افتح `http://127.0.0.1:8040/up` ثم صفحة الدخول. لا توقف أو تحذف XAMPP قبل التأكد من عمل الموقع الجديد ومراجعة أي قواعد بيانات أو مواقع أخرى داخله؛ ملف `mri.sql` لا يضمن أنه نسخة احتياطية لبقية بيانات XAMPP. بعد التحقق، أوقف خدمة MariaDB/MySQL الخاصة بـXAMPP من لوحة الخدمات، ثم أزل XAMPP من أداة إلغاء التثبيت في Windows. لا تحذف مجلد XAMPP يدويًا قبل الاحتفاظ بنسخة احتياطية مما تحتاجه.
