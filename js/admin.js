/**
 * Admin Dashboard Logic
 */

document.addEventListener('DOMContentLoaded', () => {
    // Initialize Data
    initAdminData();

    // Load Site Visitor Stats
    loadSiteVisitorStats();

    // Setup Tab Navigation
    setupTabNavigation();

    // Load Data into Tables
    renderUsers();
    renderCodes(); // يعرض جدول الأكواد (فارغ حتى يتم استدعاء loadCodes عند فتح التبويب)

    // Setup Search
    setupSearch();

    // Handle Add User Form
    const addUserForm = document.getElementById('addUserForm');
    if (addUserForm) {
        addUserForm.addEventListener('submit', (e) => {
            e.preventDefault();
            addUser();
        });
    }

    // Setup Video Upload Section
    setupVideoUpload();
});

// بيانات الأكواد تُحمّل من API (لا بيانات ثابتة)
let adminCodesData = [];

function escapeHtml(text) {
    if (!text) return '';
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// تهيئة بيانات الأدمن (بدون بيانات ثابتة للأكواد)
function initAdminData() {
    try { localStorage.removeItem('nayl_codes'); } catch (e) { /* ignore */ }
    // الأكواد والكورسات تُحمّل من API عند فتح تبويب أكواد الكورسات
}

// Tab Navigation logic
function setupTabNavigation() {
    const menuLinks = document.querySelectorAll('.menu-link');
    const sections = document.querySelectorAll('.dashboard-section');

    // Restore active tab from localStorage
    const savedTab = localStorage.getItem('admin_active_tab');
    if (savedTab) {
        const targetLink = document.querySelector(`.menu-link[data-tab="${savedTab}"]`);
        const targetSection = document.getElementById(savedTab);

        if (targetLink && targetSection) {
            // Remove default active classes
            menuLinks.forEach(l => l.classList.remove('active'));
            sections.forEach(sec => sec.classList.remove('active'));

            // Add active class to saved tab
            targetLink.classList.add('active');
            targetSection.classList.add('active');

            // Trigger specific render functions if needed
            if (savedTab === 'users') renderUsers();
            if (savedTab === 'codes') loadCodes();
            if (savedTab === 'discount-codes') loadDiscountCodes();
            if (savedTab === 'videos') loadAdminCourses();
            if (savedTab === 'recharge-requests') renderRechargeRequests();
        }
    }

    menuLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const targetTab = link.getAttribute('data-tab');

            // Save active tab to localStorage
            localStorage.setItem('admin_active_tab', targetTab);

            // Update Active Link
            menuLinks.forEach(l => l.classList.remove('active'));
            link.classList.add('active');

            // Show Target Section
            sections.forEach(sec => {
                sec.classList.remove('active');
                if (sec.id === targetTab) {
                    sec.classList.add('active');
                }
            });

            // Re-render data if needed when switching
            if (targetTab === 'users') renderUsers();
            if (targetTab === 'codes') loadCodes();
            if (targetTab === 'discount-codes') loadDiscountCodes();
            if (targetTab === 'videos') loadAdminCourses();
        });
    });
}

// Render Users Table with Pagination
let currentUsersPage = 1;
let usersPerPage = 10;

async function renderUsers(page = 1) {
    const tbody = document.getElementById('usersTableBody');
    const recentTbody = document.querySelector('#recentUsersTable tbody');
    const pagination = document.getElementById('usersPagination');
    const totalUsersEl = document.getElementById('totalUsersCount');

    // Show loading state
    if (tbody) tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;">جاري التحميل...</td></tr>';

    try {
        const token = localStorage.getItem('admin_session_token') || getCookie('admin_session_token');
        const response = await fetch(`../api/admin/get-all-users.php?page=${page}&limit=${usersPerPage}`, {
            headers: {
                'Authorization': 'Bearer ' + token
            }
        });

        if (!checkAdminResponse(response)) return;
        const result = await response.json();

        if (result.success) {
            const users = result.data.users;
            const paginationData = result.data.pagination;

            // Render to All Users Table
            if (tbody) {
                tbody.innerHTML = '';
                if (users.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;">لا يوجد مستخدمين مسجلين</td></tr>';
                } else {
                    users.forEach(user => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td><span style="font-weight: 600; color: #4a90e2;">${user.id}</span></td>
                            <td>
                                <div class="user-cell">
                                    <div class="user-cell-avatar">${user.name ? user.name.substring(0, 2) : 'U'}</div>
                                    <span>${user.name || 'غير متوفر'}</span>
                                </div>
                            </td>
                            <td>${user.phone}</td>
                            <td><span style="font-weight: 600; color: #2d3748;">${user.wallet || 0} جنيه</span></td>
                            <td><span class="badge ${user.status === 'نشط' ? 'badge-active' : 'badge-pending'}">${user.status}</span></td>
                            <td>
                                <div class="actions-cell">
                                    <button class="action-btn btn-view" title="عرض التفاصيل" onclick="viewUserDetails(${user.id})"><i class="bi bi-eye-fill"></i></button>
                                    <button class="action-btn btn-edit" title="تعديل" onclick="editUser(${user.id})"><i class="bi bi-pencil-fill"></i></button>
                                    <button class="action-btn btn-delete" title="حذف" onclick="deleteUser(${user.id})"><i class="bi bi-trash-fill"></i></button>
                                </div>
                            </td>
                        `;
                        tbody.appendChild(row);
                    });
                }

                // Render pagination
                if (pagination) {
                    renderPaginationGeneric(pagination, page, paginationData.total_pages, paginationData.total_records, usersPerPage, 'changeUsersPage');
                }
            }

            // Render to Recent Users Table (only if on page 1)
            if (recentTbody && page === 1) {
                recentTbody.innerHTML = '';
                users.slice(0, 5).forEach(user => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td><span style="font-weight: 600; color: #4a90e2;">${user.id}</span></td>
                        <td>
                            <div class="user-cell">
                                <div class="user-cell-avatar">${user.name ? user.name.substring(0, 2) : 'U'}</div>
                                <span>${user.name || 'غير متوفر'}</span>
                            </div>
                        </td>
                        <td>${user.phone}</td>
                        <td>${user.joined}</td>
                        <td><span class="badge ${user.status === 'نشط' ? 'badge-active' : 'badge-pending'}">${user.status}</span></td>
                        <td>
                            <div class="actions-cell">
                                <button class="action-btn btn-view" title="عرض التفاصيل" onclick="viewUserDetails(${user.id})"><i class="bi bi-eye-fill"></i></button>
                            </div>
                        </td>
                    `;
                    recentTbody.appendChild(row);
                });
            }

            // Update stats
            if (totalUsersEl) totalUsersEl.textContent = paginationData.total_records;

            // Update global current page
            currentUsersPage = page;

        } else {
            console.error('Failed to fetch users:', result.message);
            if (tbody) tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; color:red;">خطأ في تحميل البيانات</td></tr>';
        }
    } catch (error) {
        console.error('Error fetching users:', error);
        if (tbody) tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; color:red;">خطأ في الاتصال بالخادم</td></tr>';
    }
}

function changeUsersPage(page) {
    renderUsers(page);
}

// Render Codes Table with Pagination
let currentCodesPage = 1;
let codesPerPage = 10;

function renderCodes(page = 1) {
    const codes = adminCodesData.slice();
    const tbody = document.getElementById('codesTableBody');
    const pagination = document.getElementById('codesPagination');

    if (!tbody) return;

    const startIndex = (page - 1) * codesPerPage;
    const endIndex = startIndex + codesPerPage;
    const paginatedCodes = codes.slice(startIndex, endIndex);
    const totalPages = Math.max(1, Math.ceil(codes.length / codesPerPage));

    tbody.innerHTML = '';
    if (codes.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding: 2rem; color: var(--text-gray);">لا توجد أكواد حتى الآن. استخدم "إنشاء كود تفعيل" واختر الكورس.</td></tr>';
        if (pagination) { pagination.innerHTML = ''; }
        const activeCodesEl = document.getElementById('activeCodesCount');
        if (activeCodesEl) activeCodesEl.textContent = '0';
        return;
    }
    const courseNameKey = 'course_title' in (codes[0] || {}) ? 'course_title' : 'courseName';
    paginatedCodes.forEach(c => {
        const row = document.createElement('tr');
        const codeRaw = c.code || '';
        const codeSafe = escapeHtml(codeRaw);
        const dataCodeAttr = codeRaw.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
        const nameSafe = escapeHtml(c[courseNameKey] || c.courseName || '—');
        const usedSafe = escapeHtml(c.usedBy || '—');
        const hasBoundDevice = !!c.bound_device;
        const unbindBtn = hasBoundDevice
            ? `<button class="action-btn btn-unbind" title="إلغاء ربط الجهاز - لتمكين صاحب الكود من الربط بجهاز جديد" onclick="unbindCodeDevice(${c.id})"><i class="bi bi-device-hdd"></i></button>`
            : '';
        row.innerHTML = `
            <td class="copyable-code-cell" data-code="${dataCodeAttr}" title="انقر للنسخ" style="font-family: monospace; font-weight: bold; letter-spacing: 1px; cursor: pointer; user-select: all;">
                <span>${codeSafe}</span>
                <i class="bi bi-clipboard" style="margin-right: 6px; opacity: 0.5; font-size: 0.85rem;"></i>
            </td>
            <td>${nameSafe}</td>
            <td>${escapeHtml(c.created_at || c.createdAt || '—')}</td>
            <td><span class="badge ${c.status === 'مفعل' ? 'badge-active' : 'badge-pending'}">${escapeHtml(c.status || '—')}</span></td>
            <td>${usedSafe}</td>
            <td>
                <div class="actions-cell">
                    ${unbindBtn}
                    <button class="action-btn btn-delete" title="حذف" onclick="deleteCode(${c.id})"><i class="bi bi-trash-fill"></i></button>
                </div>
            </td>
        `;
        tbody.appendChild(row);
    });

    if (pagination) {
        renderPaginationGeneric(pagination, page, totalPages, codes.length, codesPerPage, 'changeCodesPage');
        currentCodesPage = page;
    }

    const activeCodesEl = document.getElementById('activeCodesCount');
    if (activeCodesEl) activeCodesEl.textContent = codes.filter(c => c.status === 'مفعل').length;
}

// تحميل أكواد الكورسات من API
async function loadCodes() {
    const tbody = document.getElementById('codesTableBody');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding: 2rem; color: var(--text-gray);">جاري التحميل...</td></tr>';
    try {
        const token = localStorage.getItem('admin_session_token') || getCookie('admin_session_token');
        const response = await fetch('../api/admin/get-course-codes.php', {
            headers: { 'Authorization': 'Bearer ' + token }
        });
        const result = await response.json();
        if (result.success && result.data && Array.isArray(result.data.codes)) {
            adminCodesData = result.data.codes;
            renderCodes(1);
        } else {
            adminCodesData = [];
            renderCodes(1);
        }
    } catch (err) {
        console.error('Error loading course codes:', err);
        adminCodesData = [];
        renderCodes(1);
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding: 2rem; color: red;">خطأ في الاتصال بالخادم.</td></tr>';
    }
}

// تحميل قائمة الكورسات لزر إنشاء كود (في المودال)
async function loadCoursesForCodeModal() {
    const select = document.getElementById('courseSelect');
    if (!select) return;
    const firstOption = select.querySelector('option[value=""]') || (select.options[0] && select.options[0].value === '' ? select.options[0] : null);
    select.innerHTML = firstOption ? firstOption.outerHTML : '';
    try {
        const token = localStorage.getItem('admin_session_token') || getCookie('admin_session_token');
        const response = await fetch('../api/admin/get-courses.php', { headers: { 'Authorization': 'Bearer ' + token } });
        const result = await response.json();
        if (result.success && result.data && Array.isArray(result.data.courses)) {
            result.data.courses.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.id;
                opt.textContent = c.title || '';
                select.appendChild(opt);
            });
        }
    } catch (err) {
        console.error('Error loading courses for code modal:', err);
    }
}

function changeCodesPage(page) {
    renderCodes(page);
}

// ——— أكواد الخصم ———
async function loadDiscountCodes() {
    const tbody = document.getElementById('discountCodesTableBody');
    const emptyEl = document.getElementById('discountCodesEmpty');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding: 2rem;">جاري التحميل...</td></tr>';
    if (emptyEl) emptyEl.style.display = 'none';
    try {
        const token = localStorage.getItem('admin_session_token') || getCookie('admin_session_token');
        const response = await fetch('../api/admin/get-discount-codes.php', {
            headers: { 'Authorization': 'Bearer ' + token }
        });
        if (!checkAdminResponse(response)) return;
        const result = await response.json();
        if (result.success && result.data && Array.isArray(result.data.discount_codes)) {
            const list = result.data.discount_codes;
            tbody.innerHTML = '';
            if (list.length === 0) {
                if (emptyEl) { emptyEl.style.display = 'block'; emptyEl.textContent = 'لا توجد أكواد خصم. أنشئ كوداً من الزر أعلاه.'; }
                return;
            }
            list.forEach(function (d) {
                const row = document.createElement('tr');
                const statusBadge = d.status === 'used' ? '<span class="badge badge-pending">مُستخدم</span>' : '<span class="badge badge-active">نشط</span>';
                const assignedTo = (d.assigned_to_name || d.assigned_to_email) ? escapeHtml(d.assigned_to_name || d.assigned_to_email) + (d.assigned_to_email && d.assigned_to_name ? ' (' + escapeHtml(d.assigned_to_email) + ')' : '') : '—';
                const usedBy = d.used_by_name ? escapeHtml(d.used_by_name) + (d.used_by_email ? ' (' + escapeHtml(d.used_by_email) + ')' : '') : '—';
                const usedAt = d.used_at ? d.used_at : '—';
                row.innerHTML =
                    '<td style="font-family: monospace; font-weight: bold;">' + escapeHtml(d.code) + '</td>' +
                    '<td>' + parseFloat(d.discount_amount).toFixed(2) + ' ج.م</td>' +
                    '<td>' + escapeHtml(d.course_title) + '</td>' +
                    '<td>' + assignedTo + '</td>' +
                    '<td>' + statusBadge + '</td>' +
                    '<td>' + usedBy + '</td>' +
                    '<td>' + escapeHtml(usedAt) + '</td>';
                tbody.appendChild(row);
            });
        } else {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding: 2rem; color: red;">خطأ في جلب البيانات.</td></tr>';
        }
    } catch (err) {
        console.error('Load discount codes error:', err);
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding: 2rem; color: red;">خطأ في الاتصال بالخادم.</td></tr>';
    }
}

function openCreateDiscountCodeModal() {
    const card = document.getElementById('addDiscountCodeCard');
    if (!card) return;
    card.style.display = 'block';
    card.scrollIntoView({ behavior: 'smooth', block: 'start' });
    loadCoursesForDiscountCodeForm();
    document.getElementById('discountCodeFormError').style.display = 'none';
}

function closeCreateDiscountCodeModal() {
    const card = document.getElementById('addDiscountCodeCard');
    if (card) card.style.display = 'none';
    const form = document.getElementById('createDiscountCodeForm');
    if (form) form.reset();
}

async function loadCoursesForDiscountCodeForm() {
    const select = document.getElementById('discountCourseSelect');
    if (!select) return;
    const firstOpt = select.querySelector('option[value=""]') || select.options[0];
    select.innerHTML = firstOpt ? '<option value="">اختر الكورس</option>' : '';
    try {
        const token = localStorage.getItem('admin_session_token') || getCookie('admin_session_token');
        const response = await fetch('../api/admin/get-courses.php', { headers: { 'Authorization': 'Bearer ' + token } });
        if (!checkAdminResponse(response)) return;
        const result = await response.json();
        if (result.success && result.data && Array.isArray(result.data.courses)) {
            result.data.courses.forEach(function (c) {
                if (c.statusRaw === 'published' || c.status === 'منشور') {
                    const opt = document.createElement('option');
                    opt.value = c.id;
                    opt.textContent = c.title || '';
                    select.appendChild(opt);
                }
            });
        }
    } catch (err) {
        console.error('Load courses for discount form:', err);
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('createDiscountCodeForm');
    if (form) {
        form.addEventListener('submit', async function (e) {
            e.preventDefault();
            const codeInput = document.getElementById('discountCodeInput');
            const amountInput = document.getElementById('discountAmountInput');
            const courseSelect = document.getElementById('discountCourseSelect');
            const assignedInput = document.getElementById('discountAssignedUserId');
            const errEl = document.getElementById('discountCodeFormError');
            const code = codeInput ? codeInput.value.trim() : '';
            const amount = amountInput ? parseFloat(amountInput.value) : 0;
            const courseId = courseSelect ? parseInt(courseSelect.value, 10) : 0;
            const assignedUserId = assignedInput && assignedInput.value.trim() ? parseInt(assignedInput.value.trim(), 10) : 0;
            if (!code || amount <= 0 || !courseId) {
                if (errEl) { errEl.textContent = 'يرجى تعبئة الحقول بشكل صحيح.'; errEl.style.display = 'block'; }
                return;
            }
            if (errEl) errEl.style.display = 'none';
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) { submitBtn.disabled = true; submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> جاري الإنشاء...'; }
            const body = { code: code, discount_amount: amount, course_id: courseId };
            if (assignedUserId > 0) body.assigned_to_user_id = assignedUserId;
            try {
                const token = localStorage.getItem('admin_session_token') || getCookie('admin_session_token');
                const response = await fetch('../api/admin/create-discount-code.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + token },
                    body: JSON.stringify(body)
                });
                const result = await response.json();
                if (result.success) {
                    closeCreateDiscountCodeModal();
                    loadDiscountCodes();
                } else {
                    if (errEl) { errEl.textContent = result.error || 'حدث خطأ'; errEl.style.display = 'block'; }
                }
            } catch (err) {
                console.error('Create discount code error:', err);
                if (errEl) { errEl.textContent = 'خطأ في الاتصال بالخادم.'; errEl.style.display = 'block'; }
            }
            if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = '<i class="bi bi-check-lg"></i><span>إنشاء كود الخصم</span>'; }
        });
    }
});

// Modal functions
function openModal(modalId) {
    const el = document.getElementById(modalId);
    if (el) {
        el.style.display = 'flex';
        requestAnimationFrame(() => el.classList.add('active'));
    }
}

function closeModal(modalId) {
    const el = document.getElementById(modalId);
    if (el) {
        el.classList.remove('active');
        setTimeout(() => {
            if (!el.classList.contains('active')) el.style.display = 'none';
        }, 300); // Wait for transition
    }
}

// Toggle Add User Form (Inline)
function toggleAddUserForm() {
    const formCard = document.getElementById('addUserFormCard');
    const isVisible = formCard.style.display !== 'none';

    if (isVisible) {
        formCard.style.display = 'none';
        // Reset form
        document.getElementById('addUserFormInline').reset();
    } else {
        formCard.style.display = 'block';
        // Scroll to form
        formCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

// Add User (from inline form)
document.addEventListener('DOMContentLoaded', () => {
    const addUserFormInline = document.getElementById('addUserFormInline');
    if (addUserFormInline) {
        addUserFormInline.addEventListener('submit', (e) => {
            e.preventDefault();
            addUserInline();
        });
    }
});

function addUserInline() {
    alert('⚠️ تم تعطيل إضافة المستخدمين مؤقتاً لأن النظام متصل بقاعدة البيانات الحية.\nيرجى استخدام واجهة التسجيل أو طلب تطوير API الإضافة.');
    toggleAddUserForm();
}

// Add User (from modal - kept for backward compatibility)
function addUser() {
    alert('⚠️ تم تعطيل إضافة المستخدمين مؤقتاً لأن النظام متصل بقاعدة البيانات الحية.');
    closeModal('addUserModal');
}

// فتح مودال إنشاء الأكواد بعد تحميل قائمة الكورسات
function openGenerateCodeModal() {
    loadCoursesForCodeModal().then(() => openModal('generateCodeModal'));
}

// إنشاء أكواد تفعيل عبر API (مرتبطة بالكورس المحدد)
async function generateCodes() {
    const courseSelect = document.getElementById('courseSelect');
    const courseId = courseSelect ? courseSelect.value : '';
    const count = parseInt(document.getElementById('codesCount').value, 10) || 1;
    const expiryDays = parseInt(document.getElementById('expiryDays').value, 10) || 365;

    if (!courseId) {
        alert('يرجى اختيار كورس أولاً');
        return;
    }

    try {
        const token = localStorage.getItem('admin_session_token') || getCookie('admin_session_token');
        const response = await fetch('../api/admin/create-course-codes.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + token
            },
            body: JSON.stringify({
                course_id: parseInt(courseId, 10),
                count: Math.min(100, Math.max(1, count)),
                expiry_days: expiryDays
            })
        });
        const result = await response.json();

        if (result.success && result.data) {
            closeModal('generateCodeModal');
            await loadCodes();
            alert('تم إنشاء ' + (result.data.created || count) + ' كود بنجاح');
        } else {
            alert('فشل إنشاء الأكواد: ' + (result.error || 'خطأ غير معروف'));
        }
    } catch (err) {
        console.error('Error creating codes:', err);
        alert('حدث خطأ أثناء الاتصال بالخادم');
    }
}

// Actions
async function deleteUser(userId) {
    if (confirm('هل أنت متأكد من حذف هذا المستخدم؟')) {
        try {
            const token = localStorage.getItem('admin_session_token') || getCookie('admin_session_token');
            const response = await fetch('../api/admin/delete-user.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer ' + token
                },
                body: JSON.stringify({ user_id: userId })
            });

            const result = await response.json();

            if (result.success) {
                // Refresh table
                renderUsers(currentUsersPage);
                alert('✓ تم حذف المستخدم بنجاح');
            } else {
                alert('❌ فشل الحذف: ' + result.message);
            }
        } catch (error) {
            console.error('Error deleting user:', error);
            alert('❌ حدث خطأ أثناء الاتصال بالخادم');
        }
    }
}

async function editUser(userId) {
    const token = localStorage.getItem('admin_session_token') || getCookie('admin_session_token');
    if (!token) {
        alert('انتهت صلاحية الجلسة، يرجى تسجيل الدخول.');
        return;
    }
    try {
        const res = await fetch(`../api/admin/get-user-details.php?user_id=${userId}`, {
            headers: { 'Authorization': 'Bearer ' + token }
        });
        const json = await res.json();
        if (!json.success || !json.data || !json.data.user) {
            alert(json.message || 'فشل جلب بيانات المستخدم');
            return;
        }
        const u = json.data.user;
        document.getElementById('editUserId').value = u.id;
        document.getElementById('editUserName').value = u.name || '';
        document.getElementById('editUserPhone').value = u.phone || '';
        document.getElementById('editUserEmail').value = u.email || '';
        document.getElementById('editUserCountry').value = u.country || '';
        document.getElementById('editUserCity').value = u.city || '';
        document.getElementById('editUserStatus').value = u.status === 'نشط' ? 'نشط' : 'معلق';
        document.getElementById('editUserWallet').value = u.wallet != null ? parseFloat(u.wallet).toFixed(2) : '0';
        openModal('editUserModal');
    } catch (e) {
        console.error('editUser:', e);
        alert('حدث خطأ أثناء جلب البيانات');
    }
}

function closeEditUserModal() {
    closeModal('editUserModal');
}

async function submitEditUser() {
    const id = document.getElementById('editUserId').value;
    const name = document.getElementById('editUserName').value.trim();
    const phone = document.getElementById('editUserPhone').value.trim();
    const email = document.getElementById('editUserEmail').value.trim();
    const country = document.getElementById('editUserCountry').value.trim();
    const city = document.getElementById('editUserCity').value.trim();
    const status = document.getElementById('editUserStatus').value;
    const wallet = parseFloat(document.getElementById('editUserWallet').value) || 0;
    if (!name || !phone || !email) {
        alert('الاسم، رقم الهاتف، والبريد الإلكتروني مطلوبة');
        return;
    }
    const token = localStorage.getItem('admin_session_token') || getCookie('admin_session_token');
    if (!token) {
        alert('انتهت صلاحية الجلسة.');
        return;
    }
    try {
        const res = await fetch('../api/admin/update-user.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + token
            },
            body: JSON.stringify({
                user_id: parseInt(id, 10),
                full_name: name,
                phone: phone.replace(/\D/g, ''),
                email,
                country: country || null,
                city: city || null,
                is_active: status === 'نشط',
                wallet_balance: wallet
            })
        });
        const json = await res.json();
        if (json.success) {
            closeEditUserModal();
            renderUsers(currentUsersPage);
            alert('✓ تم تحديث بيانات الحساب بنجاح');
        } else {
            alert('❌ ' + (json.message || 'فشل التحديث'));
        }
    } catch (e) {
        console.error('submitEditUser:', e);
        alert('حدث خطأ أثناء الاتصال بالخادم');
    }
}

async function viewUserDetails(userId) {
    const token = localStorage.getItem('admin_session_token') || getCookie('admin_session_token');
    if (!token) {
        alert('انتهت صلاحية الجلسة، يرجى تسجيل الدخول.');
        return;
    }
    try {
        const res = await fetch(`../api/admin/get-user-details.php?user_id=${userId}`, {
            headers: { 'Authorization': 'Bearer ' + token }
        });
        const json = await res.json();
        if (!json.success || !json.data || !json.data.user) {
            alert(json.message || 'فشل جلب بيانات المستخدم');
            return;
        }
        const u = json.data.user;
        const nameEl = document.getElementById('viewUserDetailsName');
        const listEl = document.getElementById('viewUserDetailsCoursesList');
        const emptyEl = document.getElementById('viewUserDetailsEmpty');
        if (nameEl) nameEl.textContent = 'الحساب: ' + (u.name || '—');
        if (emptyEl) emptyEl.style.display = 'none';
        if (listEl) {
            listEl.innerHTML = '';
            if (u.courses && u.courses.length > 0) {
                listEl.style.display = 'block';
                u.courses.forEach(function (c) {
                    const item = document.createElement('div');
                    item.style.cssText = 'padding: 12px 16px; background: #f8fafc; border-radius: var(--radius-md); margin-bottom: 8px; border: 1px solid #edf2f7;';
                    item.innerHTML = '<strong style="color: var(--text-dark);">' + (c.title || '—') + '</strong>' +
                        (c.enrolled_at ? '<span style="color: var(--text-gray); font-size: 0.9rem; margin-right: 8px;"> — ' + c.enrolled_at + '</span>' : '');
                    listEl.appendChild(item);
                });
            } else {
                listEl.style.display = 'none';
                if (emptyEl) emptyEl.style.display = 'block';
            }
        }
        openModal('viewUserDetailsModal');
    } catch (e) {
        console.error('viewUserDetails:', e);
        alert('حدث خطأ أثناء جلب البيانات');
    }
}

function closeViewUserDetailsModal() {
    closeModal('viewUserDetailsModal');
}

async function unbindCodeDevice(codeId) {
    if (!confirm('إلغاء ربط هذا الكود بالجهاز؟ بعد ذلك يمكن لصاحب الكود تفعيل الكود على جهاز جديد فقط.')) return;
    try {
        const token = localStorage.getItem('admin_session_token') || getCookie('admin_session_token');
        const response = await fetch('../api/admin/unbind-code-device.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + token
            },
            body: JSON.stringify({ code_id: codeId })
        });
        const result = await response.json();
        if (result.success) {
            await loadCodes();
            alert(result.data && result.data.message ? result.data.message : 'تم إلغاء ربط الكود بالجهاز.');
        } else {
            alert('فشل إلغاء الربط: ' + (result.error || 'خطأ غير معروف'));
        }
    } catch (err) {
        console.error('Error unbinding code device:', err);
        alert('حدث خطأ أثناء الاتصال بالخادم');
    }
}

async function deleteCode(codeId) {
    if (!confirm('هل أنت متأكد من حذف هذا الكود؟')) return;
    try {
        const token = localStorage.getItem('admin_session_token') || getCookie('admin_session_token');
        const response = await fetch('../api/admin/delete-course-code.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + token
            },
            body: JSON.stringify({ code_id: codeId })
        });
        const result = await response.json();
        if (result.success) {
            await loadCodes();
            alert('تم حذف الكود بنجاح');
        } else {
            alert('فشل الحذف: ' + (result.error || 'خطأ غير معروف'));
        }
    } catch (err) {
        console.error('Error deleting code:', err);
        alert('حدث خطأ أثناء الاتصال بالخادم');
    }
}

// Search Functionality
function setupSearch() {
    const searchInput = document.querySelector('.search-bar input');
    if (!searchInput) return;

    searchInput.addEventListener('input', (e) => {
        const query = e.target.value.toLowerCase();

        const activeSection = document.querySelector('.dashboard-section.active');
        if (!activeSection) return;

        const rows = activeSection.querySelectorAll('tbody tr');

        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(query) ? '' : 'none';
        });
    });
}

// ===========================
// Video Upload Section Functions
// ===========================

let videoCounter = 1;

function setupVideoUpload() {
    const addVideoBtn = document.getElementById('addVideoBtn');
    const uploadForm = document.getElementById('uploadCourseForm');

    if (addVideoBtn) {
        addVideoBtn.addEventListener('click', addNewVideoField);
    }

    if (uploadForm) {
        uploadForm.addEventListener('submit', handleCourseUpload);
    }

    // Handle file input changes to show file name
    setupFileInputListeners();
}

function addNewVideoField() {
    videoCounter++;
    const videosContainer = document.getElementById('videosContainer');

    const videoItem = document.createElement('div');
    videoItem.className = 'video-upload-item';
    videoItem.setAttribute('data-video-index', videoCounter - 1);

    videoItem.innerHTML = `
        <div class="video-item-header">
            <h4 style="font-size: 1rem; font-weight: 700; color: var(--primary-dark);">الفيديو #${videoCounter}</h4>
            <button type="button" class="remove-video-btn" onclick="removeVideoField(this)">
                <i class="bi bi-trash-fill" style="display: inline-block; vertical-align: middle; margin-left: 4px;"></i>
                حذف
            </button>
        </div>
        <div class="video-item-fields">
            <div class="admin-form-group">
                <label>عنوان الفيديو</label>
                <input type="text" class="video-title" placeholder="مثال: مقدمة عن الكورس" required>
            </div>
            <div class="admin-form-group">
                <label>ترتيب الفيديو</label>
                <input type="number" class="video-order" value="${videoCounter}" min="1" required>
            </div>
            <div class="admin-form-group">
                <label>صورة واجهة الفيديو</label>
                <div class="file-upload-wrapper">
                    <input type="file" class="video-thumbnail" accept="image/*" required>
                    <div class="file-upload-label">
                        <i class="bi bi-image" style="font-size: 1.5rem;"></i>
                        <span>اختر صورة الواجهة</span>
                    </div>
                </div>
            </div>
            <div class="admin-form-group">
                <label>رفع الفيديو</label>
                <div class="file-upload-wrapper">
                    <input type="file" class="video-file" accept="video/*" required>
                    <div class="file-upload-label">
                        <i class="bi bi-file-earmark-play" style="font-size: 1.5rem;"></i>
                        <span>اختر ملف الفيديو</span>
                    </div>
                </div>
            </div>
            <div class="admin-form-group" style="grid-column: 1 / -1;">
                <label>تفاصيل وشرح الفيديو</label>
                <textarea class="video-description" rows="3" placeholder="اكتب وصف تفصيلي عن محتوى الفيديو..." required></textarea>
            </div>
        </div>
    `;

    videosContainer.appendChild(videoItem);
    setupFileInputListeners();
}

function removeVideoField(button) {
    const videoItem = button.closest('.video-upload-item');
    videoItem.remove();

    // Re-number remaining videos
    const remainingVideos = document.querySelectorAll('.video-upload-item');
    remainingVideos.forEach((item, index) => {
        const header = item.querySelector('.video-item-header h4');
        header.textContent = `الفيديو #${index + 1}`;
        item.setAttribute('data-video-index', index);
    });

    videoCounter = remainingVideos.length;
}

function setupFileInputListeners() {
    // Handle video file inputs
    const fileInputs = document.querySelectorAll('.video-file');
    fileInputs.forEach(input => {
        const newInput = input.cloneNode(true);
        input.parentNode.replaceChild(newInput, input);

        newInput.addEventListener('change', function (e) {
            const label = this.parentElement.querySelector('.file-upload-label span');
            if (this.files && this.files[0]) {
                label.textContent = this.files[0].name;
                label.style.color = 'var(--accent-green)';
            } else {
                label.textContent = 'اختر ملف الفيديو';
                label.style.color = '';
            }
        });
    });

    // Handle thumbnail image inputs
    const thumbnailInputs = document.querySelectorAll('.video-thumbnail');
    thumbnailInputs.forEach(input => {
        const newInput = input.cloneNode(true);
        input.parentNode.replaceChild(newInput, input);

        newInput.addEventListener('change', function (e) {
            const label = this.parentElement.querySelector('.file-upload-label span');
            if (this.files && this.files[0]) {
                label.textContent = this.files[0].name;
                label.style.color = 'var(--accent-green)';
            } else {
                label.textContent = 'اختر صورة الواجهة';
                label.style.color = '';
            }
        });
    });
}

async function handleCourseUpload(e) {
    e.preventDefault();

        const courseTitle = document.getElementById('courseTitle').value.trim();
        const courseDescription = document.getElementById('courseDescription').value.trim();
        const coursePriceInput = document.getElementById('coursePrice');
        const coursePrice = coursePriceInput ? parseFloat(coursePriceInput.value) : 500;
        const validPrice = !isNaN(coursePrice) && coursePrice >= 0 ? coursePrice : 500;

        // التحقق من البيانات الأساسية
        if (!courseTitle) {
            alert('يرجى إدخال عنوان الكورس');
            return;
        }

    // Collect all videos data
    const videoItems = document.querySelectorAll('.video-upload-item');
    const videos = [];

    // التحقق من وجود فيديوهات
    if (videoItems.length === 0) {
        alert('يرجى إضافة فيديو واحد على الأقل');
        return;
    }

    // عرض مؤشر التحميل
    const submitButton = e.target.querySelector('button[type="submit"]');
    const originalButtonText = submitButton ? submitButton.innerHTML : '';
    if (submitButton) {
        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="bi bi-hourglass-split"></i> جاري الرفع...';
    }

    try {
        // معالجة كل فيديو وتحويله إلى Base64
        for (let index = 0; index < videoItems.length; index++) {
            const item = videoItems[index];
            const title = item.querySelector('.video-title').value.trim();
            const order = parseInt(item.querySelector('.video-order').value) || (index + 1);
            const file = item.querySelector('.video-file').files[0];
            const thumbnail = item.querySelector('.video-thumbnail').files[0];
            const description = item.querySelector('.video-description').value.trim();

            if (!title || !file || !thumbnail || !description) {
                throw new Error(`يرجى ملء جميع بيانات الفيديو #${order}`);
            }

            // تحويل الملفات إلى Base64
            const videoBase64 = await fileToBase64(file);
            const thumbnailBase64 = await fileToBase64(thumbnail);

            videos.push({
                title: title,
                order: order,
                description: description,
                videoFile: videoBase64,
                thumbnailFile: thumbnailBase64
            });
        }

        if (videos.length === 0) {
            throw new Error('يرجى إضافة فيديو واحد على الأقل');
        }

        // صورة واجهة الكورس (اختيارية)
        const courseCoverInput = document.getElementById('courseCoverImage');
        let courseCoverFile = null;
        if (courseCoverInput && courseCoverInput.files && courseCoverInput.files[0]) {
            courseCoverFile = await fileToBase64(courseCoverInput.files[0]);
        }

        // إرسال البيانات إلى API
        const token = localStorage.getItem('admin_session_token') || getCookie('admin_session_token');
        
        const requestData = {
            courseTitle: courseTitle,
            courseDescription: courseDescription,
            coursePrice: validPrice,
            courseCoverFile: courseCoverFile,
            videos: videos
        };
        
        // التحقق من حجم البيانات
        const dataSize = new Blob([JSON.stringify(requestData)]).size;
        const maxSize = 100 * 1024 * 1024; // 100MB
        
        if (dataSize > maxSize) {
            throw new Error('حجم البيانات كبير جداً. يرجى تقليل حجم الفيديوهات أو رفعها بشكل منفصل.');
        }
        
        const response = await fetch('../api/admin/upload-course.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + token
            },
            body: JSON.stringify(requestData)
        });

        // التحقق من نوع الاستجابة
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const text = await response.text();
            console.error('Non-JSON response:', text);
            throw new Error('استجابة غير صحيحة من الخادم. يرجى التحقق من سجلات الأخطاء.');
        }

        const result = await response.json();

        if (!response.ok || !result.success) {
            throw new Error(result.error || 'فشل رفع الكورس');
        }

        // عرض رسالة النجاح
        const successMessage = `تم رفع الكورس "${courseTitle}" بنجاح!\nعدد الفيديوهات: ${result.data.videosCount}`;
        if (result.data.warnings && result.data.warnings.length > 0) {
            alert(successMessage + '\n\nتحذيرات:\n' + result.data.warnings.join('\n'));
        } else {
            alert(successMessage);
        }

        // تحديث قائمة الكورسات من السيرفر
        loadAdminCourses();

        // Reset form
        e.target.reset();

        // Reset videos container to initial state
        const videosContainer = document.getElementById('videosContainer');
        videosContainer.innerHTML = `
            <div class="video-upload-item" data-video-index="0">
                <div class="video-item-header">
                    <h4 style="font-size: 1rem; font-weight: 700; color: var(--primary-dark);">الفيديو #1</h4>
                </div>
                <div class="video-item-fields">
                    <div class="admin-form-group">
                        <label>عنوان الفيديو</label>
                        <input type="text" class="video-title" placeholder="مثال: مقدمة عن الكورس" required>
                    </div>
                    <div class="admin-form-group">
                        <label>ترتيب الفيديو</label>
                        <input type="number" class="video-order" value="1" min="1" required>
                    </div>
                    <div class="admin-form-group">
                        <label>صورة واجهة الفيديو</label>
                        <div class="file-upload-wrapper">
                            <input type="file" class="video-thumbnail" accept="image/*" required>
                            <div class="file-upload-label">
                                <i class="bi bi-image" style="font-size: 1.5rem;"></i>
                                <span>اختر صورة الواجهة</span>
                            </div>
                        </div>
                    </div>
                    <div class="admin-form-group">
                        <label>رفع الفيديو</label>
                        <div class="file-upload-wrapper">
                            <input type="file" class="video-file" accept="video/*" required>
                            <div class="file-upload-label">
                                <i class="bi bi-file-earmark-play" style="font-size: 1.5rem;"></i>
                                <span>اختر ملف الفيديو</span>
                            </div>
                        </div>
                    </div>
                    <div class="admin-form-group" style="grid-column: 1 / -1;">
                        <label>تفاصيل وشرح الفيديو</label>
                        <textarea class="video-description" rows="3" placeholder="اكتب وصف تفصيلي عن محتوى الفيديو..." required></textarea>
                    </div>
                </div>
            </div>
        `;

        videoCounter = 1;
        setupFileInputListeners();

        // تحديث قائمة الكورسات إذا كانت مفتوحة
        if (document.getElementById('videos').classList.contains('active')) {
            renderCourses(1);
        }

    } catch (error) {
        console.error('Upload error:', error);
        alert('خطأ في رفع الكورس: ' + error.message);
    } finally {
        // إعادة تفعيل الزر
        if (submitButton) {
            submitButton.disabled = false;
            submitButton.innerHTML = originalButtonText;
        }
    }
}

/**
 * تحويل ملف إلى Base64
 * @param {File} file - الملف المراد تحويله
 * @returns {Promise<string>} - Base64 string
 */
function fileToBase64(file) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => {
            // إزالة prefix (data:video/mp4;base64,) إذا كان موجوداً
            const base64 = reader.result.split(',')[1] || reader.result;
            resolve(base64);
        };
        reader.onerror = (error) => reject(error);
        reader.readAsDataURL(file);
    });
}

/**
 * الحصول على cookie value
 */
function getCookie(name) {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) return parts.pop().split(';').shift();
    return null;
}

// ===========================
// Courses Management with Pagination
// ===========================

let currentCoursesPage = 1;
let coursesPerPage = 10;
let currentCourseId = null;

/**
 * تحميل قائمة الكورسات من السيرفر وتحديث الجدول
 */
async function loadAdminCourses() {
    const tbody = document.getElementById('coursesTableBody');
    const pagination = document.getElementById('coursesPagination');
    if (tbody) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 2rem;">جاري تحميل الكورسات...</td></tr>';
    }
    const token = localStorage.getItem('admin_session_token') || getCookie('admin_session_token');
    try {
        const response = await fetch('../api/admin/get-courses.php', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + token
            },
            credentials: 'include'
        });
        let result;
        try {
            result = await response.json();
        } catch (parseErr) {
            if (tbody) tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 2rem; color: #e74c3c;">استجابة غير صحيحة من الخادم.</td></tr>';
            if (pagination) pagination.innerHTML = '';
            return;
        }
        if (!response.ok) {
            const msg = result.error || (response.status === 401 ? 'يجب تسجيل الدخول كمسؤول' : 'فشل تحميل الكورسات');
            if (tbody) tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 2rem; color: #e74c3c;">' + (msg || 'فشل تحميل الكورسات') + '</td></tr>';
            if (pagination) pagination.innerHTML = '';
            return;
        }
        const list = (result.success && result.data && result.data.courses) ? result.data.courses : [];
        const mapped = list.map(function (c) {
            return {
                id: c.id,
                title: c.title,
                description: c.description || '',
                videosCount: c.videosCount || 0,
                uploadDate: c.uploadDate || '—',
                status: c.status || 'منشور',
                videos: []
            };
        });
        localStorage.setItem('nayl_courses', JSON.stringify(mapped));
        renderCourses(1);
    } catch (err) {
        console.error('Error loading courses:', err);
        if (tbody) {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 2rem; color: #e74c3c;">فشل تحميل الكورسات. تحقق من الاتصال أو من تسجيل الدخول.</td></tr>';
        }
        if (pagination) pagination.innerHTML = '';
    }
}

function renderCourses(page = 1) {
    const courses = JSON.parse(localStorage.getItem('nayl_courses')) || [];
    const tbody = document.getElementById('coursesTableBody');
    const pagination = document.getElementById('coursesPagination');

    if (!tbody) return;

    // Calculate pagination
    const startIndex = (page - 1) * coursesPerPage;
    const endIndex = startIndex + coursesPerPage;
    const paginatedCourses = courses.slice(startIndex, endIndex);
    const totalPages = Math.ceil(courses.length / coursesPerPage);

    // Render table rows
    tbody.innerHTML = '';
    if (courses.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 2rem; color: var(--text-gray);">لا توجد كورسات. ارفع كورساً جديداً من النموذج أعلاه.</td></tr>';
        if (pagination) pagination.innerHTML = '';
        currentCoursesPage = page;
        return;
    }
    paginatedCourses.forEach(course => {
        const row = document.createElement('tr');
        row.className = 'course-row';
        row.setAttribute('data-course-id', course.id);
        row.innerHTML = `
            <td>
                <div class="course-cell">
                    <i class="bi bi-play-circle-fill" style="color: var(--primary-blue); font-size: 1.25rem;"></i>
                    <span style="font-weight: 600;">${course.title}</span>
                </div>
            </td>
            <td>${course.videosCount} فيديو</td>
            <td>${course.uploadDate}</td>
            <td><span class="badge ${course.status === 'منشور' ? 'badge-active' : 'badge-pending'}">${course.status}</span></td>
            <td>
                <div class="actions-cell" style="justify-content: center;">
                    <button class="action-btn btn-edit" onclick="editCoursePage(${course.id})" title="تعديل">
                        <i class="bi bi-pencil-fill"></i>
                    </button>
                    <button class="action-btn btn-delete" onclick="deleteCourse(${course.id})" title="حذف">
                        <i class="bi bi-trash-fill"></i>
                    </button>
                </div>
            </td>
        `;
        tbody.appendChild(row);
    });

    // Render pagination
    if (pagination) {
        renderPagination(pagination, page, totalPages, courses.length);
    }

    currentCoursesPage = page;
}

// Generic Pagination Render Function
function renderPaginationGeneric(container, currentPage, totalPages, totalItems, itemsPerPage, changePageFunctionName) {
    const startItem = (currentPage - 1) * itemsPerPage + 1;
    const endItem = Math.min(currentPage * itemsPerPage, totalItems);

    let paginationHTML = `
        <div class="pagination-info">
            عرض ${startItem} إلى ${endItem} من ${totalItems}
        </div>
        <div class="pagination-buttons">
            <button class="pagination-btn" onclick="${changePageFunctionName}(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''}>
                السابق
            </button>
    `;

    // Page numbers
    const maxVisiblePages = 5;
    let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
    let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);

    if (endPage - startPage < maxVisiblePages - 1) {
        startPage = Math.max(1, endPage - maxVisiblePages + 1);
    }

    for (let i = startPage; i <= endPage; i++) {
        paginationHTML += `
            <button class="pagination-btn ${i === currentPage ? 'active' : ''}" onclick="${changePageFunctionName}(${i})">
                ${i}
            </button>
        `;
    }

    paginationHTML += `
            <button class="pagination-btn" onclick="${changePageFunctionName}(${currentPage + 1})" ${currentPage === totalPages ? 'disabled' : ''}>
                التالي
            </button>
        </div>
    `;

    container.innerHTML = paginationHTML;
}

function renderPagination(container, currentPage, totalPages, totalItems) {
    const startItem = (currentPage - 1) * coursesPerPage + 1;
    const endItem = Math.min(currentPage * coursesPerPage, totalItems);

    let paginationHTML = `
        <div class="pagination-info">
            عرض ${startItem} إلى ${endItem} من ${totalItems} كورس
        </div>
        <div class="pagination-buttons">
            <button class="pagination-btn" onclick="changePage(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''}>
                السابق
            </button>
    `;

    // Page numbers
    const maxVisiblePages = 5;
    let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
    let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);

    if (endPage - startPage < maxVisiblePages - 1) {
        startPage = Math.max(1, endPage - maxVisiblePages + 1);
    }

    for (let i = startPage; i <= endPage; i++) {
        paginationHTML += `
            <button class="pagination-btn ${i === currentPage ? 'active' : ''}" onclick="changePage(${i})">
                ${i}
            </button>
        `;
    }

    paginationHTML += `
            <button class="pagination-btn" onclick="changePage(${currentPage + 1})" ${currentPage === totalPages ? 'disabled' : ''}>
                التالي
            </button>
        </div>
    `;

    container.innerHTML = paginationHTML;
}

function changePage(page) {
    renderCourses(page);
}

// Setup courses per page selector
document.addEventListener('DOMContentLoaded', () => {
    const perPageSelect = document.getElementById('coursesPerPage');
    if (perPageSelect) {
        perPageSelect.addEventListener('change', (e) => {
            coursesPerPage = parseInt(e.target.value);
            renderCourses(1);
        });
    }
});

async function editCourse(courseId) {
    currentCourseId = courseId;

    const contentEl = document.getElementById('courseEditContent');
    const modalEl = document.getElementById('courseEditModal');
    if (contentEl) contentEl.innerHTML = '<div style="text-align: center; padding: 2rem; color: var(--text-gray);">جاري تحميل تفاصيل الكورس...</div>';
    if (modalEl) modalEl.style.display = 'flex';

    let course = null;
    try {
        const token = localStorage.getItem('admin_session_token') || getCookie('admin_session_token');
        const response = await fetch('../api/admin/get-course-details.php?course_id=' + encodeURIComponent(courseId), {
            method: 'GET',
            headers: { 'Authorization': 'Bearer ' + token },
            credentials: 'include'
        });
        const result = await response.json();
        if (result.success && result.data && result.data.course) {
            course = result.data.course;
            if (!Array.isArray(course.videos)) course.videos = [];
        }
    } catch (e) {
        console.error('Error loading course details:', e);
    }

    if (!course) {
        const courses = JSON.parse(localStorage.getItem('nayl_courses')) || [];
        course = courses.find(c => c.id === courseId);
        if (!course) {
            if (contentEl) contentEl.innerHTML = '<div style="text-align: center; padding: 2rem; color: #e74c3c;">الكورس غير موجود أو فشل تحميل التفاصيل.</div>';
            return;
        }
        if (!Array.isArray(course.videos)) course.videos = [];
    }

    // Build edit modal content
    let modalContent = `
        <div class="course-edit-section">
            <h4>معلومات الكورس</h4>
            <div class="admin-form-group">
                <label>عنوان الكورس</label>
                <input type="text" id="editCourseTitle" value="${escapeHtml(course.title)}" class="form-control">
            </div>
            <div class="admin-form-group">
                <label>وصف الكورس</label>
                <textarea id="editCourseDescription" rows="3" class="form-control">${escapeHtml(course.description)}</textarea>
            </div>
            <div class="admin-form-group">
                <label>الحالة</label>
                <select id="editCourseStatus" class="form-control">
                    <option value="منشور" ${course.status === 'منشور' ? 'selected' : ''}>منشور</option>
                    <option value="مسودة" ${course.status === 'مسودة' ? 'selected' : ''}>مسودة</option>
                </select>
            </div>
        </div>

        <div class="course-edit-section">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--spacing-md);">
                <h4 style="margin: 0;">الفيديوهات (${course.videos.length})</h4>
                <button class="btn btn-secondary btn-with-icon" onclick="showAddVideoForm(${courseId})">
                    <i class="bi bi-plus-circle-fill"></i>
                    <span>إضافة فيديو جديد</span>
                </button>
            </div>
            
            <!-- Add Video Form (Hidden by default) -->
            <div id="addVideoForm" style="display: none; background: #f8f9fa; padding: var(--spacing-lg); border-radius: var(--radius-md); margin-bottom: var(--spacing-md);">
                <h5 style="margin-bottom: var(--spacing-md);">إضافة فيديو جديد</h5>
                <div class="admin-form-group">
                    <label>عنوان الفيديو</label>
                    <input type="text" id="newVideoTitle" placeholder="مثال: مقدمة عن الدرس" class="form-control">
                </div>
                <div class="admin-form-group">
                    <label>ترتيب الفيديو</label>
                    <input type="number" id="newVideoOrder" value="${course.videos.length + 1}" min="1" class="form-control">
                </div>
                <div class="admin-form-group">
                    <label>صورة واجهة الفيديو</label>
                    <div class="file-upload-wrapper">
                        <input type="file" id="newVideoThumbnail" accept="image/*" class="form-control">
                        <div class="file-upload-label">
                            <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <span>اختر صورة الواجهة</span>
                        </div>
                    </div>
                </div>
                <div class="admin-form-group">
                    <label>رفع الفيديو</label>
                    <div class="file-upload-wrapper">
                        <input type="file" id="newVideoFile" accept="video/*" class="form-control">
                        <div class="file-upload-label">
                            <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12"/>
                            </svg>
                            <span>اختر ملف الفيديو</span>
                        </div>
                    </div>
                </div>
                <div class="admin-form-group">
                    <label>تفاصيل وشرح الفيديو</label>
                    <textarea id="newVideoDescription" rows="3" placeholder="اكتب وصف تفصيلي عن محتوى الفيديو..." class="form-control"></textarea>
                </div>
                <div style="display: flex; gap: var(--spacing-sm); justify-content: flex-end; margin-top: var(--spacing-md);">
                    <button class="btn btn-secondary" onclick="hideAddVideoForm()">
                        <i class="bi bi-x-lg"></i>
                        إلغاء
                    </button>
                    <button class="btn btn-primary btn-with-icon" onclick="saveNewVideo(${courseId})">
                        <i class="bi bi-check-lg"></i>
                        <span>حفظ الفيديو</span>
                    </button>
                </div>
            </div>
            
            <div id="editVideosList">
    `;

    if (course.videos.length > 0) {
        course.videos.forEach((video, index) => {
            modalContent += `
                <div class="video-list-item" data-video-id="${video.id}">
                    <div class="video-list-header">
                        <div class="video-list-title">
                            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                                <path d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span>${video.order}. ${escapeHtml(video.title)}</span>
                        </div>
                        <div class="video-list-actions">
                            <button class="action-btn btn-delete" onclick="deleteVideoFromCourse(${courseId}, ${video.id})" title="حذف الفيديو">
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M3 6h18m-2 0v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                    <p style="color: var(--text-gray); font-size: 0.9rem; margin: 0;">${escapeHtml(video.description)}</p>
                    ${video.video_url ? `<div style="margin-top: 8px;"><video src="../api/admin/proxy-video.php?video_id=${video.id}" controls style="max-width: 100%; max-height: 220px; border-radius: 8px; background: #1a2332;"></video></div>` : '<p style="color: var(--text-gray); font-size: 0.85rem; margin-top: 6px;">لا يوجد رابط فيديو</p>'}
                </div>
            `;
        });
    } else {
        modalContent += '<p style="color: var(--text-gray); text-align: center; padding: var(--spacing-lg);">لا توجد فيديوهات في هذا الكورس</p>';
    }

    modalContent += `
            </div>
        </div>
    `;

    document.getElementById('courseEditContent').innerHTML = modalContent;
    document.getElementById('courseEditModal').style.display = 'flex';

    // Setup file input listeners for the new video form
    setupNewVideoFileListeners();
}

function closeCourseEditModal() {
    document.getElementById('courseEditModal').style.display = 'none';
    currentCourseId = null;
}

function saveCourseChanges() {
    if (!currentCourseId) return;

    const courses = JSON.parse(localStorage.getItem('nayl_courses')) || [];
    const courseIndex = courses.findIndex(c => c.id === currentCourseId);

    if (courseIndex === -1) {
        alert('الكورس غير موجود');
        return;
    }

    // Get updated values
    const title = document.getElementById('editCourseTitle').value;
    const description = document.getElementById('editCourseDescription').value;
    const status = document.getElementById('editCourseStatus').value;

    // Update course
    courses[courseIndex].title = title;
    courses[courseIndex].description = description;
    courses[courseIndex].status = status;

    // Save to localStorage
    localStorage.setItem('nayl_courses', JSON.stringify(courses));

    // Close modal and refresh table
    closeCourseEditModal();
    renderCourses(currentCoursesPage);

    alert('تم حفظ التعديلات بنجاح');
}

async function deleteCourse(courseId) {
    if (!confirm('هل أنت متأكد من حذف هذا الكورس؟ سيتم حذف جميع الفيديوهات المرتبطة به.')) {
        return;
    }

    try {
        const token = localStorage.getItem('admin_session_token') || getCookie('admin_session_token');
        const response = await fetch('../api/admin/delete-course.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + token
            },
            credentials: 'include',
            body: JSON.stringify({ course_id: courseId })
        });

        const result = await response.json();

        if (result.success) {
            await loadAdminCourses();
            alert('تم حذف الكورس بنجاح');
        } else {
            alert('فشل الحذف: ' + (result.error || 'حدث خطأ'));
        }
    } catch (err) {
        console.error('Error deleting course:', err);
        alert('حدث خطأ أثناء الاتصال بالخادم');
    }
}

function deleteVideoFromCourse(courseId, videoId) {
    if (!confirm('هل أنت متأكد من حذف هذا الفيديو؟')) {
        return;
    }

    const courses = JSON.parse(localStorage.getItem('nayl_courses')) || [];
    const course = courses.find(c => c.id === courseId);

    if (course) {
        course.videos = course.videos.filter(v => v.id !== videoId);
        course.videosCount = course.videos.length;
        localStorage.setItem('nayl_courses', JSON.stringify(courses));

        // Refresh edit page content
        editCoursePage(courseId);
        renderCourses(currentCoursesPage);
    }
}

// Show add video form
function showAddVideoForm(courseId) {
    const form = document.getElementById('addVideoForm');
    if (form) {
        form.style.display = 'block';
        form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
}

// Hide add video form
function hideAddVideoForm() {
    const form = document.getElementById('addVideoForm');
    if (form) {
        form.style.display = 'none';
        // Reset form fields
        document.getElementById('newVideoTitle').value = '';
        document.getElementById('newVideoDescription').value = '';
        document.getElementById('newVideoThumbnail').value = '';
        document.getElementById('newVideoFile').value = '';
    }
}

// Save new video to course
function saveNewVideo(courseId) {
    const title = document.getElementById('newVideoTitle').value.trim();
    const order = parseInt(document.getElementById('newVideoOrder').value);
    const description = document.getElementById('newVideoDescription').value.trim();
    const thumbnailInput = document.getElementById('newVideoThumbnail');
    const videoFileInput = document.getElementById('newVideoFile');

    // Validation
    if (!title) {
        alert('يرجى إدخال عنوان الفيديو');
        return;
    }

    if (!description) {
        alert('يرجى إدخال وصف الفيديو');
        return;
    }

    if (!thumbnailInput.files || !thumbnailInput.files[0]) {
        alert('يرجى اختيار صورة واجهة الفيديو');
        return;
    }

    if (!videoFileInput.files || !videoFileInput.files[0]) {
        alert('يرجى اختيار ملف الفيديو');
        return;
    }

    // Get courses
    const courses = JSON.parse(localStorage.getItem('nayl_courses')) || [];
    const courseIndex = courses.findIndex(c => c.id === courseId);

    if (courseIndex === -1) {
        alert('الكورس غير موجود');
        return;
    }

    // Create new video object
    const newVideo = {
        id: Date.now(),
        title: title,
        order: order,
        thumbnail: thumbnailInput.files[0].name,
        description: description,
        fileName: videoFileInput.files[0].name,
        fileSize: (videoFileInput.files[0].size / (1024 * 1024)).toFixed(2) + ' MB'
    };

    // Add video to course
    if (!courses[courseIndex].videos) {
        courses[courseIndex].videos = [];
    }
    courses[courseIndex].videos.push(newVideo);
    courses[courseIndex].videosCount = courses[courseIndex].videos.length;

    // Save to localStorage
    localStorage.setItem('nayl_courses', JSON.stringify(courses));

    // Show success message
    alert('✓ تم إضافة الفيديو بنجاح');

    // Refresh the edit page
    editCoursePage(courseId);
    renderCourses(currentCoursesPage);

    // Hide the form
    hideAddVideoForm();
}

// Setup file input listeners for new video form
function setupNewVideoFileListeners() {
    const thumbnailInput = document.getElementById('newVideoThumbnail');
    const videoFileInput = document.getElementById('newVideoFile');

    if (thumbnailInput) {
        thumbnailInput.addEventListener('change', function () {
            const label = this.parentElement.querySelector('.file-upload-label span');
            if (this.files && this.files[0]) {
                label.textContent = this.files[0].name;
                label.style.color = 'var(--accent-green)';
            } else {
                label.textContent = 'اختر صورة الواجهة';
                label.style.color = '';
            }
        });
    }

    if (videoFileInput) {
        videoFileInput.addEventListener('change', function () {
            const label = this.parentElement.querySelector('.file-upload-label span');
            if (this.files && this.files[0]) {
                label.textContent = this.files[0].name;
                label.style.color = 'var(--accent-green)';
            } else {
                label.textContent = 'اختر ملف الفيديو';
                label.style.color = '';
            }
        });
    }
}

// Initialize courses table when videos tab is opened
document.addEventListener('DOMContentLoaded', () => {
    const menuLinks = document.querySelectorAll('.menu-link');
    menuLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            const targetTab = link.getAttribute('data-tab');
            if (targetTab === 'videos') {
                setTimeout(() => renderCourses(1), 100);
            }
        });
    });
});

// ===========================
// Course Edit Page Functions
// ===========================

async function editCoursePage(courseId) {
    currentCourseId = courseId;

    const pageContentEl = document.getElementById('courseEditPageContent');
    const uploadForm = document.querySelector('#uploadCourseForm').closest('.data-card');
    const coursesTable = document.querySelector('#coursesTable').closest('.data-card');
    const editPage = document.getElementById('courseEditPage');

    if (pageContentEl) pageContentEl.innerHTML = '<div style="text-align: center; padding: 2rem; color: var(--text-gray);">جاري تحميل تفاصيل الكورس...</div>';
    if (uploadForm) uploadForm.style.display = 'none';
    if (coursesTable) coursesTable.style.display = 'none';
    if (editPage) editPage.style.display = 'block';

    let course = null;
    try {
        const token = localStorage.getItem('admin_session_token') || getCookie('admin_session_token');
        const response = await fetch('../api/admin/get-course-details.php?course_id=' + encodeURIComponent(courseId), {
            method: 'GET',
            headers: { 'Authorization': 'Bearer ' + token },
            credentials: 'include'
        });
        const result = await response.json();
        if (result.success && result.data && result.data.course) {
            course = result.data.course;
            if (!Array.isArray(course.videos)) course.videos = [];
        }
    } catch (e) {
        console.error('Error loading course details:', e);
    }

    if (!course) {
        const courses = JSON.parse(localStorage.getItem('nayl_courses')) || [];
        course = courses.find(c => c.id === courseId);
        if (!course) {
            if (pageContentEl) pageContentEl.innerHTML = '<div style="text-align: center; padding: 2rem; color: #e74c3c;">الكورس غير موجود أو فشل تحميل التفاصيل.</div>';
            return;
        }
        if (!Array.isArray(course.videos)) course.videos = [];
    }

    let editContent = `
        <div class="course-edit-section">
            <h4>معلومات الكورس الأساسية</h4>
            <div class="admin-form-group">
                <label>عنوان الكورس</label>
                <input type="text" id="editCourseTitle" value="${escapeHtml(course.title)}" style="width: 100%; padding: 12px; border: 1px solid #e0e6ed; border-radius: var(--radius-sm); font-family: inherit;">
            </div>
            <div class="admin-form-group">
                <label>وصف الكورس</label>
                <textarea id="editCourseDescription" rows="4" style="width: 100%; padding: 12px; border: 1px solid #e0e6ed; border-radius: var(--radius-sm); font-family: inherit; resize: vertical;">${escapeHtml(course.description)}</textarea>
            </div>
            <div class="admin-form-group" style="max-width: 300px;">
                <label>حالة النشر</label>
                <select id="editCourseStatus" style="width: 100%; padding: 12px; border: 1px solid #e0e6ed; border-radius: var(--radius-sm); font-family: inherit;">
                    <option value="منشور" ${course.status === 'منشور' ? 'selected' : ''}>منشور</option>
                    <option value="مسودة" ${course.status === 'مسودة' ? 'selected' : ''}>مسودة</option>
                </select>
            </div>
        </div>

        <div class="course-edit-section">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--spacing-md);">
                <h4 style="margin: 0;">فيديوهات الكورس (${course.videos.length})</h4>
            </div>
            <div id="editVideosList">
    `;

    if (course.videos.length > 0) {
        course.videos.forEach((video, index) => {
            const thumbDisplay = video.thumbnail_url ? '<img src="' + escapeHtml(video.thumbnail_url) + '" alt="" style="max-width: 120px; max-height: 68px; border-radius: 4px;">' : '—';
            editContent += `
                <div class="video-upload-item" data-video-id="${video.id}">
                    <div class="video-item-header">
                        <h4 style="font-size: 1rem; font-weight: 700; color: var(--primary-dark); margin: 0;">
                            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; vertical-align: middle; margin-left: 6px;">
                                <path d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                                <path d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            الفيديو #${index + 1}
                        </h4>
                        <button type="button" class="remove-video-btn" onclick="deleteVideoFromCourse(${courseId}, ${video.id})">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; vertical-align: middle; margin-left: 4px;">
                                <path d="M3 6h18m-2 0v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/>
                            </svg>
                            حذف
                        </button>
                    </div>
                    <div class="video-item-fields">
                        <div class="admin-form-group">
                            <label>عنوان الفيديو</label>
                            <input type="text" class="edit-video-title" value="${escapeHtml(video.title)}" data-video-id="${video.id}">
                        </div>
                        <div class="admin-form-group">
                            <label>ترتيب الفيديو</label>
                            <input type="number" class="edit-video-order" value="${video.order}" data-video-id="${video.id}" min="1">
                        </div>
                        <div class="admin-form-group">
                            <label>صورة الواجهة الحالية</label>
                            <div style="padding: 10px; background: #f8fafc; border: 1px solid #e0e6ed; border-radius: var(--radius-sm); color: var(--text-gray); font-size: 0.9rem;">
                                ${thumbDisplay}
                            </div>
                        </div>
                        ${video.video_url ? `<div class="admin-form-group" style="grid-column: 1 / -1;"><label>مشاهدة الفيديو</label><video src="../api/admin/proxy-video.php?video_id=${video.id}" controls style="max-width: 100%; max-height: 320px; border-radius: 8px; background: #1a2332;"></video></div>` : ''}
                        <div class="admin-form-group">
                            <label>تحديث صورة الواجهة (اختياري)</label>
                            <input type="file" class="edit-video-thumbnail" data-video-id="${video.id}" accept="image/*">
                        </div>
                        <div class="admin-form-group" style="grid-column: 1 / -1;">
                            <label>تفاصيل وشرح الفيديو</label>
                            <textarea class="edit-video-description" data-video-id="${video.id}" rows="3">${escapeHtml(video.description)}</textarea>
                        </div>
                    </div>
                </div>
            `;
        });
    } else {
        editContent += '<p style="color: var(--text-gray); text-align: center; padding: var(--spacing-xl); background: #f8fafc; border-radius: var(--radius-md);">لا توجد فيديوهات في هذا الكورس.</p>';
    }

    editContent += `
            </div>
        </div>
    `;

    if (pageContentEl) pageContentEl.innerHTML = editContent;
}

function closeCourseEditPage() {
    // Show upload form and courses table, hide edit page
    const uploadForm = document.querySelector('#uploadCourseForm').closest('.data-card');
    if (uploadForm) uploadForm.style.display = 'block';
    document.querySelector('#coursesTable').closest('.data-card').style.display = 'block';
    document.getElementById('courseEditPage').style.display = 'none';
    currentCourseId = null;
}

function saveCourseChanges() {
    if (!currentCourseId) return;

    const courses = JSON.parse(localStorage.getItem('nayl_courses')) || [];
    const courseIndex = courses.findIndex(c => c.id === currentCourseId);

    if (courseIndex === -1) {
        alert('الكورس غير موجود');
        return;
    }

    // Get updated course info
    const title = document.getElementById('editCourseTitle').value;
    const description = document.getElementById('editCourseDescription').value;
    const status = document.getElementById('editCourseStatus').value;

    // Update course basic info
    courses[courseIndex].title = title;
    courses[courseIndex].description = description;
    courses[courseIndex].status = status;

    // Update videos info
    const videoItems = document.querySelectorAll('#editVideosList .video-upload-item');
    videoItems.forEach(item => {
        const videoId = parseInt(item.getAttribute('data-video-id'));
        const video = courses[courseIndex].videos.find(v => v.id === videoId);

        if (video) {
            const titleInput = item.querySelector('.edit-video-title');
            const orderInput = item.querySelector('.edit-video-order');
            const descriptionInput = item.querySelector('.edit-video-description');
            const thumbnailInput = item.querySelector('.edit-video-thumbnail');

            if (titleInput) video.title = titleInput.value;
            if (orderInput) video.order = parseInt(orderInput.value);
            if (descriptionInput) video.description = descriptionInput.value;
            if (thumbnailInput && thumbnailInput.files[0]) {
                video.thumbnail = thumbnailInput.files[0].name;
            }
        }
    });

    // Save to localStorage
    localStorage.setItem('nayl_courses', JSON.stringify(courses));

    // Close edit page and refresh table
    closeCourseEditPage();
    renderCourses(currentCoursesPage);

    alert('تم حفظ التعديلات بنجاح ✓');
}

function closeCourseEditModal() {
    document.getElementById('courseEditModal').style.display = 'none';
}

// ===========================
// Recharge Requests Functions
// ===========================

let currentRechargePage = 1;
let rechargeRequestsPerPage = 10;
let currentRechargeFilter = 'all';

// الحصول على توكن جلسة الأدمن من التخزين المحلي أو الكوكي
function getAdminSessionToken() {
    const fromStorage = localStorage.getItem('admin_session_token');
    if (fromStorage) return fromStorage;

    const value = `; ${document.cookie}`;
    const parts = value.split('; admin_session_token=');
    if (parts.length === 2) {
        return parts.pop().split(';').shift();
    }

    return null;
}

// عند استلام 401 من أي API أدمن: مسح الجلسة والتوجيه لتسجيل الدخول (انتهاء أو خمول)
function handleAdminUnauthorized() {
    document.cookie = "admin_session_token=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
    localStorage.removeItem('admin_info');
    localStorage.removeItem('admin_session_token');
    window.location.href = 'login.html';
}

// التحقق من استجابة طلب أدمن: إذا 401 يتم التوجيه لتسجيل الدخول
function checkAdminResponse(response) {
    if (response && response.status === 401) {
        handleAdminUnauthorized();
        return false;
    }
    return true;
}

async function renderRechargeRequests(page = 1, status = 'all') {
    const tbody = document.getElementById('rechargeRequestsTableBody');
    const pagination = document.getElementById('rechargeRequestsPagination');

    if (!tbody) return;

    tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; padding: 20px;">جاري تحميل البيانات...</td></tr>';

    try {
        // استخدام توكن جلسة الأدمن من التخزين المحلي أو الكوكي
        const adminToken = getAdminSessionToken();

        const headers = {};
        if (adminToken) {
            headers['Authorization'] = `Bearer ${adminToken}`;
        }

        const response = await fetch(`/api/admin/get-all-recharge-requests.php?page=${page}&limit=${rechargeRequestsPerPage}&status=${status}`, {
            method: 'GET',
            headers: headers,
            credentials: 'include'
        });

        if (!checkAdminResponse(response)) return;
        const result = await response.json();

        if (result.success) {
            const requests = result.data.requests;
            const paginationData = result.data.pagination;

            tbody.innerHTML = '';

            if (requests.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; padding: 20px;">لا توجد طلبات شحن</td></tr>';
                if (pagination) pagination.innerHTML = '';
                return;
            }

            requests.forEach(req => {
                const row = document.createElement('tr');
                const paymentMethod = req.payment_method === 'vodafone_cash' ? 'فودافون كاش' : (req.payment_method === 'instapay' ? 'إنستا باي' : req.payment_method);
                const statusBadge = getStatusBadge(req.status);

                // تنسيق المبلغ
                const formattedAmount = parseFloat(req.amount).toFixed(2);

                row.innerHTML = `
                    <td style="font-family: monospace; font-weight: bold;">#${req.id}</td>
                    <td>
                        <div class="user-cell">
                             <div class="user-cell-avatar">${req.user_name ? req.user_name.substring(0, 2) : '??'}</div>
                             <div style="display: flex; flex-direction: column;">
                                <span>${req.user_name || 'غير معروف'}</span>
                                <span style="font-size: 0.8em; color: gray;">${req.user_phone || ''}</span>
                             </div>
                        </div>
                    </td>
                    <td><span style="font-family: inherit; font-weight: 600; color: #2d3748;">${req.account_number || '-'}</span></td>
                    <td style="font-weight: bold; color: var(--primary-dark);">${formattedAmount} ج.م</td>
                    <td>${paymentMethod}</td>
                    <td style="direction: ltr; text-align: right;">${new Date(req.created_at).toLocaleString('ar-EG')}</td>
                    <td>${statusBadge}</td>
                    <td>
                        <div class="actions-cell" style="justify-content: center;">
                            ${req.transaction_image ? `
                            <button class="action-btn btn-view" onclick="viewReceipt('${req.transaction_image}')" title="عرض الإيصال">
                                <i class="bi bi-image"></i>
                            </button>` : ''}
                            
                            ${req.status === 'pending' ? `
                            <button class="action-btn btn-view" style="color: #2ecc71; background: rgba(46, 204, 113, 0.1);" onclick="updateRequestStatus(${req.id}, 'approved')" title="قبول">
                                <i class="bi bi-check-lg"></i>
                            </button>
                            <button class="action-btn btn-delete" onclick="openRejectModal(${req.id}, '${req.amount}', '${req.user_name || 'غير معروف'}')" title="رفض">
                                <i class="bi bi-x-lg"></i>
                            </button>` : ''}
                        </div>
                    </td>
                `;
                tbody.appendChild(row);
            });

            // Render pagination
            if (pagination) {
                renderPaginationGeneric(pagination, page, paginationData.total_pages, paginationData.total_records, rechargeRequestsPerPage, 'changeRechargePage');
            }

            currentRechargePage = page;
            currentRechargeFilter = status;

        } else {
            tbody.innerHTML = `<tr><td colspan="8" style="text-align: center; color: red;">${result.error || 'حدث خطأ'}</td></tr>`;
        }
    } catch (error) {
        console.error("Error fetching requests:", error);
        tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; color: red;">حدث خطأ في الاتصال بالسيرفر</td></tr>';
    }
}

function getStatusBadge(status) {
    switch (status) {
        case 'pending': return '<span class="badge badge-pending">قيد المراجعة</span>';
        case 'approved': return '<span class="badge badge-active">تم الموافقة</span>';
        case 'rejected': return '<span class="badge" style="background: rgba(220, 53, 69, 0.1); color: #dc3545;">مرفوض</span>';
        case 'cancelled': return '<span class="badge" style="background: #eee; color: #666;">ملغي</span>';
        default: return `<span class="badge">${status}</span>`;
    }
}

function changeRechargePage(page) {
    renderRechargeRequests(page, currentRechargeFilter);
}

function filterRechargeRequests(status) {
    currentRechargeFilter = status;
    renderRechargeRequests(1, status);
}

function viewReceipt(imageUrl) {
    const modal = document.getElementById('receiptModal');
    const img = document.getElementById('newReceiptImage');
    const downloadBtn = document.getElementById('downloadReceiptBtn');
    const loadingState = document.getElementById('receiptLoadingState');
    const errorState = document.getElementById('receiptErrorState');

    if (modal && img) {
        // Reset state
        if (loadingState) loadingState.style.display = 'block';
        if (errorState) errorState.style.display = 'none';
        img.style.display = 'none';

        // NOTE: onload/onerror are handled in HTML, but we set them here just in case of race conditions
        // or if the HTML handlers don't fire for some reason (though they should).
        // Actually, setting src triggers loading, so the HTML handlers are best.
        // We just ensure initial visibility here.

        // Determine URL
        let finalUrl = imageUrl;
        if (!imageUrl.startsWith('http') && !imageUrl.startsWith('data:')) {
            finalUrl = imageUrl.startsWith('../') ? imageUrl : '../' + imageUrl;
        }

        // Set Image Source
        img.src = finalUrl;

        // Set Download Link
        if (downloadBtn) {
            downloadBtn.href = finalUrl;
            // Update download filename based on URL if possible
            const filename = finalUrl.split('/').pop();
            if (filename) downloadBtn.download = filename;
        }

        // Show Modal
        modal.style.display = 'flex';
        requestAnimationFrame(() => modal.classList.add('active'));
    } else {
        console.error('Receipt Modal elements not found');
        // Fallback
        const finalUrl = imageUrl.startsWith('../') ? imageUrl : '../' + imageUrl;
        window.open(finalUrl, '_blank');
    }
}

window.closeReceiptModal = function () {
    const modal = document.getElementById('receiptModal');
    if (modal) {
        modal.classList.remove('active');
        setTimeout(() => {
            if (!modal.classList.contains('active')) modal.style.display = 'none';
        }, 300);

        // Clear src to stop memory leak
        const img = document.getElementById('newReceiptImage');
        if (img) img.src = '';
    }
};

// Close when clicking outside image
document.addEventListener('click', (e) => {
    if (e.target.id === 'receiptModal') {
        window.closeReceiptModal();
    }
});

// نسخ الكود عند النقر على خلية الكود
document.addEventListener('click', (e) => {
    const cell = e.target.closest('.copyable-code-cell');
    if (!cell) return;
    const code = cell.dataset.code;
    if (!code) return;
    navigator.clipboard.writeText(code).then(() => {
        const icon = cell.querySelector('.bi-clipboard');
        if (icon) {
            icon.classList.remove('bi-clipboard');
            icon.classList.add('bi-check-lg');
            icon.style.color = '#4CAF50';
            setTimeout(() => {
                icon.classList.remove('bi-check-lg');
                icon.classList.add('bi-clipboard');
                icon.style.color = '';
            }, 1500);
        }
    }).catch(() => alert('تعذر النسخ'));
});


// ==========================================
// Notification System (Browser & Dropdown)
// ==========================================

let knownNotificationIds = new Set();
let notificationPollInterval = null;

document.addEventListener('DOMContentLoaded', () => {
    // Check Notification API support
    if ("Notification" in window) {
        if (Notification.permission !== "granted" && Notification.permission !== "denied") {
            Notification.requestPermission();
        }
    }

    // Initial fetch
    fetchNotifications();

    // Start Polling (every 30 seconds)
    notificationPollInterval = setInterval(fetchNotifications, 30000);

    // Close dropdown when clicking outside
    document.addEventListener('click', (e) => {
        const wrapper = document.querySelector('.notifications-wrapper');
        const dropdown = document.getElementById('notificationsDropdown');
        if (wrapper && dropdown && !wrapper.contains(e.target)) {
            dropdown.style.display = 'none';
        }
    });
});

function toggleNotifications() {
    const dropdown = document.getElementById('notificationsDropdown');
    const isVisible = dropdown.style.display === 'block';
    dropdown.style.display = isVisible ? 'none' : 'block';

    // Refresh if opening
    if (!isVisible) fetchNotifications();
}

async function fetchNotifications() {
    try {
        const token = localStorage.getItem('admin_session_token') || getCookie('admin_session_token');
        if (!token) return;

        const response = await fetch('../api/admin/notifications/get-all.php', {
            headers: { 'Authorization': 'Bearer ' + token }
        });
        const result = await response.json();

        if (result.success) {
            updateNotificationUI(result.data, result.unread_count);
            checkForNewNotifications(result.data);
        }
    } catch (e) {
        console.error('Failed to fetch notifications', e);
    }
}

function updateNotificationUI(notifications, unreadCount) {
    const badge = document.getElementById('notificationBadge');
    const list = document.getElementById('notificationList');

    // Update Badge
    if (badge) {
        badge.textContent = unreadCount;
        badge.style.display = unreadCount > 0 ? 'block' : 'none';

        // Change color based on count if many
        if (unreadCount > 9) badge.style.backgroundColor = '#ff0000';
    }

    // Update List
    if (list) {
        if (notifications.length === 0) {
            list.innerHTML = '<li class="empty-notif" style="padding: 20px; text-align: center; color: #888;">لا توجد إشعارات جديدة</li>';
        } else {
            list.innerHTML = notifications.map(n => `
                <li class="notif-item-li" style="position: relative; padding: 12px 16px; border-bottom: 1px solid #eee; transition: background 0.2s;">
                    <div class="notif-item" style="display: flex; gap: 12px;">
                        <div class="notif-icon" style="background: ${n.type === 'recharge_request' ? '#e3f2fd' : '#f3e5f5'}; color: ${n.type === 'recharge_request' ? '#2196f3' : '#9c27b0'}; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                            <i class="bi ${n.type === 'recharge_request' ? 'bi-cash-coin' : 'bi-bell-fill'}"></i>
                        </div>
                        <div class="notif-content" style="flex: 1;">
                            <div class="notif-title" style="font-weight: 600; font-size: 0.9rem; margin-bottom: 4px;">${n.message}</div>
                            <div class="notif-time" style="font-size: 0.75rem; color: #888;">${new Date(n.created_at).toLocaleString('ar-EG')}</div>
                        </div>
                        <button onclick="deleteNotification(${n.id}, event)" class="delete-notif-btn" title="حذف الإشعار">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </li>
            `).join('');
        }
    }
}

function checkForNewNotifications(notifications) {
    let hasNew = false;
    notifications.forEach(n => {
        if (!knownNotificationIds.has(n.id)) {
            hasNew = true;
            knownNotificationIds.add(n.id);

            // Trigger Browser Notification if permission granted
            if (Notification.permission === "granted") {
                new Notification("AMR NAYL Academy Admin", {
                    body: n.message,
                    icon: "../pics/cp.png" // Ensure this path is correct
                });
            }
        }
    });

    // Determine if we should play sound (only if actual new items appeared since page load)
    // We can use a simple Audio object if sound is needed.
}

async function deleteNotification(id, event) {
    if (event) event.stopPropagation();

    if (!confirm('حذف هذا الإشعار؟')) return;

    try {
        const token = localStorage.getItem('admin_session_token') || getCookie('admin_session_token');
        await fetch('../api/admin/notifications/delete.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + token
            },
            body: JSON.stringify({ id: id, type: 'single' })
        });

        // Remove from Set to allow re-notification if it reappears (unlikely) or just cleanup
        knownNotificationIds.delete(id);
        fetchNotifications();
    } catch (e) {
        console.error(e);
    }
}

async function clearAllNotifications() {
    if (!confirm('هل أنت متأكد من حذف جميع الإشعارات؟')) return;

    try {
        const token = localStorage.getItem('admin_session_token') || getCookie('admin_session_token');
        await fetch('../api/admin/notifications/delete.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + token
            },
            body: JSON.stringify({ type: 'all' })
        });

        knownNotificationIds.clear();
        fetchNotifications();
    } catch (e) {
        console.error(e);
    }
}

// Reject Modal Globals
let currentRejectionRequestId = null;

function openRejectModal(id, amount, userName) {
    currentRejectionRequestId = id;
    const modal = document.getElementById('rejectRechargeModal');
    const amountEl = document.getElementById('rejectAmount');
    const userEl = document.getElementById('rejectUserName');
    const textEl = document.getElementById('rejectionReason');

    if (modal) {
        if (amountEl) amountEl.textContent = parseFloat(amount).toFixed(2);
        if (userEl) userEl.textContent = userName;
        if (textEl) textEl.value = ''; // Reset reason

        modal.style.display = 'flex';
        requestAnimationFrame(() => modal.classList.add('active'));
    }
}

function closeRejectModal() {
    const modal = document.getElementById('rejectRechargeModal');
    if (modal) {
        modal.classList.remove('active');
        setTimeout(() => {
            if (!modal.classList.contains('active')) modal.style.display = 'none';
        }, 300);
        currentRejectionRequestId = null;
    }
}

function confirmRejectRecharge() {
    const reasonEl = document.getElementById('rejectionReason');
    const reason = reasonEl ? reasonEl.value.trim() : '';

    if (!reason) {
        alert('يرجى كتابة سبب الرفض');
        return;
    }

    if (currentRejectionRequestId) {
        updateRequestStatus(currentRejectionRequestId, 'rejected', reason);
        closeRejectModal();
    }
}

async function updateRequestStatus(id, newStatus, adminNotes = null) {
    const isApprove = newStatus === 'approved';
    const confirmMessage = isApprove
        ? 'هل أنت متأكد من قبول هذا الطلب وإضافة الرصيد إلى محفظة المستخدم؟'
        : 'هل أنت متأكد من رفض هذا الطلب؟';

    // Only ask for confirmation if it's NOT a rejection flow (rejection already confirmed via modal)
    // Or if it IS a rejection flow but coming from somewhere else (though we now use modal)
    // Actually, simple check: if adminNotes is provided, it's coming from the modal, so skip confirm.
    if (!adminNotes && !confirm(confirmMessage)) {
        return;
    }

    const adminToken = getAdminSessionToken();
    if (!adminToken) {
        alert('انتهت صلاحية جلسة الأدمن، يرجى تسجيل الدخول مرة أخرى.');
        window.location.href = 'login.html';
        return;
    }

    try {
        const payload = {
            request_id: id,
            status: newStatus
        };

        if (adminNotes) {
            payload.admin_notes = adminNotes;
        }

        const response = await fetch('/api/admin/update-recharge-request.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${adminToken}`
            },
            credentials: 'include',
            body: JSON.stringify(payload)
        });

        const result = await response.json();

        if (result.success) {
            alert(isApprove
                ? 'تم قبول الطلب وتم إضافة الرصيد إلى محفظة المستخدم بنجاح ✓'
                : 'تم رفض الطلب وتسجيل السبب بنجاح');

            // إعادة تحميل الطلبات بنفس الفلتر والصفحة الحالية
            renderRechargeRequests(currentRechargePage, currentRechargeFilter);

            // تحديث عداد الطلبات المعلقة
            if (typeof updatePendingRechargeCount === 'function') {
                updatePendingRechargeCount();
            }
        } else {
            alert(result.error || 'حدث خطأ أثناء تحديث حالة الطلب');
        }
    } catch (error) {
        console.error('Error updating recharge request status:', error);
        alert('حدث خطأ في الاتصال بالسيرفر أثناء تحديث حالة الطلب');
    }
}

// Initial load for recharge requests tab
document.addEventListener('DOMContentLoaded', () => {
    const menuLinks = document.querySelectorAll('.menu-link');
    menuLinks.forEach(link => {
        link.addEventListener('click', () => {
            const targetTab = link.getAttribute('data-tab');
            if (targetTab === 'recharge-requests') {
                renderRechargeRequests(1, currentRechargeFilter);
            }
        });
    });
});


// ===========================
// Sidebar Badge Logic
// ===========================

async function updatePendingRechargeCount() {
    const badge = document.getElementById('pendingRechargeCountBadge');
    if (!badge) return;

    try {
        const adminToken = getAdminSessionToken();
        const headers = {};
        if (adminToken) {
            headers['Authorization'] = `Bearer ${adminToken}`;
        }

        // Use relative path matching other calls with cache busting
        const response = await fetch('../api/admin/get-pending-recharge-count.php?_t=' + new Date().getTime(), {
            method: 'GET',
            headers: headers,
            credentials: 'include'
        });

        const text = await response.text();
        console.log('Raw pending count response:', text);

        let result;
        try {
            result = JSON.parse(text);
        } catch (e) {
            console.error('Failed to parse JSON:', e);
            console.error('Response text was:', text);
            return;
        }

        console.log('Pending count result:', result);

        if (result.success && result.data && result.data.count !== undefined) {
            const count = result.data.count;
            badge.textContent = count;
            badge.style.display = 'inline-block';

            // Debug: Log the debug breakdown we added in PHP
            if (result.data.debug_breakdown) {
                console.log('Recharge Status Breakdown:', result.data.debug_breakdown);
            }

            if (parseInt(count) > 0) {
                badge.style.backgroundColor = '#ff4d4d'; // Red
                badge.style.color = '#fff';
            } else {
                badge.style.backgroundColor = '#e2e8f0'; // Gray
                badge.style.color = '#4a5568';
            }
        } else {
            console.error('Pending count API returned failure:', result);
            if (result.error) console.error('Error message:', result.error);
        }
    } catch (error) {
        console.error("Error fetching pending count:", error);
    }
}

// Initial Call
document.addEventListener('DOMContentLoaded', updatePendingRechargeCount);
// Also update every 60 seconds
setInterval(updatePendingRechargeCount, 60000);

// ===========================
// Visitor Stats
// ===========================
function loadSiteVisitorStats() {
    const el = document.getElementById('siteVisitorsCount');
    if (!el) return;

    // Use token from cookie or localStorage
    function getCookie(name) {
        let matches = document.cookie.match(new RegExp(
            "(?:^|; )" + name.replace(/([\.$?*|{}\(\)\[\]\\\/\+^])/g, '\\$1') + "=([^;]*)"
        ));
        return matches ? decodeURIComponent(matches[1]) : undefined;
    }
    const token = localStorage.getItem('admin_session_token') || getCookie('admin_session_token');

    if (!token) return;

    const monthlySalesEl = document.getElementById('monthlySalesTotal');

    fetch('../api/admin/get-site-stats.php', {
        headers: {
            'Authorization': 'Bearer ' + token
        }
    })
        .then(res => res.json())
        .then(data => {
            if (data.success && data.data) {
                el.textContent = data.data.visitor_count;
                if (monthlySalesEl) {
                    const total = Number(data.data.monthly_sales_total) || 0;
                    monthlySalesEl.textContent = 'EGP ' + total.toLocaleString('en-EG', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
                }
            } else {
                el.textContent = '-';
                if (monthlySalesEl) monthlySalesEl.textContent = '-';
            }
        })
        .catch(e => {
            console.error('Stats error:', e);
            el.textContent = '-';
            if (monthlySalesEl) monthlySalesEl.textContent = '-';
        });
}
