# إعداد Bunny CDN لرفع الفيديوهات

## الخطوات المطلوبة:

### 1. الحصول على بيانات Bunny CDN

1. سجل الدخول إلى حسابك في [Bunny CDN](https://bunny.net)
2. انتقل إلى **Video Library**
3. أنشئ مكتبة فيديو جديدة أو استخدم مكتبة موجودة
4. احصل على:
   - **Library ID**: موجود في صفحة المكتبة
   - **API Key** (مفتاح المكتبة): موجود في إعدادات المكتبة
   - **Account API Key**: من **Account → API** في اللوحة (رابط مباشر: https://dash.bunny.net/account/api-key) — **مطلوب لإنشاء مكتبة جديدة لكل كورس**. لا تستخدم مفتاح المكتبة (Stream API Key) هنا؛ استخدم فقط مفتاح الحساب (Account API Key).

### 2. إعداد ملف .env

افتح ملف `api/.env` وأضف البيانات التالية:

```env
# إعدادات Bunny CDN
BUNNY_LIBRARY_ID=your_library_id_here
BUNNY_API_KEY=your_library_api_key_here
# مطلوب لإنشاء مكتبة جديدة لكل كورس - من Bunny Dashboard → Account → API
BUNNY_ACCOUNT_API_KEY=your_account_api_key_here
BUNNY_CDN_URL=https://video.bunnycdn.com
```

**مثال:**
```env
BUNNY_LIBRARY_ID=123456
BUNNY_API_KEY=abc123def456ghi789jkl012mno345pqr678stu901vwx234yz
BUNNY_ACCOUNT_API_KEY=your-account-api-key-from-account-api-page
BUNNY_CDN_URL=https://video.bunnycdn.com
```

**ملاحظة:** عند رفع كورس جديد، النظام ينشئ **مكتبة فيديو منفصلة** لكل كورس في Bunny CDN. لهذا تحتاج إلى **BUNNY_ACCOUNT_API_KEY** (Account API Key) وليس فقط مفتاح المكتبة.

### 3. إنشاء مجلد uploads

تأكد من وجود مجلد `uploads/thumbnails/` مع الصلاحيات المناسبة:

```bash
mkdir -p uploads/thumbnails
chmod 755 uploads/thumbnails
```

### 4. اختبار الرفع

1. افتح لوحة التحكم
2. انتقل إلى قسم "رفع الفيديوهات"
3. املأ بيانات الكورس والفيديوهات
4. اضغط "رفع الكورس"

## ملاحظات مهمة:

- **حجم الملفات**: الفيديوهات الكبيرة قد تستغرق وقتاً أطول في الرفع
- **الذاكرة**: تأكد من أن `memory_limit` في PHP كافٍ للملفات الكبيرة
- **الوقت**: قد تحتاج لزيادة `max_execution_time` في PHP للفيديوهات الكبيرة
- **الصلاحيات**: تأكد من أن مجلد `uploads` قابل للكتابة

## إعدادات PHP الموصى بها:

في ملف `php.ini`:

```ini
memory_limit = 512M
max_execution_time = 300
upload_max_filesize = 500M
post_max_size = 500M
```

## استكشاف الأخطاء:

إذا واجهت مشاكل:

1. تحقق من بيانات Bunny CDN في ملف `.env`
2. تحقق من سجلات الأخطاء في `error_log`
3. تأكد من أن API Key لديه صلاحيات الكتابة
4. تحقق من أن المكتبة نشطة في Bunny CDN

### خطأ 401 "Authorization has been denied"

- يعني أن المفتاح المستخدم لإنشاء المكتبة **غير صحيح** أو **نوع خاطئ**.
- **BUNNY_ACCOUNT_API_KEY** يجب أن يكون **مفتاح الحساب (Account API Key)** من:
  - Bunny Dashboard → **Account** (القائمة الجانبية) → **API**
  - أو الرابط: https://dash.bunny.net/account/api-key
- **لا تستخدم** مفتاح المكتبة (Stream API Key) الموجود في Stream → [مكتبة] → API في هذا الحقل؛ مفتاح الحساب مختلف ومخصص لعمليات الحساب مثل إنشاء مكتبات جديدة.
