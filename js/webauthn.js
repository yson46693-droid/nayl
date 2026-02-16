/**
 * WebAuthn - تسجيل الدخول بالبصمة / Face ID / مفتاح المرور
 * يدعم المتصفحات والأجهزة الحديثة مع عرض رسالة واضحة للأجهزة غير المدعومة
 */

(function () {
    'use strict';

    /**
     * التحقق من دعم WebAuthn في المتصفح
     * @returns {boolean}
     */
    function isWebAuthnSupported() {
        return typeof window.PublicKeyCredential === 'function' &&
            typeof window.navigator.credentials !== 'undefined' &&
            typeof window.navigator.credentials.create === 'function' &&
            typeof window.navigator.credentials.get === 'function';
    }

    /**
     * تحويل base64url إلى ArrayBuffer (للعميل)
     * @param {string} base64url
     * @returns {ArrayBuffer}
     */
    function base64urlToBuffer(base64url) {
        const padding = '='.repeat((4 - (base64url.length % 4)) % 4);
        const base64 = (base64url + padding).replace(/-/g, '+').replace(/_/g, '/');
        const bin = atob(base64);
        const bytes = new Uint8Array(bin.length);
        for (let i = 0; i < bin.length; i++) {
            bytes[i] = bin.charCodeAt(i);
        }
        return bytes.buffer;
    }

    /**
     * تحويل ArrayBuffer إلى base64url
     * @param {ArrayBuffer} buffer
     * @returns {string}
     */
    function bufferToBase64url(buffer) {
        const bytes = new Uint8Array(buffer);
        let binary = '';
        for (let i = 0; i < bytes.length; i++) {
            binary += String.fromCharCode(bytes[i]);
        }
        return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    }

    /**
     * مسار الـ API (نسبي من الصفحة الحالية - يعمل مع php -S و XAMPP)
     * من index.html أو profile.html يُصبح نفس المجلد ثم api/auth/webauthn/...
     */
    function getWebAuthnApiPath(path) {
        return 'api/auth/webauthn/' + path;
    }

    /**
     * تحويل الاستجابة إلى JSON بأمان (تجنب Unexpected token '<' عند إرجاع HTML)
     * @param {Response} res
     * @returns {Promise<object>}
     */
    function parseJsonResponse(res) {
        var ct = (res.headers.get('Content-Type') || '').toLowerCase();
        return res.text().then(function (text) {
            var trimmed = (text || '').trim();
            if (trimmed.indexOf('{') === 0) {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    throw new Error('فشل قراءة رد الخادم. تأكد من أن الـ API يعمل بشكل صحيح.');
                }
            }
            if (trimmed.indexOf('<') === 0) {
                throw new Error('الخادم أعاد صفحة HTML بدلاً من JSON (قد يكون خطأ 404 أو 500). تحقق من مسار الـ API وأن الخادم يعمل.');
            }
            throw new Error('رد الخادم غير متوقع. تحقق من أن الـ API يعمل.');
        });
    }

    /**
     * الحصول على session token (من cookie أو localStorage)
     * @returns {string|null}
     */
    function getSessionToken() {
        const match = document.cookie.match(/session_token=([^;]+)/);
        if (match) return match[1].trim();
        return localStorage.getItem('sessionToken') || sessionStorage.getItem('sessionToken');
    }

    /**
     * اكتشاف اسم/نوع الجهاز من المتصفح لعرضه في جدول البصمة
     * @returns {string}
     */
    function getDeviceDisplayName() {
        var ua = typeof navigator !== 'undefined' && navigator.userAgent ? navigator.userAgent : '';
        var platform = typeof navigator !== 'undefined' && navigator.platform ? navigator.platform : '';
        var name = 'جهاز كمبيوتر';
        if (/iPad/i.test(ua)) {
            name = 'iPad';
        } else if (/Android/i.test(ua)) {
            name = /Mobile/i.test(ua) ? 'هاتف Android' : 'جهاز لوحي Android';
        } else if (/iPhone|iPod/i.test(ua)) {
            name = /iPhone/i.test(ua) ? 'iPhone' : 'iPod';
        } else if (/Mobile|BlackBerry|IEMobile|Opera Mini/i.test(ua)) {
            name = 'هاتف محمول';
        } else {
            if (/Windows/i.test(ua) || /Win/i.test(platform)) name = 'كمبيوتر Windows';
            else if (/Mac/i.test(ua) || /Mac/i.test(platform)) name = 'Mac';
            else if (/Linux/i.test(ua) || /Linux/i.test(platform)) name = 'كمبيوتر Linux';
        }
        var browser = 'متصفح';
        if (/Edg\//i.test(ua)) browser = 'Edge';
        else if (/OPR|Opera\//i.test(ua)) browser = 'Opera';
        else if (/Firefox\//i.test(ua)) browser = 'Firefox';
        else if (/Chrome\//i.test(ua) && !/Edg|OPR|Opera/i.test(ua)) browser = 'Chrome';
        else if (/Safari\//i.test(ua) && !/Chrome|CriOS|FxiOS/i.test(ua)) browser = 'Safari';
        return name + ' - ' + browser;
    }

    /**
     * إعداد خيارات التسجيل للعميل (من صيغة السيرفر)
     * @param {object} options
     * @returns {CredentialCreationOptions}
     */
    function prepareRegisterOptions(options) {
        const user = options.user || {};
        const rp = options.rp || {};
        return {
            publicKey: {
                challenge: base64urlToBuffer(options.challenge),
                rp: {
                    name: rp.name || 'AmrNayl Academy',
                    id: rp.id || undefined
                },
                user: {
                    id: base64urlToBuffer(user.id),
                    name: user.name || '',
                    displayName: user.displayName || user.name || ''
                },
                pubKeyCredParams: options.pubKeyCredParams || [
                    { type: 'public-key', alg: -7 },
                    { type: 'public-key', alg: -257 }
                ],
                timeout: options.timeout || 60000,
                authenticatorSelection: options.authenticatorSelection || {
                    userVerification: 'preferred',
                    residentKey: 'preferred',
                    requireResidentKey: false
                },
                excludeCredentials: (options.excludeCredentials || []).map(function (cred) {
                    return {
                        type: 'public-key',
                        id: typeof cred.id === 'string' ? base64urlToBuffer(cred.id) : cred.id,
                        transports: cred.transports || ['internal', 'hybrid', 'usb', 'nfc', 'ble']
                    };
                })
            }
        };
    }

    /**
     * إعداد خيارات تسجيل الدخول للعميل
     * @param {object} options
     * @returns {CredentialRequestOptions}
     */
    function prepareLoginOptions(options) {
        return {
            publicKey: {
                challenge: base64urlToBuffer(options.challenge),
                rpId: options.rpId || undefined,
                timeout: options.timeout || 60000,
                userVerification: options.userVerification || 'preferred',
                allowCredentials: (options.allowCredentials || []).map(function (cred) {
                    return {
                        type: 'public-key',
                        id: typeof cred.id === 'string' ? base64urlToBuffer(cred.id) : cred.id,
                        transports: cred.transports || ['internal', 'hybrid', 'usb', 'nfc', 'ble']
                    };
                })
            }
        };
    }

    /**
     * تسجيل البصمة (من صفحة الملف الشخصي - يتطلب تسجيل الدخول)
     * @param {function} onSuccess
     * @param {function} onError
     */
    function registerFingerprint(onSuccess, onError) {
        if (!isWebAuthnSupported()) {
            if (onError) onError('المتصفح لا يدعم تسجيل البصمة');
            return;
        }

        const token = getSessionToken();
        if (!token) {
            if (onError) onError('يجب تسجيل الدخول أولاً');
            return;
        }

        fetch(getWebAuthnApiPath('register-options.php'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include'
        })
            .then(function (res) { return parseJsonResponse(res); })
            .then(function (result) {
                if (!result.success || !result.data) {
                    throw new Error(result.error || 'فشل جلب خيارات التسجيل');
                }
                return navigator.credentials.create(prepareRegisterOptions(result.data));
            })
            .then(function (credential) {
                if (!credential) throw new Error('لم يتم إنشاء البصمة');

                const response = credential.response;
                const clientDataJSON = response.clientDataJSON;
                const attestationObject = response.attestationObject;

                const payload = {
                    credential: {
                        id: credential.id,
                        rawId: bufferToBase64url(credential.rawId),
                        type: credential.type,
                        response: {
                            clientDataJSON: bufferToBase64url(clientDataJSON),
                            attestationObject: bufferToBase64url(attestationObject)
                        }
                    },
                    deviceName: getDeviceDisplayName()
                };

                return fetch(getWebAuthnApiPath('register-verify.php'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify(payload)
                });
            })
            .then(function (res) { return parseJsonResponse(res); })
            .then(function (result) {
                if (!result.success) {
                    throw new Error(result.error || 'فشل حفظ البصمة');
                }
                if (onSuccess) onSuccess(result.data);
            })
            .catch(function (err) {
                if (onError) {
                    const msg = err.message || (err.name === 'NotAllowedError' ? 'تم إلغاء التسجيل من قبل المستخدم' : 'حدث خطأ أثناء تسجيل البصمة');
                    onError(msg);
                }
            });
    }

    /**
     * تسجيل الدخول بالبصمة
     * إذا لم يُمرّر إيميل: يفحص البصمات المسجلة على هذا الجهاز للموقع ويسجّل الدخول للحساب المرتبط.
     * @param {string} [email] - البريد الإلكتروني (اختياري) — إن وُجد يُستخدم للتقيد ببصمات هذا الحساب فقط
     * @param {boolean} remember - تذكرني
     * @param {function} onSuccess - (user, session)
     * @param {function} onError - (message)
     */
    function loginWithFingerprint(email, remember, onSuccess, onError) {
        if (!isWebAuthnSupported()) {
            if (onError) onError('المتصفح لا يدعم تسجيل الدخول بالبصمة');
            return;
        }

        var useDiscoverable = !email || !String(email).trim();
        var body = useDiscoverable ? {} : { email: String(email).trim() };

        fetch(getWebAuthnApiPath('login-options.php'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify(body)
        })
            .then(function (res) { return parseJsonResponse(res); })
            .then(function (result) {
                if (!result.success || !result.data) {
                    throw new Error(result.error || 'فشل جلب خيارات تسجيل الدخول');
                }
                return navigator.credentials.get(prepareLoginOptions(result.data));
            })
            .then(function (credential) {
                if (!credential) throw new Error('لم يتم التحقق من البصمة');

                const response = credential.response;
                const payload = {
                    credential: {
                        id: credential.id,
                        rawId: bufferToBase64url(credential.rawId),
                        type: credential.type,
                        response: {
                            clientDataJSON: bufferToBase64url(response.clientDataJSON),
                            authenticatorData: bufferToBase64url(response.authenticatorData),
                            signature: bufferToBase64url(response.signature),
                            userHandle: response.userHandle ? bufferToBase64url(response.userHandle) : null
                        }
                    },
                    remember: !!remember
                };

                return fetch(getWebAuthnApiPath('login-verify.php'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify(payload)
                });
            })
            .then(function (res) { return parseJsonResponse(res); })
            .then(function (result) {
                if (!result.success || !result.data) {
                    throw new Error(result.error || 'فشل تسجيل الدخول');
                }
                if (onSuccess) onSuccess(result.data.user, result.data.session);
            })
            .catch(function (err) {
                if (onError) {
                    var msg = err.message || 'حدث خطأ أثناء تسجيل الدخول بالبصمة';
                    if (err.name === 'NotAllowedError') {
                        msg = useDiscoverable
                            ? 'لا توجد بصمة مسجلة على هذا الجهاز لهذا الموقع، أو تم إلغاء الطلب. سجّل الدخول بالبريد وكلمة المرور ثم فعّل البصمة من الملف الشخصي (الأمان).'
                            : 'تم إلغاء تسجيل الدخول أو انتهت المهلة';
                    }
                    onError(msg);
                }
            });
    }

    // تصدير للاستخدام العام
    window.WebAuthn = {
        isSupported: isWebAuthnSupported,
        register: registerFingerprint,
        login: loginWithFingerprint,
        base64urlToBuffer: base64urlToBuffer,
        bufferToBase64url: bufferToBase64url
    };
})();
