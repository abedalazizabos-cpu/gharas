/* مركز غراس - تفاعلات الواجهة */
(function () {
    'use strict';

    // القائمة الجانبية في الجوال
    document.addEventListener('click', function (e) {
        if (e.target.closest('[data-sidebar-toggle]')) {
            document.body.classList.toggle('sidebar-open');
        } else if (e.target.closest('.sidebar-backdrop')) {
            document.body.classList.remove('sidebar-open');
        }
    });

    // تأكيد العمليات الحساسة
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (form.dataset.confirm && !form.dataset.confirmed) {
            e.preventDefault();
            Swal.fire({
                title: form.dataset.confirmTitle || 'هل أنت متأكد؟',
                text: form.dataset.confirm,
                icon: form.dataset.confirmIcon || 'warning',
                showCancelButton: true,
                confirmButtonText: form.dataset.confirmButton || 'نعم، متابعة',
                cancelButtonText: 'إلغاء',
                confirmButtonColor: '#1b6b50',
                cancelButtonColor: '#8a948f',
                reverseButtons: true
            }).then(function (result) {
                if (result.isConfirmed) {
                    form.dataset.confirmed = '1';
                    form.requestSubmit ? form.requestSubmit() : form.submit();
                }
            });
            return;
        }
        // حالة التحميل لزر الإرسال
        var btn = form.querySelector('button:not([type=button]):not([data-no-loading]), input[type=submit]');
        if (btn && !form.hasAttribute('data-no-loading') && form.method.toLowerCase() !== 'get') {
            setTimeout(function () {
                btn.disabled = true;
                btn.dataset.original = btn.innerHTML;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm ms-1"></span> جارٍ الحفظ...';
            }, 0);
        }
    });

    // استعادة الأزرار عند الرجوع للصفحة
    window.addEventListener('pageshow', function () {
        document.querySelectorAll('[data-original]').forEach(function (b) {
            b.disabled = false;
            b.innerHTML = b.dataset.original;
        });
    });

    // رسائل النجاح والخطأ
    document.addEventListener('DOMContentLoaded', function () {
        var flash = document.getElementById('flash-data');
        if (flash && window.Swal) {
            var type = flash.dataset.type, msg = flash.dataset.message;
            Swal.fire({
                toast: true, position: 'top-start', icon: type, title: msg,
                showConfirmButton: false, timer: type === 'error' ? 6000 : 3500, timerProgressBar: true
            });
        }

        // تحديد الكل في نموذج الحضور
        document.querySelectorAll('[data-mark-all]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var value = btn.dataset.markAll;
                document.querySelectorAll('input[type=radio][value="' + value + '"]').forEach(function (r) { r.checked = true; });
                updateAttendanceSummary();
            });
        });
        // «تحديد الكل»: checks every box named in data-check-all
        document.querySelectorAll('[data-check-all]').forEach(function (box) {
            box.addEventListener('change', function () {
                document.querySelectorAll(box.dataset.checkAll).forEach(function (c) { c.checked = box.checked; });
            });
        });
        document.querySelectorAll('.att-option input').forEach(function (r) { r.addEventListener('change', updateAttendanceSummary); });
        updateAttendanceSummary();

        // نموذج التسميع: حدود الآيات حسب السورة ومعاينة التقدير
        var surah = document.getElementById('surah_id');
        if (surah) {
            var from = document.getElementById('from_ayah'), to = document.getElementById('to_ayah'), hint = document.getElementById('ayah-hint');
            var sync = function () {
                var opt = surah.options[surah.selectedIndex];
                var max = opt ? parseInt(opt.dataset.ayahs || '0', 10) : 0;
                if (max) {
                    from.max = max; to.max = max;
                    hint.textContent = 'عدد آيات السورة: ' + max;
                }
            };
            surah.addEventListener('change', sync);
            sync();
        }
        // نموذج التسميع: اختيار سريع للمقدار بالصفحات
        document.querySelectorAll('[data-pages]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var pages = document.getElementById('pages');
                pages.value = btn.dataset.pages;
                pages.dispatchEvent(new Event('input'));
            });
        });
        var score = document.getElementById('score');
        if (score) {
            var label = document.getElementById('grade-preview');
            var grade = function () {
                var s = parseInt(score.value, 10);
                if (isNaN(s)) { label.textContent = ''; return; }
                var g = s >= 90 ? ['ممتاز', 'success'] : s >= 80 ? ['جيد جداً', 'primary'] : s >= 70 ? ['جيد', 'info'] : s >= 60 ? ['مقبول', 'warning'] : ['ضعيف', 'danger'];
                label.className = 'badge bg-' + g[1];
                label.textContent = g[0];
            };
            score.addEventListener('input', grade);
            grade();
        }

        // مجموع التقييم
        var scoreInputs = document.querySelectorAll('[data-criterion-max]');
        if (scoreInputs.length) {
            var total = document.getElementById('assessment-total');
            var calc = function () {
                var sum = 0, max = 0;
                scoreInputs.forEach(function (i) { sum += parseInt(i.value || '0', 10); max += parseInt(i.dataset.criterionMax, 10); });
                total.textContent = sum + ' / ' + max + ' (' + (max ? Math.round(sum / max * 1000) / 10 : 0) + '%)';
            };
            scoreInputs.forEach(function (i) { i.addEventListener('input', calc); });
            calc();
        }

        // الرسوم البيانية
        if (window.Chart) {
            Chart.defaults.font.family = "'Cairo', sans-serif";
            Chart.defaults.color = '#6b7a73';
            document.querySelectorAll('canvas[data-chart]').forEach(function (c) {
                var cfg = JSON.parse(c.dataset.chart);
                new Chart(c, cfg);
            });
        }

        // تفعيل التلميحات
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) { new bootstrap.Tooltip(el); });
    });

    function updateAttendanceSummary() {
        var box = document.getElementById('attendance-summary');
        if (!box) return;
        var counts = { present: 0, late: 0, absent: 0, excused: 0 };
        document.querySelectorAll('.att-option input:checked').forEach(function (r) { counts[r.value]++; });
        Object.keys(counts).forEach(function (k) {
            var el = box.querySelector('[data-count="' + k + '"]');
            if (el) el.textContent = counts[k];
        });
    }

    // تذكير دوري بالأدعية والآيات: يُحسب الوقت عبر الصفحات حتى لا يبدأ العدّ من جديد مع كل تنقل
    (function reminders() {
        var node = document.getElementById('reminders-data');
        if (!node) return;
        var cfg;
        try { cfg = JSON.parse(node.textContent); } catch (e) { return; }
        if (!cfg.items || !cfg.items.length) return;
        var every = cfg.interval * 60000, KEY = 'gharas.reminder.last', IDX = 'gharas.reminder.idx';
        function read(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }
        function write(k, v) { try { localStorage.setItem(k, v); } catch (e) {} }
        // First reminder right after signing in (if chosen), otherwise after one full interval.
        if (!read(KEY)) write(KEY, cfg.onLogin ? 0 : Date.now());

        // Only on the chosen days and between the chosen hours (the window may pass midnight).
        function allowedNow() {
            var d = new Date(), hm = ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2);
            if (cfg.days && cfg.days.indexOf(d.getDay()) < 0) return false;
            var from = cfg.from || '00:00', to = cfg.to || '23:59';
            return from <= to ? (hm >= from && hm <= to) : (hm >= from || hm <= to);
        }

        function show() {
            var i = cfg.order === 'random' ? Math.floor(Math.random() * cfg.items.length) : ((parseInt(read(IDX), 10) + 1) || 0);
            i = i % cfg.items.length;
            write(IDX, i);
            var item = cfg.items[i], old = document.querySelector('.reminder-toast');
            if (old) old.remove();
            var box = document.createElement('div');
            box.className = 'reminder-toast' + (cfg.position === 'top' ? ' at-top' : '');
            box.setAttribute('role', 'status');
            box.innerHTML = '<div class="rt-head"><i class="bi bi-stars"></i> <span>تذكير</span><button type="button" class="btn-close" aria-label="إغلاق"></button></div><div class="rt-text"></div><div class="rt-source"></div>';
            box.querySelector('.rt-text').textContent = item.text;
            box.querySelector('.rt-source').textContent = item.source || '';
            document.body.appendChild(box);
            requestAnimationFrame(function () { box.classList.add('show'); });
            var hide = function () { box.classList.remove('show'); setTimeout(function () { box.remove(); }, 400); };
            box.querySelector('.btn-close').addEventListener('click', hide);
            setTimeout(hide, (cfg.duration || 15) * 1000);
        }

        setInterval(function () {
            if (document.hidden || !allowedNow()) return;
            var last = parseInt(read(KEY), 10) || 0;
            if (Date.now() - last >= every) { write(KEY, Date.now()); show(); }
        }, 5000);
    })();

    // ---------------------------------------------------------------- The site as a phone app
    var deferredInstall = null;
    var isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    var isIos = /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

    if ('serviceWorker' in navigator && window.isSecureContext) {
        window.addEventListener('load', function () { navigator.serviceWorker.register('/sw.js').then(refreshAppMenu).catch(function () {}); });
    }
    window.addEventListener('beforeinstallprompt', function (e) { e.preventDefault(); deferredInstall = e; refreshAppMenu(); });
    window.addEventListener('appinstalled', function () { deferredInstall = null; refreshAppMenu(); });

    function urlBase64ToUint8Array(base64) {
        var padding = '='.repeat((4 - base64.length % 4) % 4);
        var raw = atob((base64 + padding).replace(/-/g, '+').replace(/_/g, '/'));
        var out = new Uint8Array(raw.length);
        for (var i = 0; i < raw.length; i++) out[i] = raw.charCodeAt(i);
        return out;
    }
    function post(url, body) {
        return fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }, body: JSON.stringify(body) });
    }
    function pushSupported() {
        var menu = document.getElementById('app-menu');
        return menu && menu.dataset.vapid && 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window && window.isSecureContext;
    }
    function currentSubscription() {
        if (!pushSupported()) return Promise.resolve(null);
        return navigator.serviceWorker.ready.then(function (reg) { return reg.pushManager.getSubscription(); });
    }

    function refreshAppMenu() {
        var menu = document.getElementById('app-menu');
        if (!menu) return;
        var install = document.getElementById('app-install');
        var push = document.getElementById('app-push');
        var showInstall = !isStandalone && (deferredInstall || isIos);
        install.classList.toggle('d-none', !showInstall);
        currentSubscription().then(function (sub) {
            var canPush = pushSupported() && (!isIos || isStandalone);
            push.classList.toggle('d-none', !canPush && !isIos);
            push.dataset.on = sub ? '1' : '';
            push.querySelector('span').textContent = sub ? 'إيقاف الإشعارات على هذا الجهاز' : 'تفعيل الإشعارات على هذا الجهاز';
            menu.classList.toggle('d-none', install.classList.contains('d-none') && push.classList.contains('d-none'));
            var dot = document.getElementById('app-dot');
            if (dot) dot.classList.toggle('d-none', !!sub);
        }).catch(function () {});
    }

    document.addEventListener('click', function (e) {
        if (e.target.closest('#app-install')) {
            e.preventDefault();
            if (deferredInstall) {
                deferredInstall.prompt();
                deferredInstall.userChoice.finally(function () { deferredInstall = null; refreshAppMenu(); });
            } else {
                Swal.fire({ title: 'تثبيت الموقع على الآيفون', icon: 'info', confirmButtonText: 'تم', confirmButtonColor: '#1b6b50',
                    html: '<div style="text-align:right;line-height:2">1. افتح الموقع من متصفح <b>Safari</b>.<br>2. اضغط زر المشاركة <b>⬆︎</b> أسفل الشاشة.<br>3. اختر <b>«إضافة إلى الشاشة الرئيسية»</b> ثم «إضافة».<br>4. افتح التطبيق من أيقونته، ثم فعّل الإشعارات من هذه القائمة.</div>' });
            }
        }
        var pushBtn = e.target.closest('#app-push');
        if (pushBtn) {
            e.preventDefault();
            if (isIos && !isStandalone) {
                Swal.fire({ icon: 'info', title: 'ثبّت التطبيق أولاً', text: 'على الآيفون تصل الإشعارات بعد إضافة الموقع إلى الشاشة الرئيسية وفتحه من أيقونته (iOS 16.4 أو أحدث).', confirmButtonText: 'حسناً', confirmButtonColor: '#1b6b50' });
                return;
            }
            var menu = document.getElementById('app-menu');
            if (pushBtn.dataset.on) {
                currentSubscription().then(function (sub) {
                    if (!sub) return;
                    return post(menu.dataset.unsubscribe, { endpoint: sub.endpoint }).then(function () { return sub.unsubscribe(); });
                }).then(refreshAppMenu);
                return;
            }
            Notification.requestPermission().then(function (perm) {
                if (perm !== 'granted') {
                    Swal.fire({ icon: 'warning', title: 'الإشعارات غير مسموحة', text: 'اسمح للموقع بالإشعارات من إعدادات المتصفح ثم أعد المحاولة.', confirmButtonText: 'حسناً', confirmButtonColor: '#1b6b50' });
                    return;
                }
                return navigator.serviceWorker.ready.then(function (reg) {
                    return reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: urlBase64ToUint8Array(menu.dataset.vapid) });
                }).then(function (sub) {
                    return post(menu.dataset.subscribe, sub.toJSON());
                }).then(function () {
                    Swal.fire({ icon: 'success', title: 'تم تفعيل الإشعارات', text: 'ستصلك إشعارات المركز على هذا الجهاز.', timer: 2500, showConfirmButton: false });
                    refreshAppMenu();
                });
            }).catch(function () {
                Swal.fire({ icon: 'error', title: 'تعذّر التفعيل', text: 'جرّب مرة أخرى، أو من متصفح آخر.', confirmButtonText: 'حسناً', confirmButtonColor: '#1b6b50' });
            });
        }
    });
    document.addEventListener('DOMContentLoaded', refreshAppMenu);
})();
