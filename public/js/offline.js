/* مركز غراس: the teacher's daily forms work without internet.
 *
 * How it fits together (explained step by step in docs/OFFLINE.md):
 *  1. public/sw.js keeps a copy of every teacher page opened while online, so it opens offline too.
 *  2. Here: a form in the list below that is sent while offline is kept on the phone, in the
 *     "outbox" (IndexedDB), instead of going to the site. sw.js does the same when the internet
 *     drops in the middle of sending.
 *  3. When the internet is back (or a page is opened, or every minute) the outbox is sent to the
 *     form's normal address with an X-Offline-Id header. The site (App\Http\Middleware\OfflineSync)
 *     runs the normal controller, answers in JSON, and never saves the same id twice.
 */
(function () {
    'use strict';

    // Keep the same list in public/sw.js.
    var FORMS = [
        [/^\/teacher\/circles\/\d+\/attendance$/, 'حضور الحلقة'],
        [/^\/teacher\/memorization$/, 'تسميع'],
        [/^\/teacher\/circles\/\d+\/day-off$/, 'لا تحفيظ'],
        [/^\/teacher\/assessments$/, 'تقييم'],
        [/^\/teacher\/sunnah\/\d+\/attendance$/, 'حضور لقاء'],
        [/^\/teacher\/sunnah\/\d+\/posts\/\d+\/grades$/, 'درجات واجب'],
        [/^\/teacher\/sunnah\/\d+\/grade$/, 'رصد درجات دورة'],
        [/^\/teacher\/quality\/\d+\/grade$/, 'نتيجة اختبار'],
    ];
    // After keeping one of these on the phone the form is emptied for the next student.
    var RESET_AFTER = [/^\/teacher\/memorization$/, /^\/teacher\/assessments$/];

    var USER = meta('gharas-user');
    if (!USER || !('indexedDB' in window)) return;
    var PAGE_DATE = meta('gharas-date');
    var state = { loginNeeded: false, staleFrom: null, open: false };

    // ------------------------------------------------------------ outbox (IndexedDB)

    var dbPromise = null;
    function db() {
        if (!dbPromise) {
            dbPromise = new Promise(function (resolve, reject) {
                var open = indexedDB.open('gharas-offline', 1);
                open.onupgradeneeded = function () { open.result.createObjectStore('outbox', { keyPath: 'id' }); };
                open.onsuccess = function () { resolve(open.result); };
                open.onerror = function () { reject(open.error); };
            });
        }
        return dbPromise;
    }
    function store(mode, work) {
        return db().then(function (d) {
            return new Promise(function (resolve, reject) {
                var tx = d.transaction('outbox', mode);
                var result = work(tx.objectStore('outbox'));
                tx.oncomplete = function () { resolve(result && 'result' in result ? result.result : undefined); };
                tx.onerror = function () { reject(tx.error); };
            });
        });
    }
    var outbox = {
        mine: function () {
            return store('readonly', function (s) { return s.getAll(); }).then(function (all) {
                return (all || []).filter(function (i) { return String(i.user) === String(USER); })
                    .sort(function (a, b) { return a.savedAt < b.savedAt ? -1 : 1; });
            });
        },
        put: function (item) { return store('readwrite', function (s) { s.put(item); }); },
        remove: function (id) { return store('readwrite', function (s) { s.delete(id); }); },
    };

    // ------------------------------------------------------------ keeping a form sent offline

    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (e.defaultPrevented || !kind(form)) return;

        // Read by sw.js if the internet drops while this form is on its way.
        hidden(form, '_offline_user', USER);
        hidden(form, '_offline_label', describe(form));
        if (navigator.onLine) return;

        e.preventDefault();
        if (!form.checkValidity()) { form.reportValidity(); return; }

        var item = {
            id: uuid(), user: USER, url: pathOf(form), fields: fields(form, e.submitter),
            label: describe(form), savedAt: new Date().toISOString(), status: 'pending', shown: true,
        };
        outbox.put(item).then(function () {
            // Ask the browser never to clear this site's data on its own (iPhone clears it after 7 idle days otherwise).
            if (navigator.storage && navigator.storage.persist) navigator.storage.persist().catch(function () {});
            toast('success', 'حُفظ على الجهاز، وسيُرفع وحده عند عودة الإنترنت');
            if (RESET_AFTER.some(function (r) { return r.test(item.url); })) {
                form.reset();
                form.querySelectorAll('select, input').forEach(function (el) { el.dispatchEvent(new Event('change', { bubbles: true })); });
            }
            render();
        }).catch(function () {
            toast('error', 'تعذّر الحفظ على الجهاز. جرّب مرة أخرى.');
        });
    });

    function kind(form) {
        if (!form || (form.getAttribute('method') || '').toLowerCase() !== 'post' || form.querySelector('[name=_method]')) return null;
        var path = pathOf(form);
        for (var i = 0; i < FORMS.length; i++) if (FORMS[i][0].test(path)) return FORMS[i][1];
        return null;
    }
    function pathOf(form) { return new URL(form.getAttribute('action') || location.href, location.href).pathname; }

    function fields(form, submitter) {
        var data;
        try { data = new FormData(form, submitter || undefined); } catch (err) { data = new FormData(form); }
        var out = [];
        data.forEach(function (value, name) {
            if (typeof value === 'string' && name !== '_token' && name.indexOf('_offline_') !== 0) out.push([name, value]);
        });
        return out;
    }

    /** A short line the teacher recognises in the waiting list: what, who, which day. */
    function describe(form) {
        var parts = [kind(form)];
        var heading = document.querySelector('.page-header h1');
        var student = selected(form, 'student_id');
        if (student) {
            parts.push(student);
        } else if (heading) { // e.g. "تسجيل الحضور: حلقة سعيد بن جبير" → the circle or course
            var h = heading.textContent.trim(), after = h.split(':')[1];
            if (after) parts.push(after.trim()); else if (!/تسجيل|تقييم/.test(h)) parts.push(h);
        }
        var surah = selected(form, 'surah_id');
        if (surah) {
            var from = form.querySelector('[name=from_ayah]'), to = form.querySelector('[name=to_ayah]');
            parts.push(surah + (from && to && from.value ? ' ' + from.value + '-' + to.value : ''));
        }
        var day = form.querySelector('[name=date], [name=recorded_on], [name=assessed_on], [name=tested_on]');
        if (day && day.value) parts.push(day.value);
        return parts.filter(Boolean).join(' · ').slice(0, 160);
    }
    function selected(form, name) {
        var el = form.querySelector('select[name=' + name + ']');
        return el && el.value && el.selectedIndex >= 0 ? el.options[el.selectedIndex].text.trim() : '';
    }
    function hidden(form, name, value) {
        var el = form.querySelector('input[name=' + name + ']');
        if (!el) { el = document.createElement('input'); el.type = 'hidden'; el.name = name; form.appendChild(el); }
        el.value = value;
    }

    // ------------------------------------------------------------ sending the outbox

    var flushing = false;
    function flush() {
        if (flushing || !navigator.onLine) return Promise.resolve();
        var run = function () {
            flushing = true;
            return outbox.mine().then(function (items) {
                var pending = items.filter(function (i) { return i.status === 'pending'; });
                if (!pending.length) return 0;
                return token().then(function (t) {
                    if (!t) return 0;
                    return sendAll(pending, t);
                });
            }).then(function (sent) {
                if (sent) toast('success', sent === 1 ? 'رُفع تسجيل محفوظ إلى الموقع' : 'رُفعت ' + sent + ' تسجيلات محفوظة إلى الموقع');
            }).catch(function () {}).then(function () { flushing = false; render(); });
        };
        // Only one open tab sends at a time.
        if (navigator.locks && navigator.locks.request) {
            return navigator.locks.request('gharas-outbox', { ifAvailable: true }, function (lock) { return lock ? run() : null; });
        }
        return run();
    }

    function token() {
        return fetch('/offline/token', { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (t) {
                state.loginNeeded = !t.user || String(t.user) !== String(USER);
                return state.loginNeeded ? null : t.token;
            })
            .catch(function () { return null; });
    }

    function sendAll(items, tok) {
        var sent = 0, i = 0, retried = false;
        function next() {
            if (i >= items.length) return Promise.resolve(sent);
            var item = items[i];
            return send(item, tok).then(function (res) {
                if (!res || res.status >= 500) return sent;                      // no internet again, or the site is busy: later
                if (res.status === 401) { state.loginNeeded = true; return sent; } // session ended: after the next login
                if (res.status === 419 && !retried) {                             // old security token: take a new one, once
                    retried = true;
                    return token().then(function (t) { if (!t) return sent; tok = t; return next(); });
                }
                if (res.ok) {
                    sent++; i++;
                    return outbox.remove(item.id).then(next);
                }
                // Refused by the site (e.g. a mistake in the form): kept, with the reason, for the teacher to see.
                return res.json().catch(function () { return {}; }).then(function (body) {
                    item.status = 'failed';
                    item.error = reason(body, res.status);
                    i++;
                    return outbox.put(item).then(next);
                });
            });
        }
        return next();
    }

    function send(item, tok) {
        var body = new FormData();
        item.fields.forEach(function (f) { body.append(f[0], f[1]); });
        body.append('_token', tok);
        return fetch(item.url, {
            method: 'POST', body: body, credentials: 'same-origin', redirect: 'manual',
            headers: { 'X-Offline-Id': item.id, 'X-CSRF-TOKEN': tok, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        }).catch(function () { return null; });
    }

    function reason(body, status) {
        if (body && body.errors) {
            var first = Object.keys(body.errors)[0];
            if (first) return body.errors[first][0];
        }
        if (body && body.message) return body.message;
        return status === 403 ? 'ليست لديك صلاحية لهذا التسجيل.' : status === 404 ? 'لم يعد هذا موجوداً في الموقع.' : 'رفض الموقع هذا التسجيل.';
    }

    // ------------------------------------------------------------ the bar above the page

    function render() {
        return outbox.mine().then(function (items) {
            var pending = items.filter(function (i) { return i.status === 'pending'; });
            var failed = items.filter(function (i) { return i.status === 'failed'; });
            var teacherPage = location.pathname.indexOf('/teacher') === 0;
            var bar = document.getElementById('offline-bar');
            if (!bar) {
                var main = document.querySelector('main.content');
                if (!main) return;
                bar = document.createElement('div');
                bar.id = 'offline-bar';
                bar.className = 'no-print';
                main.insertBefore(bar, main.firstChild);
            }
            var html = '';
            if (!navigator.onLine && (teacherPage || items.length)) {
                html += box('secondary', 'wifi-off', '<strong>أنت الآن دون إنترنت.</strong> ما تحفظه من الحضور والتسميع والتقييم يبقى على هذا الجهاز ويُرفع وحده عند عودة الاتصال.');
            }
            if (state.staleFrom) {
                html += box('warning', 'clock-history', 'هذه نسخة محفوظة من يوم ' + esc(state.staleFrom) + '، وغيّرنا التاريخ في النموذج إلى اليوم. راجع الاختيارات قبل الحفظ.');
            }
            if (state.loginNeeded && (pending.length || failed.length)) {
                html += box('danger', 'box-arrow-in-left', 'انتهت جلستك، والتسجيلات المحفوظة على الجهاز تنتظر. <a href="/login" class="alert-link">ادخل مرة أخرى</a> لترفع وحدها.');
            }
            if (pending.length) {
                html += box('info', 'cloud-upload', '<strong>' + pending.length + (pending.length === 1 ? ' تسجيل بانتظار الرفع' : ' تسجيلات بانتظار الرفع') + '</strong>'
                    + ' <button type="button" class="btn btn-sm btn-link p-0 ms-2" data-offline-toggle>' + (state.open ? 'إخفاء' : 'عرض') + '</button>'
                    + (navigator.onLine ? ' <button type="button" class="btn btn-sm btn-info ms-2" data-offline-flush>ارفع الآن</button>' : '')
                    + (state.open ? list(pending, false) : ''));
            }
            if (failed.length) {
                html += box('danger', 'exclamation-triangle', '<strong>' + (failed.length === 1 ? 'تسجيل رفضه الموقع' : failed.length + ' تسجيلات رفضها الموقع') + '</strong>: راجع السبب، ثم أعد المحاولة أو احذفه وسجّله من جديد.' + list(failed, true));
            }
            bar.innerHTML = html;
        }).catch(function () {});
    }
    function box(type, icon, inner) {
        return '<div class="alert alert-' + type + ' d-flex gap-2 align-items-start py-2 mb-2"><i class="bi bi-' + icon + ' fs-5"></i><div class="flex-grow-1">' + inner + '</div></div>';
    }
    function list(items, withActions) {
        return '<ul class="mb-0 mt-2 ps-0 pe-3 small">' + items.map(function (i) {
            var when = new Date(i.savedAt);
            return '<li class="mb-1">' + esc(i.label || 'تسجيل') + ' <span class="text-muted">(حُفظ ' + esc(when.toLocaleString('ar', { dateStyle: 'short', timeStyle: 'short' })) + ')</span>'
                + (i.error ? '<div class="text-danger">السبب: ' + esc(i.error) + '</div>' : '')
                + (withActions ? '<div class="mt-1"><button type="button" class="btn btn-sm btn-outline-primary py-0" data-offline-retry="' + esc(i.id) + '">إعادة المحاولة</button> '
                    + '<button type="button" class="btn btn-sm btn-outline-danger py-0" data-offline-delete="' + esc(i.id) + '">حذف</button></div>' : '')
                + '</li>';
        }).join('') + '</ul>';
    }

    document.addEventListener('click', function (e) {
        var t = e.target.closest('[data-offline-toggle], [data-offline-flush], [data-offline-retry], [data-offline-delete]');
        if (!t) return;
        if (t.hasAttribute('data-offline-toggle')) { state.open = !state.open; render(); }
        if (t.hasAttribute('data-offline-flush')) { flush(); }
        if (t.dataset.offlineRetry) {
            byId(t.dataset.offlineRetry).then(function (item) {
                if (!item) return;
                item.status = 'pending'; delete item.error;
                return outbox.put(item).then(function () { render(); flush(); });
            });
        }
        if (t.dataset.offlineDelete) {
            var id = t.dataset.offlineDelete;
            var go = function () { outbox.remove(id).then(render); };
            if (window.Swal) {
                Swal.fire({ title: 'حذف هذا التسجيل؟', text: 'سيُحذف من هذا الجهاز ولن يُرفع إلى الموقع.', icon: 'warning', showCancelButton: true,
                    confirmButtonText: 'نعم، احذفه', cancelButtonText: 'إلغاء', confirmButtonColor: '#b85042', reverseButtons: true })
                    .then(function (r) { if (r.isConfirmed) go(); });
            } else { go(); }
        }
    });
    function byId(id) { return outbox.mine().then(function (all) { return all.filter(function (i) { return i.id === id; })[0]; }); }

    // ------------------------------------------------------------ a kept page opened on a later day

    /** A kept copy of yesterday's page still says "yesterday" in its date fields; today's work must be dated today. */
    function freshenDates() {
        var today = localDate();
        if (!PAGE_DATE || PAGE_DATE >= today || location.pathname.indexOf('/teacher') !== 0) return;
        if (new URLSearchParams(location.search).has('date')) return; // a day the teacher chose on purpose
        var changed = false;
        document.querySelectorAll('form').forEach(function (form) {
            form.querySelectorAll('input[type=date], input[type=hidden][name=date]').forEach(function (el) {
                if (el.getAttribute('max') === PAGE_DATE) el.setAttribute('max', today);
                if (el.value === PAGE_DATE) { el.value = today; el.setAttribute('value', today); changed = true; }
            });
        });
        if (changed) state.staleFrom = PAGE_DATE;
    }

    // ------------------------------------------------------------ start

    document.addEventListener('DOMContentLoaded', function () {
        freshenDates();
        // Forms kept by sw.js while the page was changing: tell the teacher once.
        outbox.mine().then(function (items) {
            var unseen = items.filter(function (i) { return !i.shown; });
            if (unseen.length) {
                toast('info', 'انقطع الاتصال أثناء الحفظ، فحُفظ على الجهاز وسيُرفع وحده عند عودة الإنترنت');
                return Promise.all(unseen.map(function (i) { i.shown = true; return outbox.put(i); }));
            }
        }).catch(function () {}).then(function () { render(); flush(); });
    });
    window.addEventListener('online', function () { render(); flush(); });
    window.addEventListener('offline', render);
    document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'visible') flush(); });
    setInterval(function () { if (navigator.onLine) flush(); }, 60000);

    // Logging out: the next person on this phone must not see this teacher's kept pages.
    // (Forms still waiting stay, and are sent the next time this teacher logs in.)
    document.addEventListener('submit', function (e) {
        if (pathOf(e.target) !== '/logout' || !window.caches) return;
        caches.keys().then(function (keys) {
            keys.filter(function (k) { return k.indexOf('gharas-pages') === 0; }).forEach(function (k) { caches.delete(k); });
        });
    });

    // ------------------------------------------------------------ small helpers

    function meta(name) { var m = document.querySelector('meta[name="' + name + '"]'); return m ? m.content : null; }
    function localDate() { var d = new Date(); return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
    function pad(n) { return (n < 10 ? '0' : '') + n; }
    function esc(s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    function uuid() {
        if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
        var b = crypto.getRandomValues(new Uint8Array(16));
        b[6] = (b[6] & 15) | 64; b[8] = (b[8] & 63) | 128;
        var h = Array.prototype.map.call(b, function (x) { return (x + 256).toString(16).slice(1); }).join('');
        return h.slice(0, 8) + '-' + h.slice(8, 12) + '-' + h.slice(12, 16) + '-' + h.slice(16, 20) + '-' + h.slice(20);
    }
    function toast(icon, title) {
        if (!window.Swal) return;
        Swal.fire({ toast: true, position: 'top-start', icon: icon, title: title, showConfirmButton: false, timer: 4000, timerProgressBar: true });
    }
})();
