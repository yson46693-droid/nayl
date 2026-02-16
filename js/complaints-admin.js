document.addEventListener('DOMContentLoaded', function () {
    // Check if we are on the complaints tab or if it's the default
    const complaintsTab = document.querySelector('[data-tab="complaints"]');
    if (complaintsTab) {
        complaintsTab.addEventListener('click', function () {
            loadComplaints();
        });

        // If already active (unlikely on reload unless state is saved, but good practice)
        if (complaintsTab.classList.contains('active')) {
            loadComplaints();
        }
    }
});

let currentComplaintsPage = 1;
const complaintsLimit = 10;
let complaintsTotalPages = 0;

async function loadComplaints() {
    const tbody = document.getElementById('complaintsTableBody');
    if (!tbody) return;

    tbody.innerHTML = '<tr><td colspan="5" style="text-align: center;">جاري تحميل الشكاوي...</td></tr>';

    try {
        const adminToken = localStorage.getItem('admin_session_token');
        const headers = {};
        if (adminToken) {
            headers['Authorization'] = `Bearer ${adminToken}`;
        }

        const response = await fetch(`../api/admin/get-all-complaints.php?page=${currentComplaintsPage}&limit=${complaintsLimit}`, {
            method: 'GET',
            headers: headers,
            credentials: 'include'
        });
        const result = await response.json();

        if (result.success && result.data.complaints.length > 0) {
            complaintsTotalPages = result.data.pagination.total_pages;
            renderComplaintsTable(result.data.complaints);
            renderComplaintsPagination();
        } else {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align: center;">لا توجد شكاوي حالياً</td></tr>';
            document.getElementById('complaintsPagination').style.display = 'none';
        }

    } catch (error) {
        console.error('Error loading complaints:', error);
        tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; color: red;">حدث خطأ أثناء تحميل الشكاوي</td></tr>';
    }
}

function renderComplaintsPagination() {
    const paginationContainer = document.getElementById('complaintsPagination');
    const paginationNumbers = document.getElementById('complaints-pagination-numbers');
    const prevBtn = document.getElementById('complaints-prev-page');
    const nextBtn = document.getElementById('complaints-next-page');

    if (!paginationContainer || !paginationNumbers || !prevBtn || !nextBtn) return;

    if (complaintsTotalPages <= 1) {
        paginationContainer.style.display = 'none';
        return;
    }

    paginationContainer.style.display = 'flex';

    // Buttons state
    prevBtn.disabled = currentComplaintsPage === 1;
    nextBtn.disabled = currentComplaintsPage === complaintsTotalPages;

    // Events (Clone to remove listeners)
    const newPrevBtn = prevBtn.cloneNode(true);
    const newNextBtn = nextBtn.cloneNode(true);

    prevBtn.parentNode.replaceChild(newPrevBtn, prevBtn);
    nextBtn.parentNode.replaceChild(newNextBtn, nextBtn);

    newPrevBtn.addEventListener('click', () => {
        if (currentComplaintsPage > 1) {
            currentComplaintsPage--;
            loadComplaints();
        }
    });

    newNextBtn.addEventListener('click', () => {
        if (currentComplaintsPage < complaintsTotalPages) {
            currentComplaintsPage++;
            loadComplaints();
        }
    });

    // Render Numbers
    let html = '';

    // Simple pagination logic (like 1 .. 3 4 5 .. 10) or just all if few
    if (complaintsTotalPages <= 7) {
        for (let i = 1; i <= complaintsTotalPages; i++) {
            html += `<span class="page-number ${i === currentComplaintsPage ? 'active' : ''}" onclick="goToComplaintsPage(${i})">${i}</span>`;
        }
    } else {
        // Complex logic can be added here if needed, keeping it simple for now or showing limited range
        // Just showing current, prev, next, first, last

        if (currentComplaintsPage > 2) {
            html += `<span class="page-number" onclick="goToComplaintsPage(1)">1</span>`;
            if (currentComplaintsPage > 3) html += `<span class="dots">...</span>`;
        }

        for (let i = Math.max(1, currentComplaintsPage - 1); i <= Math.min(complaintsTotalPages, currentComplaintsPage + 1); i++) {
            html += `<span class="page-number ${i === currentComplaintsPage ? 'active' : ''}" onclick="goToComplaintsPage(${i})">${i}</span>`;
        }

        if (currentComplaintsPage < complaintsTotalPages - 1) {
            if (currentComplaintsPage < complaintsTotalPages - 2) html += `<span class="dots">...</span>`;
            html += `<span class="page-number" onclick="goToComplaintsPage(${complaintsTotalPages})">${complaintsTotalPages}</span>`;
        }
    }

    paginationNumbers.innerHTML = html;
}

function goToComplaintsPage(page) {
    if (page === currentComplaintsPage) return;
    currentComplaintsPage = page;
    loadComplaints();
}

function renderComplaintsTable(complaints) {
    const tbody = document.getElementById('complaintsTableBody');
    if (!tbody) return;

    let html = '';

    complaints.forEach(complaint => {
        const statusClass = getStatusBadgeClass(complaint.status);
        const statusText = getStatusLabel(complaint.status);
        const date = new Date(complaint.created_at).toLocaleDateString('ar-EG');

        html += `
            <tr>
                <td>
                     <div class="user-cell">
                        <div class="user-cell-avatar" style="background: #e2e8f0; color: #64748b;">${complaint.user_name.substring(0, 2)}</div>
                        <div>    
                            <div style="font-weight: bold;">${complaint.user_name}</div>
                            <div style="font-size: 0.8rem; color: #888;">${complaint.user_phone}</div>
                        </div>
                    </div>
                </td>
                <td>
                    <div style="font-weight: 600;">${complaint.subject}</div>
                    <div style="font-size: 0.9rem; color: #555; max-width: 300px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${complaint.message}</div>
                </td>
                <td>${date}</td>
                <td><span class="badge ${statusClass}">${statusText}</span></td>
                <td>
                    ${complaint.status !== 'closed' ? `
                    <button class="btn btn-primary btn-with-icon" 
                        style="padding: 6px 12px; font-size: 0.75rem;" 
                        onclick="openReplyModal(${complaint.id}, '${escapeHtml(complaint.message)}', '${escapeHtml(complaint.admin_reply || '')}')">
                        <i class="bi bi-reply-fill"></i>
                        <span>${complaint.status === 'replied' ? 'تعديل الرد' : 'رد'}</span>
                    </button>
                    ` : ''}
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
}

function getStatusBadgeClass(status) {
    switch (status) {
        case 'replied': return 'badge-active'; // Greenish
        case 'closed': return 'badge-inactive'; // Gray
        default: return 'badge-pending'; // Yellow/Orange
    }
}

function getStatusLabel(status) {
    switch (status) {
        case 'replied': return 'تم الرد';
        case 'closed': return 'مغلق';
        default: return 'جديد';
    }
}

function openReplyModal(id, message, currentReply) {
    const modal = document.getElementById('replyComplaintModal');
    const idInput = document.getElementById('replyComplaintId');
    const msgDisplay = document.getElementById('complaintTextDisplay');
    const replyInput = document.getElementById('replyText');

    if (modal && idInput && msgDisplay && replyInput) {
        idInput.value = id;
        msgDisplay.textContent = message;
        replyInput.value = currentReply || '';

        modal.classList.add('active'); // active class is used for display block in css usually
        modal.style.display = 'flex'; // Enforce flex
    }
}

function closeReplyModal() {
    const modal = document.getElementById('replyComplaintModal');
    if (modal) {
        modal.classList.remove('active');
        modal.style.display = 'none';
    }
}

async function submitReply() {
    const button = document.querySelector('#replyComplaintModal .btn-primary');
    const originalText = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<i class="bi bi-hourglass-split"></i> جاري الإرسال...';

    const id = document.getElementById('replyComplaintId').value;
    const reply = document.getElementById('replyText').value;

    if (!reply) {
        alert('الرجاء كتابة الرد');
        button.disabled = false;
        button.innerHTML = originalText;
        return;
    }

    try {
        const adminToken = localStorage.getItem('admin_session_token');
        const headers = {
            'Content-Type': 'application/json'
        };
        if (adminToken) {
            headers['Authorization'] = `Bearer ${adminToken}`;
        }

        const response = await fetch('../api/admin/reply-complaint.php', {
            method: 'POST',
            headers: headers,
            credentials: 'include',
            body: JSON.stringify({
                complaint_id: id,
                reply: reply
            })
        });

        const result = await response.json();

        if (result.success) {
            alert('تم الرد بنجاح');
            closeReplyModal();
            loadComplaints(); // Refresh list
        } else {
            alert(result.error || 'حدث خطأ أثناء الرد');
        }

    } catch (error) {
        console.error('Error replying:', error);
        alert('حدث خطأ في الاتصال');
    } finally {
        button.disabled = false;
        button.innerHTML = originalText;
    }
}

// Helper to escape HTML to prevent XSS in onclick attributes
function escapeHtml(text) {
    if (!text) return '';
    return text
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}
