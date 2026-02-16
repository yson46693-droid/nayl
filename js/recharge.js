// ===========================
// Recharge Page JavaScript
// ===========================

// Modal functions
// Modal functions
function openModal(type) {
    if (type === 'success') {
        const successContainer = document.getElementById('success-message-container');
        const paymentSection = document.querySelector('.payment-section');
        const instructionsSection = document.querySelector('.instructions-section');
        const rechargeTitle = document.querySelector('.recharge-title');
        const rechargeSubtitle = document.querySelector('.recharge-subtitle');

        if (successContainer) {
            // Hide other sections
            if (paymentSection) paymentSection.style.display = 'none';
            if (instructionsSection) instructionsSection.style.display = 'none';
            if (rechargeTitle) rechargeTitle.style.display = 'none';
            if (rechargeSubtitle) rechargeSubtitle.style.display = 'none';

            // Hide terms links if present
            const termsLinks = document.querySelector('.terms-links');
            if (termsLinks) termsLinks.style.display = 'none';

            // Hide any active policy cards
            document.querySelectorAll('.policy-card').forEach(card => {
                card.style.display = 'none';
                card.classList.remove('active');
            });

            // Show success message
            successContainer.style.display = 'block';
            successContainer.style.opacity = '1';
            successContainer.style.visibility = 'visible';

            // Scroll to view
            setTimeout(() => {
                successContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }, 100);
        }
        return;
    }

    // Previous modal logic for terms/privacy
    let modalId;
    if (type === 'terms') modalId = 'terms-modal';
    else if (type === 'privacy') modalId = 'privacy-modal';

    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(type) {
    if (type === 'success') {
        // Reload page to reset state or redirect
        window.location.reload();
        return;
    }

    let modalId;
    if (type === 'terms') modalId = 'terms-modal';
    else if (type === 'privacy') modalId = 'privacy-modal';

    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

// Toggle Policy Cards
// Toggle Policy Cards
function togglePolicy(type) {
    const contentId = type === 'terms' ? 'terms-content' : 'privacy-content';
    const content = document.getElementById(contentId);

    // Close other policy if open
    const otherType = type === 'terms' ? 'privacy' : 'terms';
    const otherContent = document.getElementById(otherType === 'terms' ? 'terms-content' : 'privacy-content');
    if (otherContent && otherContent.classList.contains('active')) {
        otherContent.classList.remove('active');
    }

    // Toggle current policy
    if (content) {
        // Ensure display inline style is cleared so CSS class handles visibility
        content.style.display = '';

        const isActive = content.classList.toggle('active');

        if (isActive) {
            setTimeout(() => {
                content.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }, 300);
        }
    }
}

// Toggle instructions section
function toggleInstructions() {
    const section = document.querySelector('.instructions-section');
    const list = document.querySelector('.instructions-list');

    section.classList.toggle('active');

    if (list.style.display === 'none' || !list.style.display) {
        list.style.display = 'block';
    } else {
        list.style.display = 'none';
    }
}

// Copy to clipboard function
function copyToClipboard(text) {
    // Try using the modern clipboard API
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text)
            .then(() => {
                showCopyNotification('تم النسخ!', `تم نسخ الرقم ${text} بنجاح`, 'success');
            })
            .catch(err => {
                // Fallback to old method
                copyToClipboardFallback(text);
            });
    } else {
        // Fallback for older browsers
        copyToClipboardFallback(text);
    }
}

// Fallback copy method for older browsers
function copyToClipboardFallback(text) {
    const textArea = document.createElement('textarea');
    textArea.value = text;
    textArea.style.position = 'fixed';
    textArea.style.left = '-9999px';
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();

    try {
        const successful = document.execCommand('copy');
        if (successful) {
            showCopyNotification('تم النسخ!', `تم نسخ الرقم ${text} بنجاح`, 'success');
        } else {
            showCopyNotification('خطأ', 'فشل نسخ الرقم، يرجى النسخ يدوياً', 'error');
        }
    } catch (err) {
        showCopyNotification('خطأ', 'فشل نسخ الرقم، يرجى النسخ يدوياً', 'error');
    }

    document.body.removeChild(textArea);
}

// Show copy notification
function showCopyNotification(title, message, type) {
    const notification = document.createElement('div');
    notification.className = `copy-notification copy-notification-${type}`;
    notification.innerHTML = `
        <div class="copy-notification-content">
            ${type === 'success' ?
            '<svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17L4 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>' :
            '<svg width="24" height="24" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/><path d="M12 8V12M12 16H12.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>'
        }
            <div>
                <strong>${title}</strong>
                <p>${message}</p>
            </div>
        </div>
    `;

    document.body.appendChild(notification);

    setTimeout(() => {
        notification.classList.add('show');
    }, 100);

    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 3000);
}

// Toggle payment method form
// Toggle payment method form
function togglePaymentMethod(method) {
    const paymentMethods = document.querySelectorAll('.payment-method');
    const targetMethod = document.querySelector(`[data-method="${method}"]`);
    const targetForm = targetMethod.querySelector('.payment-form');

    // Close all other methods
    paymentMethods.forEach(pm => {
        if (pm.getAttribute('data-method') !== method) {
            pm.classList.remove('active');
            const form = pm.querySelector('.payment-form');
            if (form) {
                // Ensure inline display style is cleared so CSS class handles visibility
                form.style.display = '';
                form.classList.remove('active');
            }
        }
    });

    // Toggle current method
    targetMethod.classList.toggle('active');

    // Ensure inline display style is cleared
    targetForm.style.display = '';

    const isActive = targetForm.classList.toggle('active');

    if (isActive) {
        // Update sidebar with selected payment method
        if (typeof window.updatePaymentMethodSidebar === 'function') {
            if (method === 'vodafone') {
                window.updatePaymentMethodSidebar('vodafone', 'Vodafone Cash', 'pics/vf.png');
            } else if (method === 'instapay') {
                window.updatePaymentMethodSidebar('instapay', 'InstaPay', 'pics/insta.png');
            }
        }

        // Scroll to form after a small delay
        setTimeout(() => {
            targetMethod.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 300);
    }
}

document.addEventListener('DOMContentLoaded', function () {
    // Get form elements
    const vodafoneNumber = document.getElementById('vodafone-number');
    const vodafoneAmount = document.getElementById('vodafone-amount');
    const vodafoneImage = document.getElementById('vodafone-image');
    const vodafoneMessage = document.getElementById('vodafone-message');
    const vodafoneBtn = document.querySelector('.vodafone-btn');
    const vodafonePreview = document.getElementById('vodafone-preview');

    const instapayNumber = document.getElementById('instapay-number');
    const instapayAmount = document.getElementById('instapay-amount');
    const instapayImage = document.getElementById('instapay-image');
    const instapayMessage = document.getElementById('instapay-message');
    const instapayBtn = document.querySelector('.instapay-btn');
    const instapayPreview = document.getElementById('instapay-preview');

    // Image upload handlers
    function handleImageUpload(input, preview) {
        input.addEventListener('change', function (e) {
            const file = e.target.files[0];
            if (file && file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function (event) {
                    preview.innerHTML = `
                        <img src="${event.target.result}" alt="Transfer Screenshot">
                        <div class="image-preview-actions">
                            <button type="button" class="btn-remove-image" onclick="removeImage('${input.id}', '${preview.id}')">
                                حذف الصورة
                            </button>
                        </div>
                    `;
                    preview.classList.add('active');
                };
                reader.readAsDataURL(file);
            }
        });
    }

    if (vodafoneImage && vodafonePreview) {
        handleImageUpload(vodafoneImage, vodafonePreview);
    }

    if (instapayImage && instapayPreview) {
        handleImageUpload(instapayImage, instapayPreview);
    }

    // Get sidebar elements
    const balanceValue = document.querySelector('.balance-value');
    const infoLabel = document.querySelector('.info-label');
    const infoValue = document.querySelector('.info-value');
    const selectedMethod = document.querySelector('.selected-method');

    // Update sidebar when amount changes in Vodafone
    if (vodafoneAmount) {
        vodafoneAmount.addEventListener('input', function (e) {
            const amount = e.target.value || '0';
            balanceValue.textContent = amount;
        });
    }

    // Update sidebar when amount changes in InstaPay
    if (instapayAmount) {
        instapayAmount.addEventListener('input', function (e) {
            const amount = e.target.value || '0';
            balanceValue.textContent = amount;
        });
    }

    // Update sidebar when phone number changes in Vodafone
    if (vodafoneNumber) {
        vodafoneNumber.addEventListener('input', function (e) {
            const phone = e.target.value || '0109XXXXXXXX';
            infoValue.textContent = phone;
        });
    }

    // Update sidebar when phone number changes in InstaPay
    if (instapayNumber) {
        instapayNumber.addEventListener('input', function (e) {
            const username = e.target.value || '@username';
            infoValue.textContent = username;
        });
    }

    // Update sidebar logo when payment method is selected
    window.updatePaymentMethodSidebar = function (method, name, logoSrc) {
        selectedMethod.innerHTML = `
            <img src="${logoSrc}" alt="${name}" width="30" height="30">
            <span>${name}</span>
        `;

        // Reset values to defaults when switching methods
        balanceValue.textContent = '0';
        // Set appropriate default and label based on payment method
        if (method === 'instapay') {
            infoLabel.textContent = 'اسم المستخدم';
            infoValue.textContent = '@username';
        } else {
            infoLabel.textContent = 'رقم الموبايل';
            infoValue.textContent = '0109XXXXXXXX';
        }
    };

    // Generic Submit Function
    async function submitRechargeRequest(data, btn, formElements) {
        const originalContent = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = `
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" class="spinner">
                <circle cx="10" cy="10" r="8" stroke="currentColor" stroke-width="2" stroke-dasharray="50" stroke-dashoffset="0"/>
            </svg>
            جاري إرسال الطلب...
        `;

        try {
            const formData = new FormData();
            formData.append('payment_method', data.method);
            formData.append('amount', data.amount);
            formData.append('account_number', data.number);
            formData.append('message', data.message);
            formData.append('receipt_image', data.image);

            // Get session token
            const sessionToken = localStorage.getItem('sessionToken') || sessionStorage.getItem('sessionToken');

            if (!sessionToken) {
                showNotification('خطأ', 'يجب تسجيل الدخول أولاً', 'error');
                setTimeout(() => window.location.href = 'index.html', 2000);
                return;
            }

            const response = await fetch('/api/wallet/submit-recharge-request.php', {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${sessionToken}`
                    // Content-Type not set for FormData, browser sets it with boundary
                },
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                // showNotification('نجح', 'تم إرسال طلب الشحن بنجاح! سيتم مراجعته وتفعيل الرصيد خلال دقائق.', 'success');
                openModal('success');

                // Clear form
                formElements.number.value = '';
                formElements.amount.value = '';
                formElements.image.value = '';
                formElements.message.value = '';
                if (formElements.preview) {
                    formElements.preview.innerHTML = '';
                    formElements.preview.classList.remove('active');
                }
            } else {
                showNotification('خطأ', result.error || 'حدث خطأ أثناء إرسال الطلب', 'error');
            }
        } catch (error) {
            console.error('Recharge Error:', error);
            showNotification('خطأ', 'حدث خطأ في الاتصال بالسيرفر', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalContent;
        }
    }

    // Show inline error
    function showError(elementId, message) {
        const errorElement = document.getElementById(elementId);
        if (errorElement) {
            errorElement.textContent = message;
        }
    }

    // Clear all errors
    function clearErrors() {
        const errors = document.querySelectorAll('.error-text');
        errors.forEach(err => err.textContent = '');
    }

    // Handle Vodafone recharge
    if (vodafoneBtn) {
        // Disable fields initially except number
        if (vodafoneAmount) vodafoneAmount.disabled = true;
        if (vodafoneImage) vodafoneImage.disabled = true;
        if (vodafoneMessage) vodafoneMessage.disabled = true;
        vodafoneBtn.disabled = true;

        // Monitor phone input
        vodafoneNumber.addEventListener('input', function (e) {
            const val = e.target.value.replace(/\s/g, '');
            const isValid = /^01[0125][0-9]{8}$/.test(val);

            if (isValid) {
                vodafoneAmount.disabled = false;
                vodafoneImage.disabled = false;
                vodafoneMessage.disabled = false;
                vodafoneBtn.disabled = false;
                showError('error-vodafone-number', ''); // Clear error
            } else {
                vodafoneAmount.disabled = true;
                vodafoneImage.disabled = true;
                vodafoneMessage.disabled = true;
                vodafoneBtn.disabled = true;
                if (val.length >= 11) {
                    showError('error-vodafone-number', 'رقم الهاتف غير صحيح (يجب أن يبدأ بـ 01 يتكون من 11 رقم)');
                } else {
                    showError('error-vodafone-number', '');
                }
            }
        });

        vodafoneBtn.addEventListener('click', function (e) {
            e.preventDefault();
            clearErrors();

            const number = vodafoneNumber.value.replace(/\s/g, '');
            const amount = vodafoneAmount.value;
            const image = vodafoneImage.files[0];
            const message = vodafoneMessage.value;
            let isValid = true;

            // Strict Validation for Vodafone Number
            if (!/^01[0125][0-9]{8}$/.test(number)) {
                showError('error-vodafone-number', 'يرجى إدخال رقم فودافون كاش صحيح (11 رقم يبدأ بـ 01)');
                isValid = false;
            }

            if (!amount || amount <= 0) {
                showError('error-vodafone-amount', 'يرجى إدخال مبلغ صحيح');
                isValid = false;
            }

            if (!image) {
                showError('error-vodafone-image', 'يرجى إرفاق صورة رسالة التحويل');
                isValid = false;
            }

            if (!isValid) return;

            submitRechargeRequest({
                method: 'vodafone_cash',
                number: number,
                amount: amount,
                image: image,
                message: message
            }, this, {
                number: vodafoneNumber,
                amount: vodafoneAmount,
                image: vodafoneImage,
                message: vodafoneMessage,
                preview: vodafonePreview
            });
        });
    }

    // Handle InstaPay recharge
    if (instapayBtn) {
        instapayBtn.addEventListener('click', function (e) {
            e.preventDefault();
            clearErrors();

            const number = instapayNumber.value.replace(/\s/g, '');
            const amount = instapayAmount.value;
            const image = instapayImage.files[0];
            const message = instapayMessage.value;
            let isValid = true;

            // Validation
            if (!number || number.trim().length < 3) {
                showError('error-instapay-number', 'يرجى إدخال اسم مستخدم صحيح');
                isValid = false;
            }

            if (!amount || amount <= 0) {
                showError('error-instapay-amount', 'يرجى إدخال مبلغ صحيح');
                isValid = false;
            }

            if (!image) {
                showError('error-instapay-image', 'يرجى إرفاق صورة رسالة التحويل');
                isValid = false;
            }

            if (!isValid) return;

            submitRechargeRequest({
                method: 'instapay',
                number: number,
                amount: amount,
                image: image,
                message: message
            }, this, {
                number: instapayNumber,
                amount: instapayAmount,
                image: instapayImage,
                message: instapayMessage,
                preview: instapayPreview
            });
        });
    }

    // Show notification
    function showNotification(title, message, type) {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <strong>${title}</strong>
                <p>${message}</p>
            </div>
        `;

        document.body.appendChild(notification);

        setTimeout(() => {
            notification.classList.add('show');
        }, 100);

        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => {
                notification.remove();
            }, 300);
        }, 4000);
    }

    // Add notification styles dynamically
    if (!document.getElementById('notification-styles')) {
        const style = document.createElement('style');
        style.id = 'notification-styles';
        style.textContent = `
            .notification {
                position: fixed;
                top: 100px;
                right: -400px;
                background: white;
                padding: 20px;
                border-radius: 12px;
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
                z-index: 10000;
                min-width: 300px;
                max-width: 400px;
                transition: right 0.3s ease;
                border-right: 4px solid;
            }

            .notification.show {
                right: 20px;
            }

            .notification-success {
                border-color: #4CAF50;
            }

            .notification-error {
                border-color: #E60000;
            }

            .notification-content strong {
                display: block;
                font-size: 1.1rem;
                font-weight: 700;
                margin-bottom: 8px;
                color: var(--primary-dark);
            }

            .notification-content p {
                margin: 0;
                color: var(--text-gray);
                font-size: 0.95rem;
                line-height: 1.5;
            }

            .spinner circle {
                animation: spin 1s linear infinite;
            }

            @keyframes spin {
                0% { stroke-dashoffset: 0; }
                100% { stroke-dashoffset: 100; }
            }

            .btn-recharge {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
            }
        `;
        document.head.appendChild(style);
    }
});

// Global function to remove image
function removeImage(inputId, previewId) {
    const input = document.getElementById(inputId);
    const preview = document.getElementById(previewId);

    if (input) input.value = '';
    if (preview) {
        preview.innerHTML = '';
        preview.classList.remove('active');
    }
}
