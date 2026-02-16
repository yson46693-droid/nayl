# PHP API Documentation

## هيكل الملفات

```
api/
├── auth/
│   └── signup.php          # API تسجيل حساب جديد
├── config/
│   ├── database.php         # إعدادات قاعدة البيانات
│   ├── env.php              # محمل متغيرات البيئة
│   ├── security.php         # وظائف الأمان
│   └── rateLimiter.php      # Rate Limiting
├── .env                     # متغيرات البيئة (لا ترفع إلى Git!)
├── .env.example             # قالب ملف .env
├── .htaccess                # إعدادات Apache
└── README.md                # هذا الملف
```

## الإعداد

### 1. إعداد قاعدة البيانات

**⚠️ مهم جداً**: استخدم ملف `.env` لتخزين معلومات قاعدة البيانات بشكل آمن.

1. انسخ ملف `.env.example` إلى `.env`:
```bash
cp api/.env.example api/.env
```

2. عدل ملف `api/.env` وأدخل معلومات قاعدة البيانات:
```env
DB_HOST=localhost
DB_NAME=amrnayl_academy
DB_USER=your_username
DB_PASS=your_password
DB_CHARSET=utf8mb4
```

**ملاحظة أمنية**: 
- لا ترفع ملف `.env` إلى Git أبداً (موجود في `.gitignore`)
- ملف `.env` محمي من الوصول عبر `.htaccess`

### 2. إنشاء قاعدة البيانات

قم بتشغيل ملف `database-schema.sql` لإنشاء الجداول.

### 3. إعدادات Apache

تأكد من تفعيل:
- `mod_rewrite`
- `mod_headers`

## API Endpoints

### POST /api/auth/signup.php

تسجيل حساب جديد

**Request Body:**
```json
{
    "email": "user@example.com",
    "phone": "1234567890",
    "country_code": "+20",
    "full_phone": "+201234567890",
    "password": "password123",
    "country": "مصر",
    "city": "القاهرة",
    "whatsapp_verified": false,
    "terms_accepted": true
}
```

**Response (Success):**
```json
{
    "success": true,
    "data": {
        "user": {
            "id": 1,
            "email": "user@example.com",
            "phone": "1234567890",
            "full_phone": "+201234567890",
            "country": "مصر",
            "city": "القاهرة",
            "whatsapp_verified": false,
            "created_at": "2026-01-23 12:00:00"
        },
        "message": "تم إنشاء الحساب بنجاح"
    }
}
```

**Response (Error):**
```json
{
    "success": false,
    "error": "البريد الإلكتروني مستخدم بالفعل"
}
```

## الأمان

### ✅ المميزات الأمنية:

1. **SQL Injection Protection**: استخدام Prepared Statements
2. **XSS Protection**: تنظيف جميع المدخلات
3. **Password Hashing**: استخدام bcrypt
4. **Rate Limiting**: 5 طلبات كل 15 دقيقة
5. **CSRF Protection**: دعم CSRF Tokens
6. **Input Validation**: التحقق من جميع المدخلات

### Rate Limiting:

- **الحد الأقصى**: 5 طلبات
- **الفترة الزمنية**: 15 دقيقة
- **مدة القفل**: 30 دقيقة بعد تجاوز الحد

## ملاحظات مهمة

1. **كلمات المرور**: يتم hashing باستخدام bcrypt قبل الحفظ
2. **Prepared Statements**: جميع الاستعلامات تستخدم Prepared Statements
3. **Error Logging**: الأخطاء تُسجل في `logs/php_errors.log`
4. **CORS**: يمكن تعديل إعدادات CORS في `.htaccess`

## اختبار API

### باستخدام cURL:

```bash
curl -X POST http://localhost/api/auth/signup.php \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "phone": "1234567890",
    "country_code": "+20",
    "full_phone": "+201234567890",
    "password": "password123",
    "country": "مصر",
    "city": "القاهرة",
    "whatsapp_verified": false,
    "terms_accepted": true
  }'
```

### باستخدام JavaScript:

```javascript
fetch('/api/auth/signup.php', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({
        email: 'test@example.com',
        phone: '1234567890',
        country_code: '+20',
        full_phone: '+201234567890',
        password: 'password123',
        country: 'مصر',
        city: 'القاهرة',
        whatsapp_verified: false,
        terms_accepted: true
    })
})
.then(response => response.json())
.then(data => console.log(data));
```
