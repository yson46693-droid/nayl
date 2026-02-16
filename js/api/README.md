# APIs Documentation

## هيكل الملفات

```
js/api/
├── auth.js          # APIs المصادقة والتسجيل فقط
├── rateLimiter.js   # Rate Limiting للأجهزة
├── utils.js         # وظائف مساعدة مشتركة
└── README.md        # هذا الملف
```

## الاستخدام

### 1. API التسجيل (Signup)

```javascript
import { signup } from './api/auth.js';

const userData = {
    email: 'user@example.com',
    phone: '1234567890',
    countryCode: '+20',
    password: 'password123',
    confirmPassword: 'password123',
    country: 'مصر',
    city: 'القاهرة',
    whatsappVerified: false,
    termsAccepted: true
};

const result = await signup(userData);

if (result.success) {
    console.log('تم التسجيل بنجاح');
} else {
    console.error('خطأ:', result.error);
}
```

### 2. Rate Limiting

```javascript
import { checkRateLimit, getRateLimitInfo } from './api/rateLimiter.js';

// التحقق من Rate Limit
const info = checkRateLimit();
if (info.allowed) {
    console.log(`الطلبات المتبقية: ${info.remaining}`);
} else {
    console.log('تم تجاوز الحد:', info.message);
}
```

### 3. Utils - الوظائف المساعدة

```javascript
import { 
    validateEmail, 
    validatePhone, 
    sanitizeInput 
} from './api/utils.js';

// التحقق من البريد الإلكتروني
if (validateEmail('user@example.com')) {
    console.log('البريد صحيح');
}

// تنظيف المدخلات
const clean = sanitizeInput('<script>alert("xss")</script>');
```

## Rate Limiting

- **الحد الأقصى**: 5 طلبات
- **الفترة الزمنية**: 15 دقيقة
- **مدة القفل**: 30 دقيقة بعد تجاوز الحد

## الأمان

- ✅ حماية من XSS (تنظيف المدخلات)
- ✅ التحقق من جميع المدخلات
- ✅ Rate Limiting للأجهزة
- ✅ CSRF Token Support
- ✅ Prepared Statements (في Backend)

## ملاحظات

- جميع APIs تستخدم ES6 Modules
- يجب إضافة `type="module"` في script tag
- Rate Limiting يعمل في Frontend (يجب إضافة Backend Rate Limiting أيضاً)
