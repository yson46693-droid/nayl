// ===========================
// Recharge Requests Management
// ===========================

// Mock recharge requests data (Generated for pagination demo)
let rechargeRequests = [];
const generateMockData = () => {
    const methods = [
        { name: 'Vodafone Cash', icon: 'bi-phone-fill', color: '#e60000' },
        { name: 'InstaPay', icon: 'bi-bank', color: '#00a19c' },
        { name: 'Orange Cash', icon: 'bi-phone', color: '#f37021' }
    ];

    for (let i = 1; i <= 18; i++) {
        const method = methods[Math.floor(Math.random() * methods.length)];
        const isPending = i <= 12;
        rechargeRequests.push({
            id: i,
            userId: 100 + i,
            userName: isPending ? `مستخدم ${i}` : `مستخدم ${i} (تم)`,
            userPhone: `010${Math.floor(Math.random() * 90000000 + 10000000)}`,
            amount: Math.floor(Math.random() * 500) + 50,
            paymentMethod: method.name,
            paymentIcon: method.icon,
            paymentColor: method.color,
            transferImage: `../pics/transfer${(i % 3) + 1}.jpg`,
            date: '22/01/2026',
            time: `${Math.floor(Math.random() * 12 + 1)}:${Math.floor(Math.random() * 59)} م`,
            status: isPending ? 'pending' : (i % 2 === 0 ? 'approved' : 'rejected'),
            submittedAt: new Date(Date.now() - Math.floor(Math.random() * 100000000)).toISOString()
        });
    }
};

// Current State
let rechargeCurrentPage = 1;
const rechargeItemsPerPage = 10;
let currentRechargeFilter = 'all';
let currentRequestId = null;

// Initialize
function initRechargeRequests() {
    const stored = localStorage.getItem('nayl_recharge_requests');
    if (stored) {
        rechargeRequests = JSON.parse(stored);
        // Force refresh mock data if empty (for demo purpose)
        if (rechargeRequests.length < 2) {
            rechargeRequests = [];
            generateMockData();
            localStorage.setItem('nayl_recharge_requests', JSON.stringify(rechargeRequests));
        }
    } else {
        generateMockData();
        localStorage.setItem('nayl_recharge_requests', JSON.stringify(rechargeRequests));
    }
}

// Render Table
function renderRechargeRequests(filter = currentRechargeFilter) {
    const tbody = document.getElementById('rechargeRequestsTableBody');
    if (!tbody) return;

    currentRechargeFilter = filter;
    let filtered = rechargeRequests;

    if (filter === 'pending') {
        filtered = rechargeRequests.filter(r => r.status === 'pending');
    }

    // Sort: Pending first, then by date desc
    filtered.sort((a, b) => {
        if (a.status === 'pending' && b.status !== 'pending') return -1;
        if (a.status !== 'pending' && b.status === 'pending') return 1;
        return new Date(b.submittedAt) - new Date(a.submittedAt);
    });

    const totalPages = Math.ceil(filtered.length / rechargeItemsPerPage);
    if (rechargeCurrentPage > totalPages && totalPages > 0) rechargeCurrentPage = totalPages;
    const itemsToShow = totalPages > 0 ? filtered.slice((rechargeCurrentPage - 1) * rechargeItemsPerPage, rechargeCurrentPage * rechargeItemsPerPage) : [];

    tbody.innerHTML = '';

    if (itemsToShow.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; padding: 40px; color: var(--text-gray);">لا توجد طلبات حالياً</td></tr>';
        renderRechargePagination(0);
        return;
    }

    itemsToShow.forEach(req => {
        const badge = req.status === 'pending' ? 'badge-pending' : (req.status === 'approved' ? 'badge-active' : 'badge-expired');
        const statusText = req.status === 'pending' ? 'قيد المراجعة' : (req.status === 'approved' ? 'مقبول' : 'مرفوض');

        const row = document.createElement('tr');
        row.innerHTML = `
            <td><span style="font-family: monospace; font-weight: bold; color: var(--primary-dark);">#${req.id}</span></td>
            <td>
                <div class="user-cell">
                    <div class="user-cell-avatar" style="background: ${stringToColor(req.userName)}">${req.userName.charAt(0)}</div>
                    <span>${req.userName}</span>
                </div>
            </td>
            <td>${req.userPhone}</td>
            <td><strong style="color: var(--accent-green);">EGP ${req.amount}</strong></td>
            <td>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <i class="${req.paymentIcon}" style="color: ${req.paymentColor};"></i>
                    <span>${req.paymentMethod}</span>
                </div>
            </td>
            <td>${req.date} ${req.time}</td>
            <td><span class="badge ${badge}">${statusText}</span></td>
            <td>
                <div class="actions-cell" style="justify-content: center; gap: 8px;">
                    <button class="action-btn btn-view" onclick="viewTransferImage('${req.transferImage}')" title="عرض الإيصال">
                        <i class="bi bi-image-fill"></i>
                    </button>
                    ${req.status === 'pending' ? `
                        <button class="btn btn-primary btn-with-icon" style="padding: 4px 10px; font-size: 0.8rem;" onclick="approveRecharge(${req.id})">
                            <i class="bi bi-check-lg"></i>
                            <span>قبول</span>
                        </button>
                        <button class="btn btn-delete btn-with-icon" style="padding: 4px 10px; font-size: 0.8rem;" onclick="rejectRecharge(${req.id})">
                            <i class="bi bi-x-lg"></i>
                            <span>رفض</span>
                        </button>
                    ` : ''}
                </div>
            </td>
        `;
        tbody.appendChild(row);
    });

    renderRechargePagination(totalPages);
    updateRechargeStats();
}

function renderRechargePagination(totalPages) {
    const container = document.getElementById('rechargeRequestsPagination');
    if (!container) return;

    if (totalPages <= 1) {
        container.innerHTML = '';
        return;
    }

    // Calculate items info
    const filtered = currentRechargeFilter === 'pending'
        ? rechargeRequests.filter(r => r.status === 'pending')
        : rechargeRequests;
    const startItem = (rechargeCurrentPage - 1) * rechargeItemsPerPage + 1;
    const endItem = Math.min(rechargeCurrentPage * rechargeItemsPerPage, filtered.length);

    let html = `
        <div class="pagination-info">
            عرض ${startItem} إلى ${endItem} من ${filtered.length} طلب
        </div>
        <div class="pagination-buttons">
    `;

    // Previous button
    html += `<button class="pagination-btn" ${rechargeCurrentPage === 1 ? 'disabled' : ''} onclick="changeRechargePage(${rechargeCurrentPage - 1})">
        <i class="bi bi-chevron-right"></i>
    </button>`;

    // Page numbers with smart display
    const maxVisiblePages = 5;
    let startPage = Math.max(1, rechargeCurrentPage - Math.floor(maxVisiblePages / 2));
    let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);

    // Adjust start if we're near the end
    if (endPage - startPage < maxVisiblePages - 1) {
        startPage = Math.max(1, endPage - maxVisiblePages + 1);
    }

    // First page if not visible
    if (startPage > 1) {
        html += `<button class="pagination-btn" onclick="changeRechargePage(1)">1</button>`;
        if (startPage > 2) {
            html += `<span style="padding: 8px; color: var(--text-gray);">...</span>`;
        }
    }

    // Page numbers
    for (let i = startPage; i <= endPage; i++) {
        html += `<button class="pagination-btn ${rechargeCurrentPage === i ? 'active' : ''}" onclick="changeRechargePage(${i})">${i}</button>`;
    }

    // Last page if not visible
    if (endPage < totalPages) {
        if (endPage < totalPages - 1) {
            html += `<span style="padding: 8px; color: var(--text-gray);">...</span>`;
        }
        html += `<button class="pagination-btn" onclick="changeRechargePage(${totalPages})">${totalPages}</button>`;
    }

    // Next button
    html += `<button class="pagination-btn" ${rechargeCurrentPage === totalPages ? 'disabled' : ''} onclick="changeRechargePage(${rechargeCurrentPage + 1})">
        <i class="bi bi-chevron-left"></i>
    </button>`;

    html += `</div>`;

    container.innerHTML = html;
}

window.changeRechargePage = function (page) {
    if (page < 1) return;
    rechargeCurrentPage = page;
    renderRechargeRequests();
};

window.filterRechargeRequests = function (filter) {
    rechargeCurrentPage = 1;
    renderRechargeRequests(filter);
};

// Helper for avatar colors
function stringToColor(str) {
    let hash = 0;
    for (let i = 0; i < str.length; i++) {
        hash = str.charCodeAt(i) + ((hash << 5) - hash);
    }
    const c = (hash & 0x00FFFFFF).toString(16).toUpperCase();
    return '#' + '00000'.substring(0, 6 - c.length) + c;
}

// Stats
function updateRechargeStats() {
    const pendingCount = rechargeRequests.filter(r => r.status === 'pending').length;
    const today = new Date().toLocaleDateString('en-GB');
    const approvedToday = rechargeRequests.filter(r => r.status === 'approved' && r.date === today).length;

    const pEl = document.getElementById('pendingRechargeCount');
    const aEl = document.getElementById('approvedTodayCount');

    if (pEl) pEl.textContent = pendingCount;
    if (aEl) aEl.textContent = approvedToday;
}

// Modal Actions - Fixed to ensure modals display correctly
function showModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        // Force display and visibility
        modal.style.display = 'flex';
        modal.style.opacity = '1';
        modal.style.visibility = 'visible';

        // Ensure child modal is visible with proper animation
        const modalContent = modal.querySelector('.modal, .modal-large');
        if (modalContent) {
            modalContent.style.opacity = '1';
            modalContent.style.transform = 'translateY(0)';
            modalContent.style.visibility = 'visible';
        }

        // Prevent body scroll when modal is open
        document.body.style.overflow = 'hidden';
    }
}

function hideModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
        modal.style.opacity = '0';
        modal.style.visibility = 'hidden';
    }
    currentRequestId = null;

    // Restore body scroll
    document.body.style.overflow = '';
}

window.viewTransferImage = function (src) {
    if (!src) {
        console.error('No image source provided');
        return;
    }

    // Force scroll to top instantly
    try {
        window.scrollTo(0, 0);
        document.documentElement.scrollTop = 0;
        document.body.scrollTop = 0;
    } catch (e) {
        console.error('Error scrolling to top:', e);
    }

    // Lock scroll on both body and html to contain user
    document.body.style.overflow = 'hidden';
    document.documentElement.style.overflow = 'hidden';

    // Handle relative path if needed
    const finalUrl = src.startsWith('../') ? src : '../' + src;

    // Create enhanced modal dynamically
    const modal = document.createElement('div');
    modal.className = 'receipt-modal';
    modal.id = 'dynamicReceiptModal';
    modal.innerHTML = `
        <div class="receipt-modal-overlay"></div>
        <div class="receipt-modal-content enhanced-modal">
            <div class="receipt-modal-header">
                <h3 class="receipt-title">
                    <i class="bi bi-receipt"></i>
                    إيصال التحويل
                </h3>
                <div class="receipt-actions">
                    <a href="${finalUrl}" download="receipt.jpg" class="receipt-btn download-btn" target="_blank">
                        <i class="bi bi-download"></i>
                        <span>تحميل</span>
                    </a>
                    <button class="receipt-btn close-btn receipt-modal-close">
                        <i class="bi bi-x-lg"></i>
                        <span>إغلاق</span>
                    </button>
                </div>
            </div>
            <div class="receipt-image-container">
                <img src="${finalUrl}" alt="إيصال التحويل" class="receipt-image">
            </div>
        </div>
    `;

    document.body.appendChild(modal);

    // Ensure modal itself is scrolled to top (if content is long)
    try {
        modal.scrollTop = 0;
    } catch (e) { }

    // Close logic
    const closeBtn = modal.querySelector('.receipt-modal-close');
    const overlay = modal.querySelector('.receipt-modal-overlay');

    const closeModal = () => {
        document.body.removeChild(modal);
        // Restore scroll
        document.body.style.overflow = '';
        document.documentElement.style.overflow = '';
    };

    closeBtn.addEventListener('click', closeModal);
    overlay.addEventListener('click', closeModal);
};

window.closeTransferImageModal = function () {
    const modal = document.getElementById('dynamicReceiptModal');
    if (modal) {
        document.body.removeChild(modal);
        document.body.style.overflow = '';
        document.documentElement.style.overflow = '';
    }

    // Legacy cleanup
    const oldModal = document.getElementById('receiptModal');
    if (oldModal) oldModal.style.display = 'none';
};

window.approveRecharge = function (id) {
    const req = rechargeRequests.find(r => r.id == id);
    if (!req) return;

    currentRequestId = id;
    document.getElementById('approveUserName').textContent = req.userName;
    document.getElementById('approveOriginalAmount').textContent = req.amount;
    document.getElementById('approveAmount').value = req.amount;

    showModal('approveRechargeModal');
};

window.closeApproveModal = function () {
    hideModal('approveRechargeModal');
};

window.confirmApproveRecharge = function () {
    const amount = parseFloat(document.getElementById('approveAmount').value);
    if (!amount || amount <= 0) return alert('يرجى إدخال مبلغ صحيح');

    const req = rechargeRequests.find(r => r.id == currentRequestId);
    if (req) {
        req.status = 'approved';
        req.approvedAmount = amount;

        // Update user wallet
        const users = JSON.parse(localStorage.getItem('nayl_users')) || [];
        const user = users.find(u => u.id == req.userId);
        if (user) {
            user.wallet = (user.wallet || 0) + amount;
            localStorage.setItem('nayl_users', JSON.stringify(users));
        }

        localStorage.setItem('nayl_recharge_requests', JSON.stringify(rechargeRequests));
        closeApproveModal();
        renderRechargeRequests();
        alert('تم قبول الطلب وتحديث الرصيد ✓');
    }
};

window.rejectRecharge = function (id) {
    const req = rechargeRequests.find(r => r.id == id);
    if (!req) return;

    currentRequestId = id;
    document.getElementById('rejectUserName').textContent = req.userName;
    document.getElementById('rejectAmount').textContent = req.amount;
    document.getElementById('rejectionReason').value = '';

    showModal('rejectRechargeModal');
};

window.closeRejectModal = function () {
    hideModal('rejectRechargeModal');
};

window.confirmRejectRecharge = function () {
    const reason = document.getElementById('rejectionReason').value.trim();
    if (!reason) return alert('يرجى ذكر سبب الرفض');

    const req = rechargeRequests.find(r => r.id == currentRequestId);
    if (req) {
        req.status = 'rejected';
        req.rejectionReason = reason;

        localStorage.setItem('nayl_recharge_requests', JSON.stringify(rechargeRequests));
        closeRejectModal();
        renderRechargeRequests();
        alert('تم رفض الطلب بنجاح');
    }
};

// Auto Init
document.addEventListener('DOMContentLoaded', () => {
    window.initRechargeRequests();

    // Initial render if this tab is already active
    const activeLink = document.querySelector('.menu-link.active');
    if (activeLink && activeLink.getAttribute('data-tab') === 'recharge-requests') {
        window.renderRechargeRequests();
    }

    // Monitor tab switching for all links
    document.body.addEventListener('click', (e) => {
        const link = e.target.closest('.menu-link');
        if (link && link.getAttribute('data-tab') === 'recharge-requests') {
            rechargeCurrentPage = 1;
            setTimeout(() => {
                window.renderRechargeRequests();
            }, 50);
        }
    });
});

// Export functions to window
window.initRechargeRequests = initRechargeRequests;
window.renderRechargeRequests = renderRechargeRequests;
window.updateRechargeStats = updateRechargeStats;
window.renderRechargePagination = renderRechargePagination;

// Image Control Functions
let currentZoom = 1;
const zoomStep = 0.2;
const minZoom = 0.5;
const maxZoom = 3;

window.zoomIn = function () {
    const img = document.getElementById('transferImage');
    if (!img) return;

    currentZoom = Math.min(currentZoom + zoomStep, maxZoom);
    img.style.transform = `scale(${currentZoom})`;
};

window.zoomOut = function () {
    const img = document.getElementById('transferImage');
    if (!img) return;

    currentZoom = Math.max(currentZoom - zoomStep, minZoom);
    img.style.transform = `scale(${currentZoom})`;
};

window.resetZoom = function () {
    const img = document.getElementById('transferImage');
    if (!img) return;

    currentZoom = 1;
    img.style.transform = 'scale(1)';
};

window.toggleImageZoom = function () {
    const img = document.getElementById('transferImage');
    if (!img) return;

    if (currentZoom === 1) {
        currentZoom = 2;
    } else {
        currentZoom = 1;
    }
    img.style.transform = `scale(${currentZoom})`;
};

window.downloadImage = function () {
    const img = document.getElementById('transferImage');
    if (!img || !img.src) return;

    const link = document.createElement('a');
    link.href = img.src;
    link.download = 'transfer-receipt-' + Date.now() + '.jpg';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
};

// Reset zoom when modal closes
const originalCloseTransferImageModal = window.closeTransferImageModal;
window.closeTransferImageModal = function () {
    resetZoom();
    if (originalCloseTransferImageModal) {
        originalCloseTransferImageModal();
    } else {
        hideModal('transferImageModal');
    }
};

// Click Outside to Close
window.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.style.display = 'none';
        currentRequestId = null;
        resetZoom();
    }
});
