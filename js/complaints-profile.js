document.addEventListener('DOMContentLoaded', function () {
    // Initialize complaints functionality
    initComplaints();
});

function initComplaints() {
    const complaintTabBtn = document.querySelector('[data-tab="complaints"]');

    // Listen for tab click to load complaints
    if (complaintTabBtn) {
        complaintTabBtn.addEventListener('click', function () {
            loadComplaints();
        });
    }

    // Handle form submission
    const complaintForm = document.getElementById('complaint-form');
    if (complaintForm) {
        complaintForm.addEventListener('submit', handleComplaintSubmit);
    }
}

// Toggle form visibility
function toggleComplaintForm() {
    const section = document.getElementById('new-complaint-section');
    if (section) {
        if (section.style.display === 'none') {
            section.style.display = 'block';
            // Scroll to the section
            section.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } else {
            section.style.display = 'none';
            // Reset form when closing
            const form = document.getElementById('complaint-form');
            if (form) form.reset();
        }
    }
}

async function handleComplaintSubmit(e) {
    e.preventDefault();

    const subjectBtn = document.querySelector('#complaint-form button[type="submit"]');
    const originalText = subjectBtn.innerHTML; // Use innerHTML to preserve icon
    subjectBtn.disabled = true;
    subjectBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> جاري الإرسال...';

    const subject = document.getElementById('complaint-subject').value;
    const message = document.getElementById('complaint-message').value;

    const sessionToken = localStorage.getItem('sessionToken') || sessionStorage.getItem('sessionToken');

    try {
        const response = await fetch('/api/complaints/create.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${sessionToken}`
            },
            body: JSON.stringify({
                subject: subject,
                message: message
            })
        });

        const result = await response.json();

        if (result.success) {
            alert('تم إرسال الشكوى بنجاح');
            toggleComplaintForm(); // Hide form
            loadComplaints(); // Reload list
        } else {
            alert(result.error || 'حدث خطأ أثناء إرسال الشكوى');
        }

    } catch (error) {
        console.error('Error submitting complaint:', error);
        alert('حدث خطأ في الاتصال');
    } finally {
        subjectBtn.disabled = false;
        subjectBtn.innerHTML = originalText;
    }
}

async function loadComplaints() {
    const container = document.getElementById('complaints-container');
    if (!container) return;

    const sessionToken = localStorage.getItem('sessionToken') || sessionStorage.getItem('sessionToken');

    if (!sessionToken) {
        container.innerHTML = '<p class="text-center">يجب عليك تسجيل الدخول لرؤية الشكاوي.</p>';
        return;
    }

    container.innerHTML = '<div class="empty-state"><p>جاري تحميل الشكاوي...</p></div>';

    try {
        const response = await fetch('/api/complaints/get-my-complaints.php', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${sessionToken}`
            }
        });

        const result = await response.json();

        if (result.success && result.data.complaints.length > 0) {
            renderComplaints(result.data.complaints);
        } else {
            container.innerHTML = `
                <div class="empty-state">
                    <i class="bi bi-chat-square-text" style="font-size: 2rem; margin-bottom: 10px; color: #ccc;"></i>
                    <p>لا توجد شكاوي سابقة.</p>
                </div>
            `;
        }

    } catch (error) {
        console.error('Error loading complaints:', error);
        container.innerHTML = '<p class="text-center text-danger">حدث خطأ أثناء تحميل الشكاوي.</p>';
    }
}

function renderComplaints(complaints) {
    const container = document.getElementById('complaints-container');

    const html = complaints.map(complaint => {
        const statusClass = complaint.status === 'replied' ? 'success' : (complaint.status === 'closed' ? 'secondary' : 'warning');
        const statusText = complaint.status === 'replied' ? 'تم الرد' : (complaint.status === 'closed' ? 'مغلق' : 'قيد المراجعة');
        const date = new Date(complaint.created_at).toLocaleDateString('ar-EG');

        return `
            <div class="transaction-item-premium" style="display: block;">
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
                    <div>
                        <h4 class="transaction-title-premium" style="margin-bottom: 5px;">${complaint.subject}</h4>
                        <span class="status-badge status-${statusClass}" style="padding: 2px 8px; border-radius: 12px; font-size: 0.8rem; background: ${getStatusColor(complaint.status)}; color: white;">
                            ${statusText}
                        </span>
                        <span style="font-size: 0.8rem; color: #888; margin-right: 10px;">${date}</span>
                    </div>
                </div>
                <div style="background: #f8f9fa; padding: 10px; border-radius: 8px; margin-bottom: 10px; font-size: 0.95rem;">
                    ${complaint.message}
                </div>
                
                ${complaint.admin_reply ? `
                    <div style="background: #e6f7ff; border: 1px solid #91d5ff; padding: 10px; border-radius: 8px; margin-top: 10px;">
                        <h5 style="color: #0050b3; margin-bottom: 5px; font-size: 0.9rem;"><i class="bi bi-person-check-fill"></i> رد الإدارة</h5>
                        <p style="margin: 0; font-size: 0.95rem;">${complaint.admin_reply}</p>
                    </div>
                ` : ''}
            </div>
        `;
    }).join('');

    container.innerHTML = html;
}

function getStatusColor(status) {
    switch (status) {
        case 'replied': return '#10b981'; // green
        case 'closed': return '#6b7280'; // gray
        default: return '#f59e0b'; // orange (open)
    }
}
