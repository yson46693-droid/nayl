// Wallet Transactions with Pagination
let transactionsData = [];
let currentPage = 1;
const itemsPerPage = 3;
let filteredTransactions = [];
let totalPages = 0;

/**
 * الحصول على الرابط الأساسي لـ API (يدعم التشغيل من مسار فرعي)
 */
function getWalletApiUrl(path) {
    const base = (typeof window.API_BASE !== 'undefined' && window.API_BASE) ? window.API_BASE : '';
    return base + '/api/wallet/' + path;
}

/**
 * الحصول على session token
 */
function getSessionToken() {
    return localStorage.getItem('sessionToken') || sessionStorage.getItem('sessionToken');
}

/**
 * جلب رصيد المحفظة من API
 */
async function fetchWalletBalance() {
    const sessionToken = getSessionToken();
    
    if (!sessionToken) {
        console.error('No session token found');
        return '0.00';
    }
    
    try {
        const response = await fetch(getWalletApiUrl('get-balance.php') + '?_=' + Date.now(), {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${sessionToken}`
            },
            credentials: 'include'
        });
        const contentType = response.headers.get('content-type') || '';
        if (!contentType.includes('application/json')) {
            console.error('Error fetching balance: server returned non-JSON (e.g. 404). Check API path / app-base-path.');
            return '0.00';
        }
        const result = await response.json();
        if (response.ok && result.success && result.data != null) {
            return result.data.balance != null ? result.data.balance : '0.00';
        }
        console.error('Error fetching balance:', result.error || response.status);
        return '0.00';
    } catch (error) {
        console.error('Error fetching wallet balance:', error);
        return '0.00';
    }
}

/**
 * جلب معاملات المحفظة من API
 */
async function fetchWalletTransactions(page = 1, type = 'all') {
    const sessionToken = getSessionToken();
    
    if (!sessionToken) {
        console.error('No session token found');
        return [];
    }
    
    try {
        const url = getWalletApiUrl('get-transactions.php') + `?page=${page}&limit=${itemsPerPage}&type=${type}`;
        const response = await fetch(url, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${sessionToken}`
            },
            credentials: 'include'
        });
        const contentType = response.headers.get('content-type') || '';
        if (!contentType.includes('application/json')) {
            console.error('Error fetching transactions: server returned non-JSON (e.g. 404). Check API path / app-base-path.');
            return [];
        }
        const result = await response.json();
        if (response.ok && result.success && result.data) {
            totalPages = (result.data.pagination && result.data.pagination.total_pages) ? result.data.pagination.total_pages : 0;
            return Array.isArray(result.data.transactions) ? result.data.transactions : [];
        }
        console.error('Error fetching transactions:', result.error || response.status);
        return [];
    } catch (error) {
        console.error('Error fetching wallet transactions:', error);
        return [];
    }
}

// Initialize wallet balance
async function initWalletPremium() {
    const balanceElement = document.getElementById('wallet-balance');
    
    if (balanceElement) {
        // إظهار حالة التحميل
        balanceElement.textContent = '...';
        
        // جلب الرصيد من API
        const balance = await fetchWalletBalance();
        balanceElement.textContent = balance;
        
        // إزالة أي بيانات افتراضية من localStorage
        localStorage.removeItem('walletBalance');
    }
}

// Render transactions for current page
async function renderTransactions() {
    const container = document.getElementById('transactions-container');
    if (!container) return;

    // إظهار حالة التحميل
    container.innerHTML = `
        <div class="transactions-loading" style="text-align: center; padding: 2rem; color: #8b95a5;">
            <svg width="40" height="40" viewBox="0 0 40 40" fill="none" style="animation: spin 1s linear infinite; margin: 0 auto;">
                <circle cx="20" cy="20" r="18" stroke="currentColor" stroke-width="3" stroke-dasharray="50" stroke-dashoffset="25" opacity="0.3"/>
                <circle cx="20" cy="20" r="18" stroke="currentColor" stroke-width="3" stroke-dasharray="50" stroke-dashoffset="25" stroke-linecap="round"/>
            </svg>
            <p style="margin-top: 1rem;">جاري تحميل المعاملات...</p>
        </div>
    `;

    // جلب المعاملات من API
    const filterType = document.getElementById('transaction-filter')?.value || 'all';
    const pageTransactions = await fetchWalletTransactions(currentPage, filterType);
    
    if (pageTransactions.length === 0) {
        container.innerHTML = `
            <div class="transactions-empty">
                <svg width="80" height="80" viewBox="0 0 80 80" fill="none">
                    <circle cx="40" cy="40" r="35" stroke="currentColor" stroke-width="2" />
                    <path d="M40 25V45M40 55V55.1" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
                </svg>
                <h4>لا توجد معاملات</h4>
                <p>لم يتم العثور على معاملات تطابق الفلتر المحدد</p>
            </div>
        `;
        return;
    }

    container.innerHTML = pageTransactions.map(transaction => `
        <div class="transaction-item-premium ${transaction.type}">
            <div class="transaction-icon-premium">
                ${transaction.type === 'credit' ? `
                    <svg width="28" height="28" viewBox="0 0 28 28" fill="none">
                        <circle cx="14" cy="14" r="12" stroke="currentColor" stroke-width="2.5" />
                        <path d="M14 9V19M9 14H19" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" />
                    </svg>
                ` : `
                    <svg width="28" height="28" viewBox="0 0 28 28" fill="none">
                        <rect x="4" y="8" width="20" height="14" rx="2" stroke="currentColor" stroke-width="2.5" />
                        <path d="M4 12H24" stroke="currentColor" stroke-width="2.5" />
                        <circle cx="18" cy="17" r="1.5" fill="currentColor" />
                    </svg>
                `}
            </div>
            <div class="transaction-details-premium">
                <h4 class="transaction-title-premium">${transaction.title}</h4>
                <p class="transaction-date-premium">
                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none" style="opacity: 0.6;">
                        <circle cx="7" cy="7" r="6" stroke="currentColor" stroke-width="1.5" />
                        <path d="M7 3.5V7L9.5 9.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                    </svg>
                    ${transaction.date}
                </p>
            </div>
            <div class="transaction-amount-premium ${transaction.type}">
                ${transaction.type === 'credit' ? '+' : '-'}${parseFloat(transaction.amount).toFixed(2)} ج.م
            </div>
        </div>
    `).join('');
}

// Render pagination
function renderPagination() {
    const paginationNumbers = document.getElementById('pagination-numbers');
    const prevBtn = document.getElementById('prev-page');
    const nextBtn = document.getElementById('next-page');

    if (!paginationNumbers || !prevBtn || !nextBtn) return;

    // Update buttons state
    prevBtn.disabled = currentPage === 1;
    nextBtn.disabled = currentPage === totalPages || totalPages === 0;

    // Render page numbers
    paginationNumbers.innerHTML = '';
    for (let i = 1; i <= totalPages; i++) {
        const pageBtn = document.createElement('button');
        pageBtn.className = `pagination-number ${i === currentPage ? 'active' : ''}`;
        pageBtn.textContent = i;
        pageBtn.addEventListener('click', async () => {
            currentPage = i;
            await renderTransactions();
            renderPagination();
        });
        paginationNumbers.appendChild(pageBtn);
    }
}

// Filter transactions
async function filterTransactions(type) {
    currentPage = 1;
    await renderTransactions();
    renderPagination();
}

// Initialize pagination
async function initPagination() {
    const prevBtn = document.getElementById('prev-page');
    const nextBtn = document.getElementById('next-page');
    const filterSelect = document.getElementById('transaction-filter');

    if (prevBtn) {
        prevBtn.addEventListener('click', async () => {
            if (currentPage > 1) {
                currentPage--;
                await renderTransactions();
                renderPagination();
            }
        });
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', async () => {
            if (currentPage < totalPages) {
                currentPage++;
                await renderTransactions();
                renderPagination();
            }
        });
    }

    if (filterSelect) {
        filterSelect.addEventListener('change', async (e) => {
            await filterTransactions(e.target.value);
        });
    }

    await renderTransactions();
    renderPagination();
}

// Update wallet button handlers for Egyptian Pound
function initWalletButtons() {
    const rechargeBtn = document.getElementById('recharge-btn');
    const withdrawBtn = document.querySelector('.btn-withdraw-premium');
    const transferBtn = document.querySelector('.btn-transfer-premium');

    if (rechargeBtn) {
        rechargeBtn.addEventListener('click', function () {
            // التوجيه إلى صفحة الشحن
            window.location.href = 'recharge.html';
        });
    }

    if (withdrawBtn) {
        withdrawBtn.addEventListener('click', async function () {
            // جلب الرصيد الحالي
            const currentBalance = parseFloat(await fetchWalletBalance());
            const amount = prompt(`الرصيد المتاح: ${currentBalance.toFixed(2)} ج.م\n\nأدخل المبلغ المراد سحبه:`);

            if (amount && !isNaN(amount) && parseFloat(amount) > 0) {
                if (parseFloat(amount) <= currentBalance) {
                    alert('سيتم إضافة خاصية السحب قريباً');
                } else {
                    alert('الرصيد غير كافٍ لإتمام عملية السحب');
                }
            } else if (amount !== null) {
                alert('الرجاء إدخال مبلغ صحيح');
            }
        });
    }

    if (transferBtn) {
        transferBtn.addEventListener('click', function () {
            alert('سيتم إضافة خاصية التحويل قريباً');
        });
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', async function () {
    // Check if we're on the wallet tab
    const walletTab = document.getElementById('wallet-tab');
    if (walletTab) {
        await initWalletPremium();
        await initPagination();
        initWalletButtons();
    }
});

// إضافة أنماط CSS للتحميل
if (!document.getElementById('wallet-loading-styles')) {
    const style = document.createElement('style');
    style.id = 'wallet-loading-styles';
    style.textContent = `
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    `;
    document.head.appendChild(style);
}
