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
})();
