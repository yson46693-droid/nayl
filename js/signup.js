// Signup functionality
document.addEventListener('DOMContentLoaded', function () {
    /**
     * توليد توكن أمان عشوائي فريد لكل صفحة
     * هذا التوكن يضمن أن API لا يمكن استخدامه إلا من خلال صفحة إنشاء الحساب
     */
    function generateSecurityToken() {
        // محاولة استخدام crypto.getRandomValues (الأفضل)
        if (typeof crypto !== 'undefined' && crypto.getRandomValues) {
            try {
                const array = new Uint8Array(32);
                crypto.getRandomValues(array);
                const token = Array.from(array, byte => byte.toString(16).padStart(2, '0')).join('');
                return token;
            } catch (e) {
                console.warn('Failed to use crypto.getRandomValues, using fallback');
            }
        }

        // Fallback: توليد توكن عشوائي باستخدام Math.random
        let token = '';
        const chars = '0123456789abcdef';
        for (let i = 0; i < 64; i++) {
            token += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        return token;
    }

    // تنظيف أي Rate Limiting قديم من localStorage
    try {
        const keys = Object.keys(localStorage);
        keys.forEach(key => {
            if (key.startsWith('rate_limit_signup')) {
                localStorage.removeItem(key);
            }
        });
    } catch (e) {
        // تجاهل الأخطاء
    }

    // توليد التوكن عند تحميل الصفحة
    const securityToken = generateSecurityToken();

    // التحقق من أن التوكن تم توليده بشكل صحيح
    if (!securityToken || securityToken.length < 32) {
        console.error('Failed to generate security token');
        alert('خطأ في توليد توكن الأمان. يرجى إعادة تحميل الصفحة.');
    } else {
        // حفظ التوكن في sessionStorage (يُمسح عند إغلاق المتصفح)
        sessionStorage.setItem('signup_security_token', securityToken);
    }

    const togglePasswordBtn = document.getElementById('togglePassword');
    const toggleConfirmPasswordBtn = document.getElementById('toggleConfirmPassword');
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirmPassword');
    const changeMethodBtn = document.getElementById('changeMethodBtn');

    if (changeMethodBtn) {
        changeMethodBtn.addEventListener('click', function () {
            alert('هذه الميزة ستكون متاحة قريباً');
        });
    }

    // Country Code Selector Elements
    const countryCodeSelect = document.getElementById('countryCodeSelect');
    const selectedCountry = document.getElementById('selectedCountry');
    const countryDropdown = document.getElementById('countryDropdown');
    const countrySearch = document.getElementById('countrySearch');
    const countryList = document.getElementById('countryList');
    const selectedCountryCodeInput = document.getElementById('selectedCountryCode');
    const phoneInput = document.getElementById('phone');

    // Form fields and submit button
    const submitBtn = document.getElementById('submitBtn');
    const fullnameInput = document.getElementById('fullname');
    const emailInput = document.getElementById('email');
    const cityInput = document.getElementById('city');
    const countryInput = document.getElementById('country');
    const termsCheckbox = document.getElementById('terms');

    // Toggle password visibility for password field
    if (togglePasswordBtn && passwordInput) {
        togglePasswordBtn.addEventListener('click', function () {
            togglePasswordVisibility(this, passwordInput);
        });
    }

    // Toggle password visibility for confirm password field
    if (toggleConfirmPasswordBtn && confirmPasswordInput) {
        toggleConfirmPasswordBtn.addEventListener('click', function () {
            togglePasswordVisibility(this, confirmPasswordInput);
        });
    }

    // Function to toggle password visibility
    function togglePasswordVisibility(button, input) {
        const type = input.type === 'password' ? 'text' : 'password';
        input.type = type;

        // Simple visual feedback: change icon color or similar if needed
        // For now, just toggling the type is enough as most users expect this
        button.classList.toggle('active');
    }

    // Function to validate all form fields and enable/disable submit button
    function validateForm() {
        if (!submitBtn) return;

        const fullname = fullnameInput ? fullnameInput.value.trim() : '';
        const phone = phoneInput ? phoneInput.value.trim() : '';
        const email = emailInput ? emailInput.value.trim() : '';
        const password = passwordInput ? passwordInput.value : '';
        const confirmPassword = confirmPasswordInput ? confirmPasswordInput.value : '';
        const city = cityInput ? cityInput.value.trim() : '';
        const country = countryInput ? countryInput.value : '';
        const termsAccepted = termsCheckbox ? termsCheckbox.checked : false;

        // Check if all required fields are filled
        // الاسم ثلاثي على الأقل
        const fullnameParts = fullname.split(/\s+/).filter(part => part.length > 0);
        const isFullnameValid = fullnameParts.length >= 3;

        const isPhoneValid = phone.length >= 7 && /^[0-9]+$/.test(phone);
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        const isEmailValid = emailRegex.test(email);
        const isPasswordValid = password.length >= 6;
        const isConfirmPasswordValid = confirmPassword === password && confirmPassword.length >= 6;
        const isCityValid = city.length > 0;
        const isCountryValid = country.length > 0;

        // Update visual hints
        updateFieldHint('fullnameHint', isFullnameValid, fullname.length > 0);
        updateFieldHint('phoneHint', isPhoneValid, phone.length > 0);
        updateFieldHint('emailHint', isEmailValid, email.length > 0);
        updateFieldHint('passwordHint', isPasswordValid, password.length > 0);
        updateFieldHint('confirmPasswordHint', isConfirmPasswordValid, confirmPassword.length > 0);

        // Enable button only if all fields are valid
        const isFormValid = isFullnameValid && isPhoneValid && isEmailValid && isPasswordValid && isConfirmPasswordValid &&
            isCityValid && isCountryValid && termsAccepted;

        submitBtn.disabled = !isFormValid;
    }

    function updateFieldHint(hintId, isValid, hasValue) {
        const hint = document.getElementById(hintId);
        if (!hint) return;

        if (isValid) {
            hint.classList.add('success');
            hint.classList.remove('error');
        } else if (hasValue) {
            hint.classList.add('error');
            hint.classList.remove('success');
        } else {
            hint.classList.remove('error', 'success');
        }
    }

    // Add event listeners to all form fields to validate on input
    /**
     * التحقق من وجود القيمة مسبقاً في قاعدة البيانات
     */
    async function checkExistence(type, value) {
        if (!value || value.length < 3) return false;

        try {
            const response = await fetch('api/auth/check-exists.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ type, value })
            });
            const result = await response.json();
            return result.success && result.data.exists;
        } catch (error) {
            console.error('Error checking existence:', error);
            return false;
        }
    }

    // Debounce function to limit API calls
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    if (phoneInput) {
        const checkPhone = debounce(async () => {
            const phone = phoneInput.value.trim();
            const countryCode = (selectedCountryCodeInput && selectedCountryCodeInput.value) || '+20';
            const fullPhone = countryCode + phone;

            if (phone.length >= 7) {
                const exists = await checkExistence('phone', fullPhone);
                if (exists) {
                    phoneInput.style.borderColor = '#e74c3c';
                    showInputError(phoneInput, 'رقم الهاتف هذا مسجل بالفعل بحساب آخر');

                    // إخفاء كود الدولة لإعطاء مساحة أكبر للتصحيح
                    if (countryCodeSelect) {
                        countryCodeSelect.style.display = 'none';
                    }
                } else {
                    removeInputError(phoneInput);
                    validateForm();
                }
            }
        }, 500);

        phoneInput.addEventListener('input', () => {
            removeInputError(phoneInput); // تمكين المستخدم من إعادة الإدخال بمسح الخطأ فوراً

            // إظهار كود الدولة مرة أخرى عند بدء التعديل
            if (countryCodeSelect) {
                countryCodeSelect.style.display = 'block';
            }

            validateForm();
            checkPhone();
        });
    }

    if (emailInput) {
        const checkEmail = debounce(async () => {
            const email = emailInput.value.trim();
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

            if (emailRegex.test(email)) {
                const exists = await checkExistence('email', email);
                if (exists) {
                    emailInput.style.borderColor = '#e74c3c';
                    showInputError(emailInput, 'البريد الإلكتروني هذا مسجل بالفعل');
                } else {
                    removeInputError(emailInput);
                    validateForm();
                }
            }
        }, 500);

        emailInput.addEventListener('input', () => {
            removeInputError(emailInput); // تمكين المستخدم من إعادة الإدخال بمسح الخطأ فوراً
            validateForm();
            checkEmail();
        });
    }

    function showInputError(input, message) {
        let errorEl = input.parentElement.querySelector('.input-error-tip');
        if (!errorEl) {
            errorEl = document.createElement('div');
            errorEl.className = 'input-error-tip';
            errorEl.style.cssText = 'color: #e74c3c; font-size: 0.8rem; margin-top: 5px; font-weight: 600;';
            input.parentElement.appendChild(errorEl);
        }
        errorEl.textContent = message;
        submitBtn.disabled = true; // منع الإرسال إذا وجد خطأ
    }

    function removeInputError(input) {
        const errorEl = input.parentElement.querySelector('.input-error-tip');
        if (errorEl) {
            errorEl.remove();
        }
        input.style.borderColor = '';
    }

    if (fullnameInput) {
        fullnameInput.addEventListener('input', validateForm);
    }

    if (passwordInput) {
        passwordInput.addEventListener('input', validateForm);
    }

    if (confirmPasswordInput) {
        confirmPasswordInput.addEventListener('input', validateForm);
    }

    const citiesByCountry = {
        "مصر": ["القاهرة", "الإسكندرية", "الجيزة", "شبرا الخيمة", "بورسعيد", "السويس", "الأقصر", "أسوان", "المنصورة", "الزقازيق", "طنطا", "المحلة الكبرى", "أسيوط", "سوهاج", "المنيا", "قنا", "بني سويف", "الفيوم", "دمياط", "الإسماعيلية", "كفر الشيخ", "مطروح", "البحر الأحمر", "الوادي الجديد", "الغربية", "الشرقية", "المنوفية", "الدقهلية", "القليوبية", "البحيرة"],
        "السعودية": ["الرياض", "جدة", "مكة المكرمة", "المدينة المنورة", "الدمام", "الطائف", "تبوك", "بريدة", "خميس مشيط", "أبها", "حائل", "نجران", "جازان", "الجبيل", "الخرج", "القطيف", "ينبع", "الأحساء", "عرعر", "سكاكا", "الباحة"],
        "الإمارات": ["أبوظبي", "دبي", "الشارقة", "عجمان", "رأس الخيمة", "الفجيرة", "أم القيوين", "العين"],
        "الكويت": ["مدينة الكويت", "الأحمدي", "حولي", "السالمية", "الجهراء", "الفروانية", "مبارك الكبير"],
        "قطر": ["الدوحة", "الريان", "الخور", "الوكرة", "أم صلال", "الظعاين", "الشمال"],
        "البحرين": ["المنامة", "المحرق", "الرفاع", "مدينة عيسى", "مدينة حمد", "سترة", "جد حفص"],
        "عمان": ["مسقط", "صلالة", "صحار", "نزوى", "صور", "البريمي", "السيب", "مطرح", "بوشر"],
        "الأردن": ["عمان", "إربد", "الزرقاء", "العقبة", "السلط", "الكرك", "جرش", "مادبا", "المفرق", "معان", "عجلون", "الطفيلة"],
        "فلسطين": ["القدس", "غزة", "رام الله", "الخليل", "نابلس", "جنين", "طولكرم", "بيت لحم", "أريحا", "قلقيلية", "رفح", "خانيونس"],
        "لبنان": ["بيروت", "طرابلس", "صيدا", "صور", "زحلة", "جونيه", "البترون", "بعلبك"],
        "سوريا": ["دمشق", "حلب", "حمص", "حماة", "اللاذقية", "طرطوس", "الرقة", "دير الزور", "الحسكة", "إدلب", "درعا", "السويداء"],
        "العراق": ["بغداد", "البصرة", "الموصل", "أربيل", "السليمانية", "كركوك", "النجف", "كربلاء", "الناصرية", "العمارة", "الديوانية", "الحلة", "بعقوبة", "الرمادي", "تكريت", "دهوك"],
        "اليمن": ["صنعاء", "عدن", "تعز", "الحديدة", "إب", "المكلا", "ذمار", "سيئون"],
        "ليبيا": ["طرابلس", "بنغازي", "مصراتة", "البيضاء", "طبرق", "الزاوية", "سبها", "سرت"],
        "تونس": ["تونس", "صفاقس", "سوسة", "القيروان", "بنزرت", "قابس", "أريانة", "القصرين", "قفصة"],
        "الجزائر": ["الجزائر", "وهران", "قسنطينة", "عنابة", "البليدة", "باتنة", "الجلفة", "سطيف", "سيدي بلعباس"],
        "المغرب": ["الرباط", "الدار البيضاء", "فاس", "مراكش", "طنجة", "أكادير", "مكناس", "وجدة", "القنيطرة", "تطوان"],
        "السودان": ["الخرطوم", "أم درمان", "الخرطوم بحري", "بورتسودان", "كسلا", "الأبيض", "نيالا", "ود مدني", "القضارف"]
    };

    if (cityInput) {
        cityInput.addEventListener('change', validateForm);
    }

    // Populate cities based on country
    function updateCityDropdown(countryValue) {
        if (!cityInput) return;

        cityInput.innerHTML = '<option value="">اختر المدينة</option>';

        if (countryValue && citiesByCountry[countryValue]) {
            const cities = citiesByCountry[countryValue];
            cities.forEach(city => {
                const option = document.createElement('option');
                option.value = city;
                option.textContent = city;
                cityInput.appendChild(option);
            });
        } else if (countryValue === "أخرى") {
            // For "Other", maybe add a generic option or similar. 
            // Adding a generic option to allow validation to pass if they select "Other" country
            const option = document.createElement('option');
            option.value = "أخرى";
            option.textContent = "أخرى";
            cityInput.appendChild(option);
        }

        // Disable city dropdown if no country selected
        cityInput.disabled = !countryValue;

        // Trigger validation
        validateForm();
    }


    if (countryInput) {
        // Initial state
        if (!countryInput.value) {
            if (cityInput) cityInput.disabled = true;
        } else {
            updateCityDropdown(countryInput.value);
        }

        countryInput.addEventListener('change', function () {
            const countryName = this.value;

            // Update cities
            updateCityDropdown(countryName);

            // مزامنة رمز الهاتف مع الدولة إن وُجدت في القائمة
            if (countryList) {
                const countryOptions = countryList.querySelectorAll('.country-option');
                countryOptions.forEach(option => {
                    const optionCountry = option.dataset.country;
                    if (optionCountry && optionCountry.trim() === countryName.trim()) {
                        const code = option.dataset.code;
                        const flagEl = option.querySelector('.flag-icon');
                        if (selectedCountry) {
                            const selectedFlag = selectedCountry.querySelector('.flag-icon');
                            const selectedCode = selectedCountry.querySelector('.country-code');
                            if (selectedFlag && flagEl) selectedFlag.textContent = flagEl.textContent;
                            if (selectedCode) selectedCode.textContent = code;
                        }
                        if (selectedCountryCodeInput) selectedCountryCodeInput.value = code;
                        countryOptions.forEach(opt => opt.classList.remove('selected'));
                        option.classList.add('selected');
                    }
                });
            }

            validateForm();
        });
    }

    if (termsCheckbox) {
        termsCheckbox.addEventListener('change', validateForm);
    }

    // Initial validation on page load
    validateForm();


    // Country Code Selector Functionality
    if (countryCodeSelect && selectedCountry && countryDropdown) {
        // Toggle dropdown
        selectedCountry.addEventListener('click', function (e) {
            e.stopPropagation();
            countryCodeSelect.classList.toggle('active');

            // Add active class to parent form-group for z-index management
            const parentGroup = this.closest('.form-group');
            const parentRow = this.closest('.form-row');

            if (parentGroup) {
                if (countryCodeSelect.classList.contains('active')) {
                    parentGroup.classList.add('dropdown-active');
                    if (parentRow) parentRow.classList.add('row-active');
                } else {
                    parentGroup.classList.remove('dropdown-active');
                    if (parentRow) parentRow.classList.remove('row-active');
                }
            }
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function (e) {
            if (!countryCodeSelect.contains(e.target)) {
                countryCodeSelect.classList.remove('active');
                const parentGroup = countryCodeSelect.closest('.form-group');
                const parentRow = countryCodeSelect.closest('.form-row');
                if (parentGroup) parentGroup.classList.remove('dropdown-active');
                if (parentRow) parentRow.classList.remove('row-active');
            }
        });

        // Search functionality
        if (countrySearch) {
            countrySearch.addEventListener('input', function (e) {
                e.stopPropagation();
                const searchTerm = this.value.toLowerCase();
                const countryOptions = countryList.querySelectorAll('.country-option');

                countryOptions.forEach(option => {
                    const countryName = option.dataset.country.toLowerCase();
                    const countryCode = option.dataset.code.toLowerCase();

                    if (countryName.includes(searchTerm) || countryCode.includes(searchTerm)) {
                        option.style.display = 'flex';
                    } else {
                        option.style.display = 'none';
                    }
                });
            });

            // Prevent dropdown from closing when clicking on search input
            countrySearch.addEventListener('click', function (e) {
                e.stopPropagation();
            });
        }

        // Handle country selection
        const countryOptions = countryList.querySelectorAll('.country-option');
        countryOptions.forEach(option => {
            option.addEventListener('click', function () {
                const code = this.dataset.code;
                const country = this.dataset.country;
                const flagIcon = this.querySelector('.flag-icon').textContent;

                // Update selected country display
                selectedCountry.querySelector('.flag-icon').textContent = flagIcon;
                selectedCountry.querySelector('.country-code').textContent = code;

                // Update hidden input
                selectedCountryCodeInput.value = code;

                // Update selected state
                countryOptions.forEach(opt => opt.classList.remove('selected'));
                this.classList.add('selected');

                // Close dropdown
                countryCodeSelect.classList.remove('active');

                // Clear search
                if (countrySearch) {
                    countrySearch.value = '';
                    countryOptions.forEach(opt => opt.style.display = 'flex');
                }

                // Focus on phone input
                if (phoneInput) {
                    phoneInput.focus();
                }
            });
        });
    }

    // Handle signup button click
    if (submitBtn) {
        submitBtn.addEventListener('click', async function (e) {
            // منع الإرسال المتكرر
            if (submitBtn.disabled || submitBtn.classList.contains('loading')) {
                return;
            }

            const fullname = document.getElementById('fullname').value.trim();
            const phone = document.getElementById('phone').value.trim();
            const email = document.getElementById('email').value.trim();
            const countryCode = (selectedCountryCodeInput && selectedCountryCodeInput.value) || '+20';
            const password = passwordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            const city = document.getElementById('city').value;
            const country = document.getElementById('country').value;
            const termsAccepted = document.getElementById('terms').checked;

            // Check if WhatsApp verification was done
            const whatsappClicked = sessionStorage.getItem('whatsappVerificationClicked') === 'true';

            // إظهار حالة التحميل
            submitBtn.disabled = true;
            submitBtn.classList.add('loading');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'جاري إنشاء الحساب...';

            try {
                // استيراد API
                const { signup } = await import('./api/auth.js');

                // الحصول على التوكن من sessionStorage
                const token = sessionStorage.getItem('signup_security_token');
                if (!token || token.length < 32) {
                    alert('خطأ في الأمان. يرجى إعادة تحميل الصفحة.');
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('loading');
                    submitBtn.textContent = originalText;
                    return;
                }

                // إنشاء بيانات المستخدم مع التوكن
                const userData = {
                    fullname: fullname,
                    email: email,
                    phone: phone,
                    countryCode: countryCode,
                    password: password,
                    confirmPassword: confirmPassword,
                    country: country,
                    city: city,
                    whatsappVerified: whatsappClicked,
                    termsAccepted: termsAccepted,
                    securityToken: token // إرسال التوكن مع الطلب
                };

                // استدعاء API التسجيل
                const result = await signup(userData);

                if (result.success) {
                    // نجح التسجيل - البيانات الفعلية في result.data.data (استجابة API)
                    const apiData = result.data?.data || result.data;
                    const user = apiData?.user;
                    if (user) {
                        const fullPhone = user.full_phone || (countryCode + phone);
                        localStorage.setItem('userData_' + fullPhone, JSON.stringify(user));
                        localStorage.setItem('userPhone', fullPhone);
                        localStorage.setItem('isLoggedIn', 'true');
                    }

                    // إظهار رسالة النجاح
                    if (whatsappClicked) {
                        alert('تم إنشاء الحساب بنجاح! يمكنك الآن استرجاع كلمة المرور عبر واتساب في حال نسيانها.');
                    } else {
                        alert('تم إنشاء الحساب بنجاح! يمكنك تسجيل الدخول الآن.');
                    }

                    sessionStorage.removeItem('whatsappVerificationClicked');

                    // إعادة التوجيه إلى الصفحة الرئيسية
                    window.location.href = 'home.html';
                } else {
                    // فشل التسجيل
                    let errorMessage = result.error || 'حدث خطأ أثناء إنشاء الحساب';
                    alert(errorMessage);

                    // إعادة تفعيل الزر
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('loading');
                    submitBtn.textContent = originalText;
                }
            } catch (error) {
                // خطأ في الاستيراد أو التنفيذ
                console.error('Signup Error:', error);
                alert('حدث خطأ غير متوقع. يرجى المحاولة مرة أخرى.');

                // إعادة تفعيل الزر
                submitBtn.disabled = false;
                submitBtn.classList.remove('loading');
                submitBtn.textContent = originalText;
            }
        });
    }

    // Real-time password match validation
    if (confirmPasswordInput && passwordInput) {
        confirmPasswordInput.addEventListener('input', function () {
            if (this.value && this.value !== passwordInput.value) {
                this.style.borderColor = '#e74c3c';
            } else if (this.value === passwordInput.value) {
                this.style.borderColor = '#4CAF50';
            } else {
                this.style.borderColor = '#e0e6ed';
            }
        });
    }


    // Terms Modal Functionality
    const termsModal = document.getElementById('termsModal');
    const termsLink = document.querySelector('.terms-link');
    const closeModal = document.querySelector('.close-modal');
    const acceptTermsBtn = document.getElementById('acceptTermsBtn');

    if (termsModal && termsLink) {
        // Open modal
        termsLink.addEventListener('click', function (e) {
            e.preventDefault();
            termsModal.style.display = 'block';
            document.body.style.overflow = 'hidden'; // Prevent scrolling
        });

        // Close modal
        if (closeModal) {
            closeModal.addEventListener('click', function () {
                termsModal.style.display = 'none';
                document.body.style.overflow = '';
            });
        }

        // Close when clicking outside
        window.addEventListener('click', function (e) {
            if (e.target === termsModal) {
                termsModal.style.display = 'none';
                document.body.style.overflow = '';
            }
        });

        // Accept terms
        if (acceptTermsBtn) {
            acceptTermsBtn.addEventListener('click', function () {
                if (termsCheckbox) {
                    termsCheckbox.checked = true;
                    // Trigger change event to update submit button state
                    const event = new Event('change');
                    termsCheckbox.dispatchEvent(event);
                }
                termsModal.style.display = 'none';
                document.body.style.overflow = '';
            });
        }
    }
});
