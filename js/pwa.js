let deferredPrompt;
const installBtn = document.getElementById('installAppBtn');
const installModal = document.getElementById('installModal');

// اكتشاف نوع الجهاز والمتصفح
function getDeviceInfo() {
    const ua = navigator.userAgent;
    const isIOS = /iPad|iPhone|iPod/.test(ua) && !window.MSStream;
    const isAndroid = /Android/.test(ua);
    const isSafari = /^((?!chrome|android).)*safari/i.test(ua);
    const isChrome = /Chrome/.test(ua) && !/Edg|OPR/.test(ua);
    const isEdge = /Edg/.test(ua);
    const isFirefox = /Firefox/.test(ua);
    const isOpera = /OPR/.test(ua);
    const isSamsung = /SamsungBrowser/.test(ua);
    const isMac = /Mac/.test(ua) && !isIOS;
    const isWindows = /Windows/.test(ua);
    const isLinux = /Linux/.test(ua) && !isAndroid;
    const isStandalone = window.matchMedia('(display-mode: standalone)').matches ||
        window.navigator.standalone === true;

    return {
        isIOS, isAndroid, isSafari, isChrome, isEdge,
        isFirefox, isOpera, isSamsung, isMac, isWindows, isLinux, isStandalone
    };
}

// تسجيل Service Worker
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('./sw.js')
            .then(registration => {
                console.log('ServiceWorker registration successful with scope:', registration.scope);
                // التحقق من التحديثات
                registration.addEventListener('updatefound', () => {
                    const newWorker = registration.installing;
                    console.log('New service worker being installed');
                });
            })
            .catch(err => {
                console.log('ServiceWorker registration failed: ', err);
            });
    });
}

// الاستماع لحدث قابلية التثبيت (Chrome, Edge, Opera, Samsung Internet)
window.addEventListener('beforeinstallprompt', (e) => {
    // منع ظهور النافذة التلقائية
    e.preventDefault();
    // حفظ الحدث لاستخدامه لاحقاً
    deferredPrompt = e;
    console.log('beforeinstallprompt fired - app is installable');

    // إظهار زر التثبيت
    if (installBtn) {
        installBtn.style.display = 'flex';
    }
});

// التحقق مما إذا كان التطبيق مثبتاً بالفعل
window.addEventListener('appinstalled', () => {
    console.log('PWA was installed');
    deferredPrompt = null;
    // إخفاء زر التثبيت
    if (installBtn) {
        installBtn.style.display = 'none';
    }
});

// إظهار/إخفاء زر التثبيت حسب الحالة
window.addEventListener('load', () => {
    const device = getDeviceInfo();

    // إخفاء الزر إذا كان التطبيق مثبتاً بالفعل
    if (device.isStandalone) {
        if (installBtn) installBtn.style.display = 'none';
        return;
    }

    // إظهار زر التثبيت على iOS/Safari وأي متصفح لا يدعم beforeinstallprompt
    if (installBtn) {
        installBtn.style.display = 'flex';
    }
});

// التعامل مع النقر على زر التثبيت
if (installBtn) {
    installBtn.addEventListener('click', async () => {
        const device = getDeviceInfo();

        // إذا كان التطبيق مثبتاً بالفعل
        if (device.isStandalone) {
            alert('التطبيق مثبت بالفعل على جهازك!');
            return;
        }

        if (deferredPrompt) {
            // إذا كان التثبيت التلقائي مدعوماً
            try {
                deferredPrompt.prompt();
                const { outcome } = await deferredPrompt.userChoice;
                console.log(`User response to the install prompt: ${outcome}`);
                if (outcome === 'accepted') {
                    console.log('User accepted the install prompt');
                }
            } catch (error) {
                console.log('Install prompt error:', error);
            }
            deferredPrompt = null;
        } else {
            // عرض تعليمات التثبيت اليدوي
            showInstallInstructions();
        }
    });
}

function showInstallInstructions() {
    const device = getDeviceInfo();
    const modalContent = document.getElementById('installInstructions');

    if (device.isIOS) {
        // تعليمات iOS (iPhone/iPad)
        modalContent.innerHTML = `
            <div style="text-align: center; margin-bottom: 20px;">
                <i class="bi bi-phone" style="font-size: 3rem; color: #2196f3;"></i>
                <h4 style="margin: 10px 0 5px; color: #1a2332;">تثبيت التطبيق على iPhone/iPad</h4>
                <p style="color: #64748b; font-size: 0.9rem;">اتبع الخطوات التالية لإضافة التطبيق للشاشة الرئيسية</p>
            </div>
            
            <div class="install-step" style="display: flex; align-items: flex-start; gap: 15px; padding: 15px; background: linear-gradient(135deg, #f0f7ff 0%, #e8f4fd 100%); border-radius: 12px; margin-bottom: 12px; border-right: 4px solid #2196f3;">
                <div style="background: #2196f3; color: white; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; flex-shrink: 0;">1</div>
                <div style="text-align: right;">
                    <p style="margin: 0 0 5px; font-weight: 600; color: #1a2332;">افتح المتصفح Safari</p>
                    <p style="margin: 0; color: #64748b; font-size: 0.85rem;">تأكد من فتح هذا الموقع في متصفح Safari وليس Chrome أو أي متصفح آخر</p>
                </div>
            </div>
            
            <div class="install-step" style="display: flex; align-items: flex-start; gap: 15px; padding: 15px; background: linear-gradient(135deg, #f0f7ff 0%, #e8f4fd 100%); border-radius: 12px; margin-bottom: 12px; border-right: 4px solid #2196f3;">
                <div style="background: #2196f3; color: white; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; flex-shrink: 0;">2</div>
                <div style="text-align: right;">
                    <p style="margin: 0 0 5px; font-weight: 600; color: #1a2332;">اضغط على زر المشاركة <i class="bi bi-box-arrow-up" style="color: #2196f3;"></i></p>
                    <p style="margin: 0; color: #64748b; font-size: 0.85rem;">ستجده في أسفل الشاشة (شريط الأدوات)</p>
                </div>
            </div>
            
            <div class="install-step" style="display: flex; align-items: flex-start; gap: 15px; padding: 15px; background: linear-gradient(135deg, #f0f7ff 0%, #e8f4fd 100%); border-radius: 12px; margin-bottom: 12px; border-right: 4px solid #2196f3;">
                <div style="background: #2196f3; color: white; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; flex-shrink: 0;">3</div>
                <div style="text-align: right;">
                    <p style="margin: 0 0 5px; font-weight: 600; color: #1a2332;">اختر "إضافة إلى الشاشة الرئيسية" <i class="bi bi-plus-square" style="color: #2196f3;"></i></p>
                    <p style="margin: 0; color: #64748b; font-size: 0.85rem;">قم بالتمرير لأسفل في القائمة حتى تجد هذا الخيار</p>
                </div>
            </div>
            
            <div class="install-step" style="display: flex; align-items: flex-start; gap: 15px; padding: 15px; background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); border-radius: 12px; border-right: 4px solid #4caf50;">
                <div style="background: #4caf50; color: white; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; flex-shrink: 0;">4</div>
                <div style="text-align: right;">
                    <p style="margin: 0 0 5px; font-weight: 600; color: #1a2332;">اضغط "إضافة" <i class="bi bi-check-circle-fill" style="color: #4caf50;"></i></p>
                    <p style="margin: 0; color: #64748b; font-size: 0.85rem;">سيظهر التطبيق على شاشتك الرئيسية فوراً!</p>
                </div>
            </div>
        `;
    } else if (device.isAndroid) {
        // تعليمات Android
        let browserInstructions = '';

        if (device.isSamsung) {
            browserInstructions = `
                <div style="background: linear-gradient(135deg, #e8e0f0 0%, #d4c4e8 100%); padding: 15px; border-radius: 12px; margin-bottom: 15px; border-right: 4px solid #6b5b95;">
                    <p style="margin: 0 0 10px; font-weight: 700; color: #4a3f6b;"><i class="bi bi-browser-safari" style="margin-left: 8px;"></i>Samsung Internet</p>
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <p style="margin: 0; color: #5d4e7e; font-size: 0.9rem;">1. اضغط على <i class="bi bi-three-dots-vertical"></i> (ثلاث نقاط أو خطوط)</p>
                        <p style="margin: 0; color: #5d4e7e; font-size: 0.9rem;">2. اختر "إضافة صفحة إلى" ثم "الشاشة الرئيسية"</p>
                        <p style="margin: 0; color: #5d4e7e; font-size: 0.9rem;">3. اضغط "إضافة" للتأكيد</p>
                    </div>
                </div>
            `;
        } else if (device.isFirefox) {
            browserInstructions = `
                <div style="background: linear-gradient(135deg, #ffe8e0 0%, #ffd0c0 100%); padding: 15px; border-radius: 12px; margin-bottom: 15px; border-right: 4px solid #ff6b35;">
                    <p style="margin: 0 0 10px; font-weight: 700; color: #d34500;"><i class="bi bi-globe" style="margin-left: 8px;"></i>Firefox</p>
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <p style="margin: 0; color: #993300; font-size: 0.9rem;">1. اضغط على <i class="bi bi-three-dots-vertical"></i> (ثلاث نقاط)</p>
                        <p style="margin: 0; color: #993300; font-size: 0.9rem;">2. اختر "تثبيت" أو "Install"</p>
                        <p style="margin: 0; color: #993300; font-size: 0.9rem;">3. اضغط "إضافة" للتأكيد</p>
                    </div>
                </div>
            `;
        } else if (device.isOpera) {
            browserInstructions = `
                <div style="background: linear-gradient(135deg, #ffe0e5 0%, #ffc0c8 100%); padding: 15px; border-radius: 12px; margin-bottom: 15px; border-right: 4px solid #ff1b2d;">
                    <p style="margin: 0 0 10px; font-weight: 700; color: #cc0000;"><i class="bi bi-globe" style="margin-left: 8px;"></i>Opera</p>
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <p style="margin: 0; color: #990000; font-size: 0.9rem;">1. اضغط على <i class="bi bi-three-dots-vertical"></i> في أسفل الشاشة</p>
                        <p style="margin: 0; color: #990000; font-size: 0.9rem;">2. اختر "الشاشة الرئيسية" أو "Home screen"</p>
                        <p style="margin: 0; color: #990000; font-size: 0.9rem;">3. اضغط "إضافة" للتأكيد</p>
                    </div>
                </div>
            `;
        } else {
            // Chrome أو متصفح افتراضي
            browserInstructions = `
                <div style="background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%); padding: 15px; border-radius: 12px; margin-bottom: 15px; border-right: 4px solid #ff9800;">
                    <p style="margin: 0 0 10px; font-weight: 700; color: #e65100;"><i class="bi bi-browser-chrome" style="margin-left: 8px;"></i>Chrome / المتصفح الافتراضي</p>
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <p style="margin: 0; color: #5d4037; font-size: 0.9rem;">1. اضغط على <i class="bi bi-three-dots-vertical"></i> (ثلاث نقاط) أعلى يمين المتصفح</p>
                        <p style="margin: 0; color: #5d4037; font-size: 0.9rem;">2. اختر "تثبيت التطبيق" أو "إضافة إلى الشاشة الرئيسية"</p>
                        <p style="margin: 0; color: #5d4037; font-size: 0.9rem;">3. اضغط "تثبيت" في النافذة المنبثقة</p>
                    </div>
                </div>
            `;
        }

        modalContent.innerHTML = `
            <div style="text-align: center; margin-bottom: 20px;">
                <i class="bi bi-phone" style="font-size: 3rem; color: #4caf50;"></i>
                <h4 style="margin: 10px 0 5px; color: #1a2332;">تثبيت التطبيق على Android</h4>
                <p style="color: #64748b; font-size: 0.9rem;">اتبع خطوات المتصفح الذي تستخدمه</p>
            </div>
            ${browserInstructions}
            <div style="background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); padding: 15px; border-radius: 12px; text-align: center;">
                <i class="bi bi-check-circle-fill" style="font-size: 2rem; color: #4caf50;"></i>
                <p style="margin: 10px 0 0; color: #2e7d32; font-weight: 600;">سيظهر التطبيق على شاشتك الرئيسية!</p>
            </div>
        `;
    } else {
        // تعليمات سطح المكتب (Windows, Mac, Linux)
        let osInstructions = '';

        if (device.isMac && device.isSafari) {
            osInstructions = `
                <div style="background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); padding: 15px; border-radius: 12px; margin-bottom: 15px; border-right: 4px solid #2196f3;">
                    <p style="margin: 0 0 10px; font-weight: 700; color: #1565c0;"><i class="bi bi-apple" style="margin-left: 8px;"></i>Safari على Mac</p>
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <p style="margin: 0; color: #1a237e; font-size: 0.9rem;">1. اضغط على "ملف" (File) في شريط القوائم</p>
                        <p style="margin: 0; color: #1a237e; font-size: 0.9rem;">2. اختر "إضافة إلى Dock" (Add to Dock)</p>
                        <p style="margin: 0; color: #1a237e; font-size: 0.9rem;">3. سيظهر التطبيق في Dock الخاص بك</p>
                    </div>
                </div>
            `;
        } else if (device.isEdge) {
            osInstructions = `
                <div style="background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); padding: 15px; border-radius: 12px; margin-bottom: 15px; border-right: 4px solid #0078d4;">
                    <p style="margin: 0 0 10px; font-weight: 700; color: #0050a0;"><i class="bi bi-browser-edge" style="margin-left: 8px;"></i>Microsoft Edge</p>
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <p style="margin: 0; color: #003870; font-size: 0.9rem;">1. اضغط على <i class="bi bi-three-dots"></i> (ثلاث نقاط) أعلى يمين المتصفح</p>
                        <p style="margin: 0; color: #003870; font-size: 0.9rem;">2. اختر "التطبيقات" (Apps) ثم "تثبيت هذا الموقع كتطبيق"</p>
                        <p style="margin: 0; color: #003870; font-size: 0.9rem;">3. أو ابحث عن أيقونة <i class="bi bi-plus-circle"></i> في شريط العنوان</p>
                    </div>
                </div>
            `;
        } else if (device.isFirefox) {
            osInstructions = `
                <div style="background: linear-gradient(135deg, #ffe8e0 0%, #ffd0c0 100%); padding: 15px; border-radius: 12px; margin-bottom: 15px; border-right: 4px solid #ff6b35;">
                    <p style="margin: 0 0 10px; font-weight: 700; color: #d34500;"><i class="bi bi-globe" style="margin-left: 8px;"></i>Firefox</p>
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <p style="margin: 0; color: #993300; font-size: 0.9rem;">Firefox لا يدعم تثبيت PWA على سطح المكتب حالياً</p>
                        <p style="margin: 0; color: #993300; font-size: 0.9rem;">يُنصح باستخدام Chrome أو Edge للتثبيت</p>
                        <p style="margin: 0; color: #993300; font-size: 0.9rem;">أو يمكنك إضافة الموقع للمفضلة</p>
                    </div>
                </div>
            `;
        } else {
            // Chrome أو متصفحات أخرى
            osInstructions = `
                <div style="background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%); padding: 15px; border-radius: 12px; margin-bottom: 15px; border-right: 4px solid #ff9800;">
                    <p style="margin: 0 0 10px; font-weight: 700; color: #e65100;"><i class="bi bi-browser-chrome" style="margin-left: 8px;"></i>Chrome</p>
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <p style="margin: 0; color: #5d4037; font-size: 0.9rem;">1. ابحث عن أيقونة <i class="bi bi-download"></i> في شريط العنوان</p>
                        <p style="margin: 0; color: #5d4037; font-size: 0.9rem;">2. أو اضغط على <i class="bi bi-three-dots-vertical"></i> ثم اختر "تثبيت AmrNayl Academy"</p>
                        <p style="margin: 0; color: #5d4037; font-size: 0.9rem;">3. اضغط "تثبيت" في النافذة المنبثقة</p>
                    </div>
                </div>
            `;
        }

        let osIcon = 'bi-laptop';
        let osName = 'جهازك';
        if (device.isWindows) {
            osIcon = 'bi-windows';
            osName = 'Windows';
        } else if (device.isMac) {
            osIcon = 'bi-apple';
            osName = 'Mac';
        } else if (device.isLinux) {
            osIcon = 'bi-ubuntu';
            osName = 'Linux';
        }

        modalContent.innerHTML = `
            <div style="text-align: center; margin-bottom: 20px;">
                <i class="bi ${osIcon}" style="font-size: 3rem; color: #2196f3;"></i>
                <h4 style="margin: 10px 0 5px; color: #1a2332;">تثبيت التطبيق على ${osName}</h4>
                <p style="color: #64748b; font-size: 0.9rem;">اتبع خطوات المتصفح الذي تستخدمه</p>
            </div>
            ${osInstructions}
            <div style="background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); padding: 15px; border-radius: 12px; text-align: center;">
                <i class="bi bi-check-circle-fill" style="font-size: 2rem; color: #4caf50;"></i>
                <p style="margin: 10px 0 0; color: #2e7d32; font-weight: 600;">بعد التثبيت، يمكنك فتح التطبيق مباشرة!</p>
            </div>
        `;
    }

    if (installModal) installModal.style.display = 'flex';
}

// دالة إغلاق المودال
window.closeInstallModal = function () {
    if (installModal) installModal.style.display = 'none';
}

// إغلاق المودال عند النقر خارجه
window.onclick = function (event) {
    if (event.target == installModal) {
        installModal.style.display = 'none';
    }
}

// التحقق من تحديثات Service Worker
function checkForUpdates() {
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.getRegistration().then(registration => {
            if (registration) {
                registration.update();
            }
        });
    }
}

// التحقق من التحديثات كل 5 دقائق
setInterval(checkForUpdates, 5 * 60 * 1000);
