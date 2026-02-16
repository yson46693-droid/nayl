/**
 * Purchase Course - شراء الكورس
 * يعرض بيانات المستخدم الحالي (الرصيد) ويُحمّلها من API
 * إذا وُجد id في الرابط (?id=) يُحمّل تفاصيل الكورس من API
 */
document.addEventListener('DOMContentLoaded', async function () {
    const sessionToken = localStorage.getItem('sessionToken') || sessionStorage.getItem('sessionToken');
    const coursePriceEl = document.getElementById('course-price');
    const purchaseBtn = document.getElementById('purchase-btn');
    const insufficientAlert = document.getElementById('insufficient-alert');
    const secondaryActions = document.getElementById('secondary-actions');
    const sidebarBalanceEl = document.getElementById('sidebar-balance');
    const remainingBalanceEl = document.getElementById('remaining-balance');
    const modalRemainingEl = document.getElementById('modal-remaining');
    const purchaseSuccessCard = document.getElementById('purchase-success-card');
    const purchasedBadge = document.getElementById('purchased-badge');
    const btnHaveCode = document.getElementById('btn-have-code');
    const btnWatchCourse = document.getElementById('btn-watch-course');
    const codeRedeemModal = document.getElementById('code-redeem-modal');
    const codeRedeemInput = document.getElementById('code-redeem-input');
    const codeRedeemError = document.getElementById('code-redeem-error');
    const btnRedeemCode = document.getElementById('btn-redeem-code');
    const codeRedeemClose = document.getElementById('code-redeem-close');
    const codeRedeemOverlay = document.querySelector('#code-redeem-modal .code-redeem-overlay');

    const urlParams = new URLSearchParams(window.location.search);
    const courseId = urlParams.get('id') ? parseInt(urlParams.get('id'), 10) : null;

    let userBalance = 0;
    let coursePrice = coursePriceEl ? parseFloat(coursePriceEl.textContent) || 500 : 500;
    let effectivePrice = coursePrice;
    let appliedDiscount = { amount: 0, code: '' };
    let hasSubscription = false;

    /**
     * التحقق من اشتراك المستخدم في الكورس الحالي
     * @returns {Promise<boolean>}
     */
    async function checkSubscription() {
        if (!sessionToken || !courseId || courseId < 1) return false;
        try {
            const response = await fetch('api/courses/check-subscription.php?id=' + courseId, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer ' + sessionToken
                },
                credentials: 'include'
            });
            const result = await response.json();
            if (response.ok && result.success && result.data && result.data.has_subscription) {
                return true;
            }
            return false;
        } catch (err) {
            console.error('Error checking subscription:', err);
            return false;
        }
    }

    /**
     * عرض/إخفاء واجهة الشراء أو علامة تم الشراء
     */
    function updatePurchaseUI() {
        if (hasSubscription) {
            if (purchasedBadge) purchasedBadge.style.display = 'flex';
            if (purchaseBtn) purchaseBtn.style.display = 'none';
            if (btnHaveCode) btnHaveCode.style.display = 'none';
            if (insufficientAlert) insufficientAlert.style.display = 'none';
            if (secondaryActions) secondaryActions.style.display = 'none';
        } else {
            if (purchasedBadge) purchasedBadge.style.display = 'none';
            if (purchaseBtn) purchaseBtn.style.display = 'flex';
            if (btnHaveCode) btnHaveCode.style.display = 'flex';
            checkBalance();
        }
    }

    /** حفظ موضع التمرير عند فتح المودال (لـ position:fixed على body) */
    let savedScrollY = 0;

    /**
     * فتح نافذة إدخال الكود
     */
    function openCodeModal() {
        if (codeRedeemModal) {
            savedScrollY = window.scrollY || window.pageYOffset;
            document.documentElement.classList.add('code-redeem-modal-open');
            document.body.classList.add('code-redeem-modal-open');
            document.body.style.top = savedScrollY ? `-${savedScrollY}px` : '';
            codeRedeemModal.style.display = 'flex';
        }
        if (codeRedeemInput) {
            codeRedeemInput.value = '';
            codeRedeemInput.focus();
        }
        if (codeRedeemError) {
            codeRedeemError.textContent = '';
            codeRedeemError.style.display = 'none';
        }
    }

    /**
     * إغلاق نافذة إدخال الكود
     */
    function closeCodeModal() {
        if (codeRedeemModal) {
            codeRedeemModal.style.display = 'none';
            document.documentElement.classList.remove('code-redeem-modal-open');
            document.body.classList.remove('code-redeem-modal-open');
            document.body.style.top = '';
            window.scrollTo(0, savedScrollY);
        }
    }

    /**
     * تفعيل الكود والتوجيه لصفحة المشاهدة
     */
    async function redeemCode() {
        const code = codeRedeemInput ? codeRedeemInput.value.trim() : '';
        if (!code) {
            if (codeRedeemError) {
                codeRedeemError.textContent = 'أدخل كود الكورس';
                codeRedeemError.style.display = 'block';
            }
            return;
        }
        if (!courseId || courseId < 1) {
            if (codeRedeemError) {
                codeRedeemError.textContent = 'معرف الكورس غير صحيح';
                codeRedeemError.style.display = 'block';
            }
            return;
        }
        if (btnRedeemCode) {
            btnRedeemCode.disabled = true;
            btnRedeemCode.innerHTML = '<i class="bi bi-hourglass-split"></i><span>جاري التفعيل...</span>';
        }
        if (codeRedeemError) {
            codeRedeemError.textContent = '';
            codeRedeemError.style.display = 'none';
        }
        try {
            const response = await fetch('api/courses/redeem-code.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer ' + (sessionToken || '')
                },
                credentials: 'include',
                body: JSON.stringify({ course_id: courseId, code: code })
            });
            const result = await response.json();
            if (response.ok && result.success && result.data) {
                closeCodeModal();
                const redirectUrl = result.data.redirect_url || 'course-detail.html?id=' + courseId;
                window.location.href = redirectUrl;
                return;
            }
            if (codeRedeemError) {
                codeRedeemError.textContent = result.error || result.message || 'الكود غير صحيح أو مستخدم';
                codeRedeemError.style.display = 'block';
            }
        } catch (err) {
            console.error('Redeem code error:', err);
            if (codeRedeemError) {
                codeRedeemError.textContent = 'حدث خطأ في الاتصال. حاول مرة أخرى.';
                codeRedeemError.style.display = 'block';
            }
        }
        if (btnRedeemCode) {
            btnRedeemCode.disabled = false;
            btnRedeemCode.innerHTML = '<i class="bi bi-check-lg"></i><span>تفعيل والمشاهدة</span>';
        }
    }

    /**
     * جلب رصيد المستخدم الحالي من API
     * @returns {Promise<number>} الرصيد أو 0 عند الفشل
     */
    async function fetchUserBalance() {
        if (!sessionToken) return 0;
        try {
            const response = await fetch('/api/wallet/get-balance.php', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer ' + sessionToken
                },
                credentials: 'include'
            });
            const result = await response.json();
            if (response.ok && result.success && result.data && result.data.balance !== undefined) {
                return parseFloat(result.data.balance) || 0;
            }
            return 0;
        } catch (err) {
            console.error('Error fetching wallet balance:', err);
            return 0;
        }
    }

    /**
     * تحديث عرض الرصيد والرصيد المتبقي في الواجهة
     */
    function updateBalanceDisplay() {
        if (sidebarBalanceEl) {
            sidebarBalanceEl.textContent = userBalance.toFixed(2) + ' ج.م';
        }
        const remaining = userBalance - effectivePrice;
        if (remainingBalanceEl) {
            remainingBalanceEl.textContent = remaining >= 0 ? remaining.toFixed(2) + ' ج.م' : 'غير كافٍ';
            remainingBalanceEl.style.color = remaining >= 0 ? '#4CAF50' : '#e74c3c';
        }
        if (modalRemainingEl) {
            modalRemainingEl.textContent = userBalance.toFixed(2) + ' ج.م';
        }
    }

    /**
     * التحقق من كفاية الرصيد وتفعيل/تعطيل زر الشراء
     */
    function updateDiscountUI() {
        const summaryTotal = document.getElementById('summary-total');
        const discountRow = document.getElementById('discount-summary-row');
        const summaryDiscount = document.getElementById('summary-discount');
        const discountMsg = document.getElementById('discount-code-message');
        if (summaryTotal) summaryTotal.textContent = effectivePrice.toFixed(2) + ' ج.م';
        if (discountRow) discountRow.style.display = appliedDiscount.amount > 0 ? 'flex' : 'none';
        if (summaryDiscount) summaryDiscount.textContent = appliedDiscount.amount > 0 ? '-' + appliedDiscount.amount.toFixed(2) + ' ج.م' : '0.00 ج.م';
        if (discountMsg) { discountMsg.style.display = 'none'; discountMsg.className = 'discount-code-message'; }
    }

    function checkBalance() {
        if (userBalance >= effectivePrice) {
            if (purchaseBtn) {
                purchaseBtn.disabled = false;
            }
            if (insufficientAlert) insufficientAlert.style.display = 'none';
            if (secondaryActions) secondaryActions.style.display = 'none';
            if (remainingBalanceEl) remainingBalanceEl.style.color = '#4CAF50';
        } else {
            if (purchaseBtn) purchaseBtn.disabled = true;
            if (insufficientAlert) insufficientAlert.style.display = 'flex';
            if (secondaryActions) secondaryActions.style.display = 'grid';
            if (remainingBalanceEl) remainingBalanceEl.style.color = '#e74c3c';
        }
    }

    function formatDuration(seconds) {
        if (!seconds || seconds <= 0) return '—';
        const hours = Math.floor(seconds / 3600);
        const mins = Math.floor((seconds % 3600) / 60);
        if (hours > 0 && mins > 0) return hours + ' ساعة و ' + mins + ' د';
        if (hours > 0) return hours + ' ساعة تدريبية';
        return mins + ' دقيقة';
    }

    if (courseId && courseId > 0) {
        try {
            const res = await fetch('api/courses/get.php?id=' + courseId, {
                method: 'GET',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include'
            });
            const result = await res.json();
            if (res.ok && result.success && result.data) {
                const c = result.data;
                const apiPrice = (c.price != null && c.price !== '') ? parseFloat(c.price) : 500;
                coursePrice = !isNaN(apiPrice) && apiPrice >= 0 ? apiPrice : 500;
                const titleEl = document.querySelector('.course-title');
                const descEl = document.querySelector('.course-description');
                const imgEl = document.querySelector('.course-image-container .course-image');
                const featureDuration = document.getElementById('feature-duration');
                const featureLessons = document.getElementById('feature-lessons');
                const summaryName = document.getElementById('summary-course-name');
                const summaryPrice = document.getElementById('summary-price');
                const summaryTotal = document.getElementById('summary-total');
                if (titleEl) titleEl.textContent = c.title;
                if (descEl) descEl.textContent = c.description || '';
                if (imgEl) {
                    imgEl.src = c.cover_image_url || 'pics/1.jpg';
                    imgEl.alt = c.title;
                }
                if (featureDuration) featureDuration.textContent = formatDuration(c.total_duration_seconds);
                if (featureLessons) featureLessons.textContent = (c.videos_count || 0) + ' درس فيديو';
                if (summaryName) summaryName.textContent = c.title;
                effectivePrice = coursePrice;
                appliedDiscount = { amount: 0, code: '' };
                const priceStr = coursePrice.toFixed(2) + ' ج.م';
                if (summaryPrice) summaryPrice.textContent = priceStr;
                if (summaryTotal) summaryTotal.textContent = priceStr;
                if (coursePriceEl) coursePriceEl.textContent = coursePrice.toFixed(2);
                updateDiscountUI();
            } else {
                window.location.href = 'courses.html';
                return;
            }
        } catch (err) {
            console.error('Error loading course:', err);
            window.location.href = 'courses.html';
            return;
        }
    }

    // عرض "جاري التحميل..." للرصيد حتى يتم الجلب
    if (sidebarBalanceEl) sidebarBalanceEl.textContent = 'جاري التحميل...';
    if (remainingBalanceEl) remainingBalanceEl.textContent = '—';

    // جلب رصيد المستخدم والتحقق من الاشتراك
    userBalance = await fetchUserBalance();
    hasSubscription = await checkSubscription();
    effectivePrice = coursePrice;
    updateDiscountUI();
    updateBalanceDisplay();
    checkBalance();
    updatePurchaseUI();

    // معالج زر الشراء
    if (purchaseBtn) {
        purchaseBtn.addEventListener('click', function () {
            if (userBalance >= effectivePrice) {
                processPurchase();
            }
        });
    }

    // تطبيق كود الخصم
    const discountCodeInput = document.getElementById('discount-code-input');
    const discountApplyBtn = document.getElementById('discount-apply-btn');
    const discountCodeMessage = document.getElementById('discount-code-message');
    if (discountApplyBtn && discountCodeInput) {
        discountApplyBtn.addEventListener('click', async function () {
            const code = discountCodeInput.value.trim();
            if (!code) {
                appliedDiscount = { amount: 0, code: '' };
                effectivePrice = coursePrice;
                updateDiscountUI();
                updateBalanceDisplay();
                checkBalance();
                if (discountCodeMessage) {
                    discountCodeMessage.textContent = 'تم إلغاء الخصم.';
                    discountCodeMessage.className = 'discount-code-message success';
                    discountCodeMessage.style.display = 'block';
                }
                return;
            }
            discountApplyBtn.disabled = true;
            if (discountCodeMessage) { discountCodeMessage.style.display = 'none'; discountCodeMessage.className = 'discount-code-message'; }
            try {
                const response = await fetch('api/courses/validate-discount.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': 'Bearer ' + (sessionToken || '')
                    },
                    credentials: 'include',
                    body: JSON.stringify({ code: code, course_id: courseId })
                });
                const result = await response.json();
                if (response.ok && result.success && result.data) {
                    appliedDiscount = { amount: result.data.discount_amount, code: code };
                    effectivePrice = result.data.final_price;
                    updateDiscountUI();
                    updateBalanceDisplay();
                    checkBalance();
                    if (discountCodeMessage) {
                        discountCodeMessage.textContent = 'تم تطبيق الخصم: -' + result.data.discount_amount.toFixed(2) + ' ج.م';
                        discountCodeMessage.className = 'discount-code-message success';
                        discountCodeMessage.style.display = 'block';
                    }
                } else {
                    appliedDiscount = { amount: 0, code: '' };
                    effectivePrice = coursePrice;
                    updateDiscountUI();
                    updateBalanceDisplay();
                    checkBalance();
                    if (discountCodeMessage) {
                        discountCodeMessage.textContent = result.error || result.message || 'كود الخصم غير صحيح أو مستخدم.';
                        discountCodeMessage.className = 'discount-code-message error';
                        discountCodeMessage.style.display = 'block';
                    }
                }
            } catch (err) {
                console.error('Validate discount error:', err);
                if (discountCodeMessage) {
                    discountCodeMessage.textContent = 'حدث خطأ في الاتصال. حاول مرة أخرى.';
                    discountCodeMessage.className = 'discount-code-message error';
                    discountCodeMessage.style.display = 'block';
                }
            }
            discountApplyBtn.disabled = false;
        });
    }

    // زر "هل لديك كود للمشاهدة؟"
    if (btnHaveCode) {
        btnHaveCode.addEventListener('click', openCodeModal);
    }

    // زر المشاهدة الآن (للمشتركين)
    if (btnWatchCourse && courseId) {
        btnWatchCourse.href = 'course-detail.html?id=' + courseId;
    }

    // نافذة إدخال الكود
    if (btnRedeemCode) btnRedeemCode.addEventListener('click', redeemCode);
    if (codeRedeemClose) codeRedeemClose.addEventListener('click', closeCodeModal);
    if (codeRedeemOverlay) codeRedeemOverlay.addEventListener('click', closeCodeModal);
    if (codeRedeemInput) {
        codeRedeemInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                redeemCode();
            }
        });
    }

    /**
     * تنفيذ عملية الشراء عبر API (خصم الرصيد، إنشاء كود مرتبط بالحساب، اشتراك)
     */
    async function processPurchase() {
        if (!purchaseBtn || !courseId || courseId < 1) {
            alert('معرف الكورس غير صحيح.');
            return;
        }
        const loadingOverlay = document.getElementById('purchase-loading-overlay');
        if (loadingOverlay) loadingOverlay.style.display = 'flex';
        purchaseBtn.innerHTML = '<i class="bi bi-hourglass-split"></i><span>جاري المعالجة...</span>';
        purchaseBtn.disabled = true;

        try {
            const response = await fetch('api/courses/purchase.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer ' + (sessionToken || '')
                },
                credentials: 'include',
                body: JSON.stringify({
                    course_id: courseId,
                    discount_code: appliedDiscount.code || undefined
                })
            });
            const result = await response.json();

            if (response.ok && result.success && result.data) {
                userBalance = await fetchUserBalance();
                hasSubscription = true;
                updateBalanceDisplay();
                checkBalance();
                updatePurchaseUI();

                var codeEl = document.getElementById('purchase-card-code-value');
                var titleEl = document.getElementById('purchase-card-course-title');
                if (codeEl) codeEl.textContent = result.data.code || '—';
                if (titleEl) titleEl.textContent = result.data.course_title || '—';

                if (loadingOverlay) loadingOverlay.style.display = 'none';
                if (purchaseSuccessCard) {
                    if (purchasedBadge) purchasedBadge.style.display = 'none';
                    if (purchaseBtn) purchaseBtn.style.display = 'none';
                    if (btnHaveCode) btnHaveCode.style.display = 'none';
                    if (secondaryActions) secondaryActions.style.display = 'none';
                    purchaseSuccessCard.style.display = 'block';
                    purchaseSuccessCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    requestAnimationFrame(function () {
                        var btn = document.getElementById('btn-copy-purchase-code');
                        if (btn) btn.focus({ preventScroll: true });
                    });
                }
                purchaseBtn.innerHTML = '<i class="bi bi-check-circle"></i><span>تم الشراء</span>';
                purchaseBtn.style.background = '#4CAF50';
            } else {
                if (loadingOverlay) loadingOverlay.style.display = 'none';
                purchaseBtn.disabled = false;
                purchaseBtn.innerHTML = '<i class="bi bi-cart-check"></i><span>شراء الكورس الآن</span>';
                alert(result.error || result.message || 'فشلت عملية الشراء.');
            }
        } catch (err) {
            console.error('Purchase error:', err);
            if (loadingOverlay) loadingOverlay.style.display = 'none';
            purchaseBtn.disabled = false;
            purchaseBtn.innerHTML = '<i class="bi bi-cart-check"></i><span>شراء الكورس الآن</span>';
            alert('حدث خطأ في الاتصال. حاول مرة أخرى.');
        }
    }

    // إغلاق بطاقة النجاح وعرض زر المشاهدة
    function closePurchaseSuccessCard() {
        if (purchaseSuccessCard) {
            purchaseSuccessCard.style.display = 'none';
        }
        if (purchasedBadge) purchasedBadge.style.display = 'flex';
    }

    var btnCloseCard = document.getElementById('btn-close-purchase-card');
    if (btnCloseCard) {
        btnCloseCard.addEventListener('click', closePurchaseSuccessCard);
    }

    // نسخ كود الوصول
    var btnCopyCode = document.getElementById('btn-copy-purchase-code');
    if (btnCopyCode) {
        btnCopyCode.addEventListener('click', function () {
            var codeEl = document.getElementById('purchase-card-code-value');
            var code = codeEl ? codeEl.textContent.trim() : '';
            if (!code || code === '—') return;
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(code).then(function () {
                    btnCopyCode.innerHTML = '<i class="bi bi-check"></i> تم النسخ';
                    setTimeout(function () {
                        btnCopyCode.innerHTML = '<i class="bi bi-clipboard"></i> نسخ الكود';
                    }, 2000);
                });
            } else {
                var ta = document.createElement('textarea');
                ta.value = code;
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
                btnCopyCode.innerHTML = '<i class="bi bi-check"></i> تم النسخ';
                setTimeout(function () {
                    btnCopyCode.innerHTML = '<i class="bi bi-clipboard"></i> نسخ الكود';
                }, 2000);
            }
        });
    }

    // واتساب الدعم - استخدام اسم الكورس من الصفحة إن وُجد
    window.openWhatsAppSupport = function () {
        const courseTitleEl = document.querySelector('.course-title');
        const courseName = courseTitleEl ? courseTitleEl.textContent.trim() : 'كورس';
        const message = encodeURIComponent('مرحباً، أحتاج مساعدة في شراء كورس "' + courseName + '"');
        const whatsappNumbers = ['+201060631033', '+201226550201'];
        window.open('https://wa.me/' + whatsappNumbers[0] + '?text=' + message, '_blank');
    };

    // للتحديث اليدوي للرصيد (مثلاً بعد العودة من صفحة الشحن)
    window.updateBalance = async function () {
        userBalance = await fetchUserBalance();
        updateBalanceDisplay();
        checkBalance();
    };
});
