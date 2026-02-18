#!/bin/bash
# سكربت رفع التعديلات إلى المستودع: https://github.com/yson46693-droid/nayl.git

set -e
REPO_URL="https://github.com/yson46693-droid/nayl.git"
BRANCH="${1:-main}"

echo "=== رفع التعديلات إلى المستودع ==="
echo "المستودع: $REPO_URL"
echo "الفرع: $BRANCH"
echo ""

# التحقق من وجود تغييرات
if [ -z "$(git status --porcelain)" ]; then
    echo "لا توجد تعديلات لرفعها."
    exit 0
fi

echo "التعديلات الحالية:"
git status --short
echo ""

# إضافة جميع الملفات
git add -A

# طلب رسالة الـ commit إن لم تُمرَّر كمعامل ثاني
if [ -n "$2" ]; then
    MSG="$2"
else
    read -p "رسالة الـ commit (أو Enter لاستخدام التاريخ): " MSG
    if [ -z "$MSG" ]; then
        MSG="تحديث: $(date '+%Y-%m-%d %H:%M')"
    fi
fi

git commit -m "$MSG"
echo ""
echo "جاري الرفع..."
git push -u origin "$BRANCH"
echo ""
echo "تم رفع التعديلات بنجاح."
