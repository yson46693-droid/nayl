function openSupportModal() {
    const modal = document.getElementById('support-modal');
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeSupportModal() {
    const modal = document.getElementById('support-modal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

document.addEventListener('DOMContentLoaded', function () {
    // Event listener for opening the modal (if buttons are added dynamically or efficiently)
    document.querySelectorAll('.open-support-modal').forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            openSupportModal();
        });
    });

    // Close on overlay click
    const overlay = document.querySelector('.support-modal-overlay');
    if (overlay) {
        overlay.addEventListener('click', closeSupportModal);
    }

    // Close on X button click
    const closeBtn = document.querySelector('.support-modal-close');
    if (closeBtn) {
        closeBtn.addEventListener('click', closeSupportModal);
    }

    // Close on Escape key
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeSupportModal();
        }
    });
});
