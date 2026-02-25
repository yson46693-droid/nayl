-- ============================================
-- رفع حد اتصالات MySQL بالساعة (max_connections_per_hour)
-- ============================================
-- يُشغّل مرة واحدة من مدير MySQL (صلاحيات ALTER USER أو root).
-- على الاستضافة المشتركة (مثل Hostinger) قد لا تملك الصلاحية؛ أرسل هذا الملف للدعم لتنفيذه أو اطلب رفع الحد إلى 10000.
--
-- استبدل 'u486977009_academyuserv1' و '%' إذا كان مستخدمك مختلفاً (انظر api/.env → DB_USER).
-- استبدل 10000 بحد أعلى إذا أردت (مثلاً 50000).
-- ============================================

-- رفع الحد إلى 10000 اتصال/ساعة (بدلاً من 500) — عدّل اسم المستخدم والعنوان إن لزم
ALTER USER 'u486977009_academyuserv1'@'%'
  WITH MAX_CONNECTIONS_PER_HOUR 10000
       MAX_QUERIES_PER_HOUR 0
       MAX_UPDATES_PER_HOUR 0
       MAX_USER_CONNECTIONS 0;

FLUSH PRIVILEGES;

-- إذا فشل الأمر (مثلاً المستخدم مربوط بـ host آخر)، جرّب:
-- ALTER USER 'u486977009_academyuserv1'@'localhost' WITH MAX_CONNECTIONS_PER_HOUR 10000;
-- أو من phpMyAdmin → تبويب "User accounts" → تحرير المستخدم → Max connections per hour = 10000
