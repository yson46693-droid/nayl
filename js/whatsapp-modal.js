// وظيفة المودال للتواصل عبر واتساب
document.addEventListener('DOMContentLoaded', function () {
    const withdrawBtn = document.getElementById('withdraw-btn');
    const modal = document.getElementById('whatsapp-modal');
    const modalOverlay = document.getElementById('modal-overlay');
    const modalClose = document.getElementById('modal-close');

    // فتح المودال عند الضغط على زر سحب الرصيد
    if (withdrawBtn) {
        withdrawBtn.addEventListener('click', function () {
            modal.classList.add('active');
            document.body.style.overflow = 'hidden'; // منع التمرير في الخلفية
        });
    }

    // إغلاق المودال عند الضغط على زر الإغلاق
    if (modalClose) {
        modalClose.addEventListener('click', function () {
            closeModal();
        });
    }

    // إغلاق المودال عند الضغط على الخلفية
    if (modalOverlay) {
        modalOverlay.addEventListener('click', function () {
            closeModal();
        });
    }

    // إغلاق المودال عند الضغط على مفتاح Escape
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('active')) {
            closeModal();
        }
    });

    // وظيفة إغلاق المودال
    function closeModal() {
        modal.classList.remove('active');
        document.body.style.overflow = ''; // استعادة التمرير
    }
});
