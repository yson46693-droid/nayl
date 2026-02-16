// Forgot Password functionality
document.addEventListener('DOMContentLoaded', function () {
    const forgotPasswordForm = document.getElementById('forgotPasswordForm');
    const submitBtn = document.getElementById('submitBtn');
    const successMessage = document.getElementById('successMessage');
    const phoneInput = document.getElementById('phone');

    // Handle form submission
    if (forgotPasswordForm) {
        forgotPasswordForm.addEventListener('submit', function (e) {
            e.preventDefault();

            const phone = phoneInput.value;

            // Validate phone number
            const phoneRegex = /^[0-9]{11}$/;
            if (!phoneRegex.test(phone)) {
                alert('الرجاء إدخال رقم هاتف صحيح (11 رقم)');
                return;
            }

            // Disable button and show loading state
            submitBtn.disabled = true;
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = 'جاري الإرسال... <span class="loading-spinner"></span>';

            // Simulate sending WhatsApp message (Backend will handle this later)
            setTimeout(function () {
                // Here you would normally make an API call to your backend
                // Example:
                // fetch('/api/forgot-password', {
                //     method: 'POST',
                //     headers: {
                //         'Content-Type': 'application/json',
                //     },
                //     body: JSON.stringify({ phone: phone })
                // })
                // .then(response => response.json())
                // .then(data => {
                //     if (data.success) {
                //         showSuccessMessage();
                //     } else {
                //         alert('حدث خطأ: ' + data.message);
                //     }
                // })
                // .catch(error => {
                //     alert('حدث خطأ في الاتصال بالخادم');
                // });

                // For now, just show success message
                showSuccessMessage();

                // Re-enable button
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;

                // Clear the form
                phoneInput.value = '';
            }, 2000); // Simulate 2 second delay
        });
    }

    function showSuccessMessage() {
        // Hide form temporarily
        forgotPasswordForm.style.opacity = '0.5';

        // Show success message
        successMessage.classList.add('show');

        // Scroll to success message
        successMessage.scrollIntoView({ behavior: 'smooth', block: 'center' });

        // After 5 seconds, hide success message and restore form
        setTimeout(function () {
            successMessage.classList.remove('show');
            forgotPasswordForm.style.opacity = '1';
        }, 5000);
    }

    // Real-time phone number validation
    phoneInput.addEventListener('input', function (e) {
        // Remove any non-numeric characters
        this.value = this.value.replace(/[^0-9]/g, '');

        // Limit to 11 digits
        if (this.value.length > 11) {
            this.value = this.value.slice(0, 11);
        }
    });

    // Add visual feedback for valid phone number
    phoneInput.addEventListener('blur', function () {
        const phoneRegex = /^[0-9]{11}$/;
        if (this.value && !phoneRegex.test(this.value)) {
            this.style.borderColor = '#ef4444';
        } else if (this.value) {
            this.style.borderColor = '#10b981';
        }
    });

    phoneInput.addEventListener('focus', function () {
        this.style.borderColor = '';
    });
});
