/**
 * نظام WebAuthn مبسط ونظيف
 */

class SimpleWebAuthn {
    constructor() {
        this.apiBase = this.getApiBase();
    }

    /**
     * الحصول على المسار الأساسي لـ API
     */
    getApiBase() {
        const currentPath = window.location.pathname;
        const pathParts = currentPath.split('/').filter(p => p && !p.endsWith('.php'));
        
        // استخدام مسار مطلق بناءً على موقع الصفحة الحالية
        // إذا كنا في الجذر (مثل /v1/profile.php)، المسار سيكون /v1/api/webauthn_register.php
        // إذا كنا في مجلد فرعي، نستخدم المسار النسبي
        
        if (pathParts.length === 0) {
            // في الجذر - استخدام مسار نسبي
            return 'api/webauthn_register.php';
        } else {
            // في مجلد فرعي - بناء مسار مطلق
            const basePath = '/' + pathParts[0];
            return basePath + '/api/webauthn_register.php';
        }
    }

    /**
     * التحقق من دعم WebAuthn
     */
    isSupported() {
        try {
            // التحقق من دعم WebAuthn API
            const hasPublicKeyCredential = typeof window.PublicKeyCredential !== 'undefined' && window.PublicKeyCredential !== null;
            const hasCredentials = typeof navigator !== 'undefined' && 
                                  navigator.credentials && 
                                  typeof navigator.credentials.create === 'function' && 
                                  typeof navigator.credentials.get === 'function';
            
            // التحقق من دعم HTTPS (مطلوب لـ WebAuthn إلا في localhost أو IP محلي أو الدومين المسموح)
            const hostname = window.location.hostname ? window.location.hostname.toLowerCase() : '';
            const protocol = window.location.protocol ? window.location.protocol.toLowerCase() : '';
            const allowedHosts = ['almoustafa.site', 'www.almoustafa.site', 'an.almoustafa.site'];
            const isLocalhost = hostname === 'localhost' || 
                               hostname === '127.0.0.1' || 
                               hostname === '[::1]' ||
                               allowedHosts.includes(hostname) ||
                               hostname.startsWith('192.168.') ||
                               hostname.startsWith('10.') ||
                               hostname.startsWith('172.16.') ||
                               hostname.startsWith('172.17.') ||
                               hostname.startsWith('172.18.') ||
                               hostname.startsWith('172.19.') ||
                               hostname.startsWith('172.20.') ||
                               hostname.startsWith('172.21.') ||
                               hostname.startsWith('172.22.') ||
                               hostname.startsWith('172.23.') ||
                               hostname.startsWith('172.24.') ||
                               hostname.startsWith('172.25.') ||
                               hostname.startsWith('172.26.') ||
                               hostname.startsWith('172.27.') ||
                               hostname.startsWith('172.28.') ||
                               hostname.startsWith('172.29.') ||
                               hostname.startsWith('172.30.') ||
                               hostname.startsWith('172.31.') ||
                               hostname === '0.0.0.0';
            
            const isSecure = protocol === 'https:' || isLocalhost || protocol === 'file:';
            
            const supported = hasPublicKeyCredential && hasCredentials && isSecure;
            
            // تسجيل معلومات التشخيص
            const supportInfo = {
                hasPublicKeyCredential,
                hasCredentials,
                isSecure,
                isLocalhost,
                protocol: protocol,
                hostname: hostname,
                userAgent: navigator.userAgent || 'unknown'
            };
            
            if (!supported) {
                console.warn('🔍 WebAuthn Support Check:', supportInfo);
                
                // رسالة توضيحية للمستخدم
                if (!hasPublicKeyCredential || !hasCredentials) {
                    console.error('❌ WebAuthn API غير متوفر في هذا المتصفح');
                    console.error('المتصفحات المدعومة: Chrome 67+, Firefox 60+, Safari 14+, Edge 18+');
                    console.error('المتصفح الحالي:', navigator.userAgent);
                } else if (!isSecure) {
                    console.error('❌ WebAuthn يتطلب HTTPS أو localhost');
                    console.error('البروتوكول الحالي:', protocol);
                    console.error('Hostname:', hostname);
                }
            } else {
                console.log('✅ WebAuthn مدعوم في هذا المتصفح', supportInfo);
            }
            
            return supported;
        } catch (error) {
            console.error('❌ خطأ في التحقق من دعم WebAuthn:', error);
            return false;
        }
    }

    /**
     * الحصول على معلومات الدعم
     */
    getSupportInfo() {
        try {
            const hasPublicKeyCredential = typeof window.PublicKeyCredential !== 'undefined' && window.PublicKeyCredential !== null;
            const hasCredentials = typeof navigator !== 'undefined' && 
                                  navigator.credentials && 
                                  typeof navigator.credentials.create === 'function' && 
                                  typeof navigator.credentials.get === 'function';
            
            const hostname = window.location.hostname ? window.location.hostname.toLowerCase() : 'unknown';
            const protocol = window.location.protocol ? window.location.protocol : 'unknown';
            const allowedHosts = ['almoustafa.site', 'www.almoustafa.site', 'an.almoustafa.site'];
            const isLocalhost = hostname === 'localhost' || 
                               hostname === '127.0.0.1' || 
                               hostname === '[::1]' ||
                               allowedHosts.includes(hostname) ||
                               hostname.startsWith('192.168.') ||
                               hostname.startsWith('10.') ||
                               hostname.startsWith('172.16.') ||
                               hostname === '0.0.0.0';
            
            const isSecure = protocol === 'https:' || isLocalhost || protocol === 'file:';
            
            let info = 'معلومات دعم WebAuthn:\n\n';
            info += `✅/❌ PublicKeyCredential: ${hasPublicKeyCredential ? '✅ مدعوم' : '❌ غير مدعوم'}\n`;
            info += `✅/❌ navigator.credentials: ${hasCredentials ? '✅ مدعوم' : '❌ غير مدعوم'}\n`;
            info += `✅/❌ HTTPS/Localhost: ${isSecure ? '✅ آمن' : '❌ غير آمن'} (${protocol})\n`;
            info += `📍 Hostname: ${hostname}\n\n`;
            
            if (!hasPublicKeyCredential || !hasCredentials) {
                info += '📱 المتصفحات المدعومة:\n';
                info += '   - Chrome 67+\n';
                info += '   - Firefox 60+\n';
                info += '   - Safari 14+ (iOS 14+)\n';
                info += '   - Edge 18+\n';
                info += '   - Opera 54+\n\n';
            }
            
            if (!isSecure) {
                info += '⚠️ ملاحظة مهمة:\n';
                info += '   WebAuthn يتطلب HTTPS أو localhost\n';
                info += '   الحل: استخدم https:// بدلاً من http://\n\n';
            }
            
            info += `🔍 معلومات إضافية:\n`;
            info += `   User Agent: ${navigator.userAgent ? navigator.userAgent.substring(0, 50) + '...' : 'غير متوفر'}\n`;
            
            return info;
        } catch (error) {
            return 'خطأ في الحصول على معلومات الدعم: ' + error.message;
        }
    }

    /**
     * تحويل Base64 إلى ArrayBuffer
     */
    base64ToArrayBuffer(base64) {
        if (typeof base64 !== 'string' || base64.length === 0) {
            throw new Error('بيانات Base64 غير صالحة');
        }

        const normalized = this.normalizeBase64(base64);
        let binaryString;

        try {
            binaryString = window.atob(normalized);
        } catch (error) {
            console.error('WebAuthn: Invalid Base64 input', {
                original: base64,
                normalized,
                length: normalized.length,
                error: error.message
            });
            throw new Error('فشل في قراءة بيانات الترميز (Base64).');
        }
        const bytes = new Uint8Array(binaryString.length);
        for (let i = 0; i < binaryString.length; i++) {
            bytes[i] = binaryString.charCodeAt(i);
        }
        return bytes.buffer;
    }

    /**
     * تحويل ArrayBuffer إلى Base64
     */
    arrayBufferToBase64(buffer) {
        const bytes = new Uint8Array(buffer);
        let binary = '';
        for (let i = 0; i < bytes.byteLength; i++) {
            binary += String.fromCharCode(bytes[i]);
        }
        return window.btoa(binary);
    }

    /**
     * تحويل base64url إلى base64 عادي مع الحشو
     */
    normalizeBase64(value) {
        let normalized = value.replace(/-/g, '+').replace(/_/g, '/').replace(/\s+/g, '');
        const paddingNeeded = normalized.length % 4;
        if (paddingNeeded) {
            normalized += '='.repeat(4 - paddingNeeded);
        }
        return normalized;
    }

    /**
     * محاولة تخمين اسم الجهاز من الـ User-Agent
     */
    detectDeviceName() {
        const ua = navigator.userAgent || '';

        if (/iPhone/i.test(ua)) {
            return 'iPhone';
        }
        if (/iPad/i.test(ua)) {
            return 'iPad';
        }
        if (/Android/i.test(ua)) {
            const match = ua.match(/Android\s+([\d\.]+)/i);
            return match ? `Android ${match[1]}` : 'Android Device';
        }
        if (/Macintosh/i.test(ua)) {
            return 'Mac';
        }
        if (/Windows/i.test(ua)) {
            return 'Windows';
        }

        if (/Chrome/i.test(ua)) {
            return 'Chrome Browser';
        }
        if (/Safari/i.test(ua)) {
            return 'Safari Browser';
        }

        return 'Unknown Device';
    }

    /**
     * تسجيل بصمة جديدة
     */
    async register(deviceName = null) {
        try {
            // التحقق من الدعم
            if (!this.isSupported()) {
                throw new Error('WebAuthn غير مدعوم في هذا المتصفح. يرجى استخدام متصفح حديث.');
            }

            // التحقق من HTTPS (مطلوب لـ WebAuthn إلا في localhost أو الدومين المسموح)
            const hostname = window.location.hostname.toLowerCase();
            const allowedHosts = ['almoustafa.site', 'www.almoustafa.site', 'an.almoustafa.site'];
            const isLocalhost = hostname === 'localhost' || 
                               hostname === '127.0.0.1' || 
                               hostname === '[::1]' ||
                               allowedHosts.includes(hostname) ||
                               hostname.startsWith('192.168.') ||
                               hostname.startsWith('10.');
            
            if (window.location.protocol !== 'https:' && !isLocalhost) {
                throw new Error('WebAuthn يتطلب HTTPS. الموقع الحالي: ' + window.location.protocol + '://' + window.location.hostname);
            }

            // الحصول على اسم الجهاز بشكل تلقائي إن لم يُرسل من الواجهة
            if (!deviceName || deviceName.trim() === '') {
                deviceName = this.detectDeviceName();
            }
            deviceName = deviceName.trim();

            // 1. الحصول على challenge من الخادم
            const challengeResponse = await fetch('api/webauthn_register.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'include', // ✅ إصلاح: استخدام include لإرسال cookies مع الطلب
                body: JSON.stringify({
                    action: 'challenge'
                })
            });

            console.log('WebAuthn Register - Challenge response status:', challengeResponse.status);

            if (!challengeResponse.ok) {
                const errorText = await challengeResponse.text();
                console.error('WebAuthn Register - Challenge error response:', errorText);
                let errorData = null;
                try {
                    errorData = JSON.parse(errorText);
                } catch (e) {
                    // ليس JSON
                }
                
                // ✅ إصلاح: رسالة خطأ أوضح عند فشل التحقق من الجلسة
                if (challengeResponse.status === 401) {
                    const errorMessage = errorData?.message || errorData?.error || 'غير مصرح. يرجى تسجيل الدخول أولاً';
                    console.error('❌ فشل التحقق من الجلسة:', errorMessage);
                    throw new Error(errorMessage);
                }
                
                const errorMessage = errorData?.error || errorData?.message || `خطأ في الاتصال بالخادم: ${challengeResponse.status} - ${errorText.substring(0, 200)}`;
                throw new Error(errorMessage);
            }

            const challengeData = await challengeResponse.json();
            console.log('WebAuthn Register - Challenge data:', challengeData);

            if (!challengeData.success || !challengeData.data) {
                const errorMsg = challengeData.message || challengeData.error || 'فشل في إنشاء التحدي';
                console.error('WebAuthn Register - Challenge creation failed:', errorMsg, challengeData);
                throw new Error(errorMsg);
            }

            const challenge = challengeData.data;

            // 2. تحويل البيانات إلى ArrayBuffer
            const challengeBuffer = this.base64ToArrayBuffer(challenge.challenge);
            const userIdBuffer = this.base64ToArrayBuffer(challenge.user.id);

            // 3. تحويل excludeCredentials
            const excludeCredentials = (challenge.excludeCredentials || [])
                .filter(cred => cred && cred.id)
                .map(cred => {
                    try {
                        return {
                            id: this.base64ToArrayBuffer(cred.id),
                            type: cred.type || 'public-key'
                        };
                    } catch (error) {
                        console.warn('WebAuthn: تجاهل excludeCredential غير صالح', cred, error);
                        return null;
                    }
                })
                .filter(Boolean);

            // 4. إعداد rpId
            let rpId = challenge.rp?.id || window.location.hostname;
            rpId = rpId.replace(/^www\./, '').split(':')[0];

            // 5. إنشاء challenge object - نظام مبسط يعمل على الموبايل
            const pubKeyCredParams = Array.isArray(challenge.pubKeyCredParams) && challenge.pubKeyCredParams.length > 0
                ? challenge.pubKeyCredParams
                : [
                    { type: 'public-key', alg: -7 },   // ES256
                    { type: 'public-key', alg: -257 }  // RS256
                ];

            const authenticatorSelection = { ...(challenge.authenticatorSelection || {}) };

            if (!authenticatorSelection.userVerification) {
                authenticatorSelection.userVerification = 'preferred';
            }

            // إذا لم يحدد الخادم نوع authenticatorAttachment، نتركه فارغاً
            if (!('authenticatorAttachment' in authenticatorSelection)) {
                delete authenticatorSelection.authenticatorAttachment;
            }

            const publicKeyTimeout = typeof challenge.timeout === 'number' ? challenge.timeout : 60000;
            const attestation = challenge.attestation || 'none';

            const publicKeyCredentialCreationOptions = {
                challenge: challengeBuffer,
                rp: {
                    name: challenge.rp?.name || 'نظام الإدارة المتكاملة',
                    id: rpId
                },
                user: {
                    id: userIdBuffer,
                    name: challenge.user.name,
                    displayName: challenge.user.displayName || challenge.user.name
                },
                pubKeyCredParams,
                timeout: publicKeyTimeout,
                attestation
            };

            if (Object.keys(authenticatorSelection).length > 0) {
                publicKeyCredentialCreationOptions.authenticatorSelection = authenticatorSelection;
            }

            if (excludeCredentials.length > 0) {
                publicKeyCredentialCreationOptions.excludeCredentials = excludeCredentials;
            }

            console.log('WebAuthn Registration Options:', {
                rpId: rpId,
                timeout: publicKeyCredentialCreationOptions.timeout,
                authenticatorSelection: publicKeyCredentialCreationOptions.authenticatorSelection,
                attestation: publicKeyCredentialCreationOptions.attestation,
                pubKeyCredParams: publicKeyCredentialCreationOptions.pubKeyCredParams,
                excludeCredentialsCount: excludeCredentials.length
            });

            // 6. إنشاء الاعتماد
            let credential;
            try {
                console.log('Requesting WebAuthn credential...');
                credential = await navigator.credentials.create({
                    publicKey: publicKeyCredentialCreationOptions
                });
                console.log('WebAuthn credential created successfully');
            } catch (error) {
                console.error('WebAuthn error:', error);
                console.error('Error name:', error.name);
                console.error('Error message:', error.message);
                
                // رسالة خطأ أوضح
                let errorMessage = 'فشل في التسجيل البيومتري.';
                if (error.name === 'NotAllowedError') {
                    errorMessage = 'تم إلغاء العملية أو رفض الطلب.\n\nتأكد من:\n1. السماح للموقع بالوصول إلى البصمة/المفتاح\n2. الضغط على "Allow" عند ظهور نافذة البصمة\n3. تفعيل Face ID/Touch ID في إعدادات الجهاز';
                } else if (error.name === 'NotSupportedError') {
                    errorMessage = 'المتصفح أو الجهاز لا يدعم WebAuthn. يرجى استخدام متصفح حديث.';
                } else if (error.name === 'InvalidStateError') {
                    errorMessage = 'البصمة مسجلة بالفعل على هذا الجهاز.';
                } else {
                    errorMessage = 'فشل في التسجيل البيومتري: ' + (error.message || error.name) + '\n\nتأكد من تفعيل البصمة أو Face ID';
                }
                
                throw new Error(errorMessage);
            }

            if (!credential) {
                throw new Error('فشل في إنشاء الاعتماد');
            }

            // 7. تحويل البيانات إلى base64
            // credential.rawId هو نفس credential_id المستخرج من authData في PHP
            // يجب تحويله إلى base64 عادي (ليس base64url) لمطابقة ما سيتم حفظه في قاعدة البيانات
            const credentialId = this.arrayBufferToBase64(credential.rawId);
            const attestationObject = this.arrayBufferToBase64(credential.response.attestationObject);
            const clientDataJSON = this.arrayBufferToBase64(credential.response.clientDataJSON);
            
            console.log('WebAuthn Register - Credential ID (first 50 chars):', credentialId.substring(0, 50));
            console.log('WebAuthn Register - Credential ID length:', credentialId.length);

            // 8. إرسال البيانات للتحقق
            const verifyResponse = await fetch('api/webauthn_register.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'include', // ✅ إصلاح: استخدام include لإرسال cookies مع الطلب
                body: JSON.stringify({
                    action: 'verify',
                    response: {
                        id: credential.id,
                        rawId: credentialId,
                        type: credential.type,
                        response: {
                            clientDataJSON: clientDataJSON,
                            attestationObject: attestationObject
                        },
                        deviceName: deviceName.trim()
                    }
                })
            });

            console.log('WebAuthn Register - Verify response status:', verifyResponse.status);

            if (!verifyResponse.ok) {
                const errorText = await verifyResponse.text();
                console.error('WebAuthn Register - Verify error response:', errorText);
                let errorData = null;
                try {
                    errorData = JSON.parse(errorText);
                } catch (e) {
                    // ليس JSON
                }
                const errorMsg = errorData?.error || errorData?.message || `خطأ في التحقق: ${verifyResponse.status} - ${errorText.substring(0, 200)}`;
                throw new Error(errorMsg);
            }

            const verifyData = await verifyResponse.json();
            
            console.log('WebAuthn Register - Verify response:', verifyData);

            if (!verifyData.success) {
                const errorMsg = verifyData.message || verifyData.error || 'فشل التحقق من البصمة';
                console.error('WebAuthn Register - Verify failed:', errorMsg, verifyData);
                throw new Error(errorMsg);
            }

            return {
                success: true,
                message: verifyData.message || 'تم تسجيل البصمة بنجاح'
            };

        } catch (error) {
            console.error('WebAuthn Registration Error:', error);
            console.error('WebAuthn Registration Error Details:', {
                name: error.name,
                message: error.message,
                stack: error.stack
            });
            
            // معالجة الأخطاء الشائعة
            let errorMessage = 'خطأ في تسجيل البصمة';
            
            if (error.name === 'NotAllowedError') {
                errorMessage = 'تم إلغاء العملية أو رفض الطلب.\n\n' +
                    'تأكد من:\n' +
                    '1. السماح للموقع بالوصول إلى البصمة/المفتاح عند الطلب\n' +
                    '2. الضغط على "Allow" أو "Allow once" عند ظهور نافذة البصمة\n' +
                    '3. تفعيل Face ID/Touch ID في إعدادات الجهاز';
            } else if (error.name === 'NotSupportedError') {
                errorMessage = 'الجهاز أو المتصفح لا يدعم WebAuthn. يرجى استخدام:\n' +
                    '- Chrome 67+\n' +
                    '- Safari 14+ (iOS 14+)\n' +
                    '- Firefox 60+';
            } else if (error.name === 'InvalidStateError') {
                errorMessage = 'البصمة مسجلة بالفعل على هذا الجهاز. احذف البصمة القديمة أولاً.';
            } else if (error.name === 'SecurityError') {
                errorMessage = 'خطأ أمني. تأكد من:\n' +
                    '1. أن الموقع يستخدم HTTPS\n' +
                    '2. أن rpId صحيح\n' +
                    '3. أن الموقع مسموح به في إعدادات الأمان';
            } else if (error.message) {
                errorMessage = error.message;
                // إذا كانت الرسالة تحتوي على تفاصيل تقنية، نبسطها
                if (errorMessage.includes('500') || errorMessage.includes('Internal Server Error')) {
                    errorMessage = 'حدث خطأ في الخادم. يرجى المحاولة مرة أخرى أو الاتصال بالدعم الفني.';
                } else if (errorMessage.includes('خطأ في تحميل النظام')) {
                    errorMessage = 'حدث خطأ في تحميل نظام البصمة. يرجى إعادة تحميل الصفحة والمحاولة مرة أخرى.';
                }
            }

            return {
                success: false,
                message: errorMessage
            };
        }
    }

    /**
     * تسجيل الدخول باستخدام WebAuthn
     */
    async login(username) {
        try {
            // التحقق من الدعم
            if (!this.isSupported()) {
                throw new Error('WebAuthn غير مدعوم في هذا المتصفح. يرجى استخدام متصفح حديث.');
            }

            // التحقق من HTTPS (مطلوب لـ WebAuthn إلا في localhost أو الدومين المسموح)
            const hostname = window.location.hostname.toLowerCase();
            const allowedHosts = ['almoustafa.site', 'www.almoustafa.site', 'an.almoustafa.site'];
            const isLocalhost = hostname === 'localhost' || 
                               hostname === '127.0.0.1' || 
                               hostname === '[::1]' ||
                               allowedHosts.includes(hostname) ||
                               hostname.startsWith('192.168.') ||
                               hostname.startsWith('10.');
            
            if (window.location.protocol !== 'https:' && !isLocalhost) {
                throw new Error('WebAuthn يتطلب HTTPS. الموقع الحالي: ' + window.location.protocol + '://' + window.location.hostname);
            }

            if (!username) {
                throw new Error('اسم المستخدم مطلوب');
            }

            // الحصول على مسار API لتسجيل الدخول
            const loginApiPath = 'api/webauthn_login.php';
            
            console.log('WebAuthn Login API path:', loginApiPath);

            // 1. الحصول على challenge
            const challengeResponse = await fetch(loginApiPath, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    action: 'create_challenge',
                    username: username
                })
            });
            
            console.log('Challenge response status:', challengeResponse.status);

            if (!challengeResponse.ok) {
                throw new Error(`خطأ في الاتصال بالخادم: ${challengeResponse.status}`);
            }

            const challengeData = await challengeResponse.json();

            if (!challengeData.success || !challengeData.challenge) {
                throw new Error(challengeData.error || 'لا توجد بصمات مسجلة لهذا المستخدم');
            }

            const challenge = challengeData.challenge;

            // 2. تحويل البيانات
            challenge.challenge = this.base64ToArrayBuffer(challenge.challenge);

            if (challenge.allowCredentials && Array.isArray(challenge.allowCredentials)) {
                console.log('WebAuthn Login - allowCredentials count:', challenge.allowCredentials.length);
                challenge.allowCredentials = challenge.allowCredentials.map(cred => {
                    try {
                        const idBuffer = this.base64ToArrayBuffer(cred.id);
                        console.log('WebAuthn Login - Converting credential ID (first 30 chars):', cred.id.substring(0, 30));
                        return {
                            id: idBuffer,
                            type: cred.type || 'public-key'
                        };
                    } catch (error) {
                        console.warn('WebAuthn Login: Failed to convert credential ID:', error);
                        return null;
                    }
                }).filter(cred => cred !== null);
                console.log('WebAuthn Login - Converted allowCredentials count:', challenge.allowCredentials.length);
            }

            // 3. إعداد rpId
            let rpId = challenge.rpId || window.location.hostname;
            rpId = rpId.replace(/^www\./, '').split(':')[0];
            challenge.rpId = rpId;

            // 4. إعدادات للموبايل
            const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
            if (isMobile) {
                challenge.timeout = 180000;
                challenge.userVerification = 'preferred';
            }

            if (!challenge.allowCredentials || challenge.allowCredentials.length === 0) {
                throw new Error('لا توجد بصمات مسجلة لهذا المستخدم');
            }

            // 5. الحصول على الاعتماد
            const credential = await navigator.credentials.get({
                publicKey: challenge
            });

            if (!credential) {
                throw new Error('فشل في الحصول على الاعتماد');
            }

            // 6. تحويل البيانات
            const clientDataJSON = this.arrayBufferToBase64(credential.response.clientDataJSON);
            const authenticatorData = this.arrayBufferToBase64(credential.response.authenticatorData);
            const signature = this.arrayBufferToBase64(credential.response.signature);
            // rawId هو نفس credential_id المستخرج من authData عند التسجيل
            // يجب تحويله إلى base64 عادي (ليس base64url) لمطابقة ما تم حفظه في قاعدة البيانات
            const credentialIdBase64 = this.arrayBufferToBase64(credential.rawId);
            
            console.log('WebAuthn Login - Credential ID (first 50 chars):', credentialIdBase64.substring(0, 50));
            console.log('WebAuthn Login - Credential ID length:', credentialIdBase64.length);

            // 7. التحقق من البصمة
            const verifyResponse = await fetch(loginApiPath, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    action: 'verify',
                    response: {
                        id: credential.id,
                        rawId: credentialIdBase64,
                        type: credential.type,
                        response: {
                            clientDataJSON: clientDataJSON,
                            authenticatorData: authenticatorData,
                            signature: signature
                        }
                    }
                })
            });
            
            console.log('WebAuthn Login - Verify response status:', verifyResponse.status, verifyResponse.statusText);
            
            if (!verifyResponse.ok) {
                const errorText = await verifyResponse.text();
                console.error('WebAuthn Login - Verify error response:', errorText);
                let errorData = null;
                try {
                    errorData = JSON.parse(errorText);
                } catch (e) {
                    // ليس JSON
                }
                const errorMsg = errorData?.error || errorData?.message || `خطأ في التحقق: ${verifyResponse.status} - ${errorText.substring(0, 200)}`;
                throw new Error(errorMsg);
            }

            const verifyData = await verifyResponse.json();
            console.log('WebAuthn Login - Verify data:', verifyData);
            console.log('🔍 WebAuthn Login - Cookies before saving user data:', document.cookie);

            if (verifyData.success) {
                console.log('✅ WebAuthn Login - Verification successful');
                console.log('🔍 WebAuthn Login - verifyData.data:', verifyData.data);
                
                // حفظ بيانات المستخدم في localStorage
                if (verifyData.data) {
                    console.log('🔍 WebAuthn Login - Clearing localStorage and sessionStorage...');
                    localStorage.clear();
                    sessionStorage.clear();
                    
                    console.log('🔍 WebAuthn Login - Saving user data to localStorage:', verifyData.data);
                    localStorage.setItem('currentUser', JSON.stringify(verifyData.data));
                    
                    // إضافة علامة تسجيل دخول حديث (مثل تسجيل الدخول العادي)
                    const loginTime = Date.now().toString();
                    sessionStorage.setItem('just_logged_in_time', loginTime);
                    console.log('🔍 WebAuthn Login - Saved just_logged_in_time:', loginTime);
                    console.log('🔍 WebAuthn Login - Cookies after saving user data:', document.cookie);
                    console.log('🔍 WebAuthn Login - localStorage currentUser:', localStorage.getItem('currentUser'));
                    console.log('🔍 WebAuthn Login - sessionStorage just_logged_in_time:', sessionStorage.getItem('just_logged_in_time'));
                } else {
                    console.warn('⚠️ WebAuthn Login - verifyData.data is missing!');
                }
                
                // ✅ تحديد الصفحة المستهدفة للتوجيه
                // التحقق من وجود معامل redirect في URL
                const urlParams = new URLSearchParams(window.location.search);
                let redirectUrl = urlParams.get('redirect');
                
                // إذا لم يكن هناك redirect محدد، استخدم dashboard.html كافتراضي
                if (!redirectUrl || redirectUrl === '') {
                    redirectUrl = 'dashboard.html';
                } else {
                    // ✅ التأكد من أن URL آمن (منع XSS)
                    // إزالة أي محاولات للوصول إلى صفحات خارجية
                    if (redirectUrl.startsWith('http://') || redirectUrl.startsWith('https://') || redirectUrl.startsWith('//')) {
                        console.warn('⚠️ محاولة توجيه غير آمنة تم رفضها:', redirectUrl);
                        redirectUrl = 'dashboard.html';
                    }
                    // التأكد من أن الصفحة موجودة في نفس المجلد
                    if (!redirectUrl.endsWith('.html')) {
                        redirectUrl = 'dashboard.html';
                    }
                }
                
                console.log('✅ تسجيل الدخول WebAuthn ناجح - التوجيه إلى', redirectUrl);
                console.log('🔍 WebAuthn Login - Final cookies before redirect:', document.cookie);
                console.log('🔍 WebAuthn Login - Final localStorage currentUser:', localStorage.getItem('currentUser'));
                console.log('🔍 WebAuthn Login - Final sessionStorage just_logged_in_time:', sessionStorage.getItem('just_logged_in_time'));
                
                // ✅ وضع علامة للصفحة المستهدفة لاستدعاء ensureCSSAndIconsLoaded
                sessionStorage.setItem('after_login_fix_css', 'true');
                
                // ✅ التوجيه مباشرة بعد حفظ البيانات
                // استخدام window.location.href لضمان التوجيه في جميع المتصفحات
                try {
                    console.log('🔍 WebAuthn Login - Attempting redirect to:', redirectUrl);
                    window.location.href = redirectUrl;
                    console.log('✅ WebAuthn Login - Redirect initiated successfully');
                } catch (error) {
                    console.error('❌ خطأ في التوجيه:', error);
                    // محاولة بديلة باستخدام replace
                    try {
                        console.log('🔍 WebAuthn Login - Attempting redirect with replace...');
                        window.location.replace(redirectUrl);
                    } catch (replaceError) {
                        console.error('❌ خطأ في التوجيه البديل:', replaceError);
                        // آخر محاولة - استخدام assign
                        console.log('🔍 WebAuthn Login - Attempting redirect with assign...');
                        window.location.assign(redirectUrl);
                    }
                }
                
                return {
                    success: true,
                    message: 'تم تسجيل الدخول بنجاح',
                    redirect: redirectUrl
                };
            } else {
                throw new Error(verifyData.error || 'فشل التحقق من البصمة');
            }

        } catch (error) {
            console.error('WebAuthn Login Error:', error);
            
            let errorMessage = 'خطأ في تسجيل الدخول';
            if (error.message) {
                errorMessage = error.message;
            } else if (error.name === 'NotAllowedError') {
                errorMessage = 'تم إلغاء العملية. يرجى المحاولة مرة أخرى.';
            } else if (error.name === 'NotSupportedError') {
                errorMessage = 'الجهاز أو المتصفح لا يدعم WebAuthn';
            }

            alert(errorMessage);
            return false;
        }
    }

    /**
     * الحصول على الحسابات المرتبطة بالبصمة على الجهاز
     */
    async getAccountsWithCredentials() {
        try {
            const response = await fetch('api/webauthn_accounts.php', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin'
            });

            if (!response.ok) {
                throw new Error(`خطأ في الاتصال بالخادم: ${response.status}`);
            }

            const data = await response.json();

            if (!data.success) {
                throw new Error(data.error || 'فشل في الحصول على الحسابات');
            }

            return {
                success: true,
                accounts: data.accounts || []
            };
        } catch (error) {
            console.error('WebAuthn Get Accounts Error:', error);
            return {
                success: false,
                message: error.message || 'خطأ في الحصول على الحسابات',
                accounts: []
            };
        }
    }

    /**
     * تسجيل الدخول بالبصمة - يعرض قائمة الحسابات أو يسجل دخول تلقائياً
     */
    async loginWithBiometric() {
        try {
            // التحقق من الدعم
            if (!this.isSupported()) {
                // رسالة توضيحية أكثر
                const supportInfo = this.getSupportInfo();
                throw new Error('WebAuthn غير مدعوم في هذا المتصفح.\n\n' + supportInfo);
            }

            // التحقق من HTTPS (مطلوب لـ WebAuthn إلا في localhost أو الدومين المسموح)
            const hostname = window.location.hostname.toLowerCase();
            const allowedHosts = ['almoustafa.site', 'www.almoustafa.site', 'an.almoustafa.site'];
            const isLocalhost = hostname === 'localhost' || 
                               hostname === '127.0.0.1' || 
                               hostname === '[::1]' ||
                               allowedHosts.includes(hostname) ||
                               hostname.startsWith('192.168.') ||
                               hostname.startsWith('10.');
            
            if (window.location.protocol !== 'https:' && !isLocalhost) {
                throw new Error('WebAuthn يتطلب HTTPS. الموقع الحالي: ' + window.location.protocol + '://' + window.location.hostname);
            }

            // الحصول على الحسابات المرتبطة بالبصمة
            const accountsResult = await this.getAccountsWithCredentials();

            if (!accountsResult.success || !accountsResult.accounts || accountsResult.accounts.length === 0) {
                throw new Error('لا توجد حسابات مرتبطة بالبصمة على هذا الجهاز');
            }

            const accounts = accountsResult.accounts;

            // إذا كان هناك حساب واحد فقط، تسجيل الدخول تلقائياً
            if (accounts.length === 1) {
                const account = accounts[0];
                return await this.login(account.username);
            }

            // إذا كان هناك أكثر من حساب، عرض قائمة للاختيار
            return await this.showAccountSelection(accounts);

        } catch (error) {
            console.error('WebAuthn Biometric Login Error:', error);
            
            let errorMessage = 'خطأ في تسجيل الدخول بالبصمة';
            if (error.message) {
                errorMessage = error.message;
            }

            return {
                success: false,
                message: errorMessage
            };
        }
    }

    /**
     * عرض قائمة اختيار الحساب
     */
    async showAccountSelection(accounts) {
        return new Promise((resolve) => {
            // إنشاء modal لعرض الحسابات
            const modal = document.createElement('div');
            modal.className = 'webauthn-account-selection-modal';
            modal.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.7);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 10000;
                backdrop-filter: blur(5px);
            `;

            const modalContent = document.createElement('div');
            modalContent.style.cssText = `
                background: white;
                border-radius: 15px;
                padding: 30px;
                max-width: 500px;
                width: 90%;
                max-height: 80vh;
                overflow-y: auto;
                box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
                direction: rtl;
            `;

            modalContent.innerHTML = `
                <h2 style="margin: 0 0 20px 0; text-align: center; color: #333; font-family: 'Cairo', sans-serif;">
                    <i class="bi bi-fingerprint" style="margin-left: 10px;"></i>
                    اختر الحساب لتسجيل الدخول
                </h2>
                <div id="webauthn-accounts-list" style="margin-bottom: 20px;"></div>
                <button id="webauthn-cancel-btn" style="
                    width: 100%;
                    padding: 12px;
                    background: #f44336;
                    color: white;
                    border: none;
                    border-radius: 8px;
                    font-size: 16px;
                    cursor: pointer;
                    font-family: 'Cairo', sans-serif;
                ">إلغاء</button>
            `;

            const accountsList = modalContent.querySelector('#webauthn-accounts-list');
            
            accounts.forEach((account, index) => {
                const accountItem = document.createElement('div');
                accountItem.style.cssText = `
                    padding: 15px;
                    margin-bottom: 10px;
                    border: 2px solid #e0e0e0;
                    border-radius: 10px;
                    cursor: pointer;
                    transition: all 0.3s;
                    background: #f9f9f9;
                `;

                accountItem.innerHTML = `
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <div style="font-weight: bold; font-size: 18px; color: #333; margin-bottom: 5px;">
                                ${account.name || account.username}
                            </div>
                            <div style="color: #666; font-size: 14px;">
                                ${account.username} • ${account.role === 'admin' ? 'مدير' : account.role === 'manager' ? 'مدير' : 'موظف'}
                            </div>
                            <div style="color: #999; font-size: 12px; margin-top: 5px;">
                                ${account.credentials_count || 0} بصمة مسجلة
                            </div>
                        </div>
                        <i class="bi bi-chevron-left" style="font-size: 24px; color: #2196F3;"></i>
                    </div>
                `;

                accountItem.addEventListener('click', async () => {
                    modalContent.style.opacity = '0.5';
                    modalContent.style.pointerEvents = 'none';
                    
                    try {
                        const result = await simpleWebAuthn.login(account.username);
                        if (result && result.success) {
                            document.body.removeChild(modal);
                            resolve(result);
                        } else {
                            modalContent.style.opacity = '1';
                            modalContent.style.pointerEvents = 'auto';
                            alert(result?.message || 'فشل تسجيل الدخول');
                        }
                    } catch (error) {
                        modalContent.style.opacity = '1';
                        modalContent.style.pointerEvents = 'auto';
                        alert('حدث خطأ: ' + error.message);
                    }
                });

                accountItem.addEventListener('mouseenter', () => {
                    accountItem.style.borderColor = '#2196F3';
                    accountItem.style.background = '#e3f2fd';
                });

                accountItem.addEventListener('mouseleave', () => {
                    accountItem.style.borderColor = '#e0e0e0';
                    accountItem.style.background = '#f9f9f9';
                });

                accountsList.appendChild(accountItem);
            });

            const cancelBtn = modalContent.querySelector('#webauthn-cancel-btn');
            cancelBtn.addEventListener('click', () => {
                document.body.removeChild(modal);
                resolve({
                    success: false,
                    message: 'تم الإلغاء'
                });
            });

            modal.appendChild(modalContent);
            document.body.appendChild(modal);
        });
    }
}

// إنشاء كائن عام
const simpleWebAuthn = new SimpleWebAuthn();

// للتوافق مع الكود القديم
const webauthnManager = {
    login: (username) => simpleWebAuthn.login(username),
    register: () => simpleWebAuthn.register(),
    loginWithBiometric: () => simpleWebAuthn.loginWithBiometric(),
    getAccountsWithCredentials: () => simpleWebAuthn.getAccountsWithCredentials()
};

