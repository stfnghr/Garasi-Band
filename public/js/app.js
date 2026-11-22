/* =====================================================================
 * Perilaku global aplikasi (dipakai di semua halaman lewat layouts/app).
 *
 * 1. Pop-up pesan sukses (toast) & pop-up daftar error dari server (SweetAlert2).
 * 2. Validasi sisi klien: field wajib yang kosong ditampilkan dalam pop-up, bukan pesan bawaan browser.
 * 3. Konfirmasi "Apakah Anda yakin?" untuk setiap form ber-atribut data-confirm.
 * 4. Filter input: hanya angka, telepon ber-strip, desimal tanpa "e"/minus, dan format Rupiah.
 *
 * Validasi server (Laravel FormRequest) TETAP berjalan; validasi klien hanya mempercepat umpan balik.
 * ===================================================================== */
(function () {
    'use strict';

    /** Tema tombol SweetAlert2 agar senada dengan Bootstrap. */
    const swalBase = Swal.mixin({
        customClass: { confirmButton: 'btn btn-primary mx-1', cancelButton: 'btn btn-light border mx-1', denyButton: 'btn btn-danger mx-1' },
        buttonsStyling: false,
        reverseButtons: true,
    });

    /** Toast kecil di pojok kanan atas untuk pesan sukses. */
    const toast = Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 3500, timerProgressBar: true });

    /** Ubah teks biasa menjadi aman untuk HTML (cegah XSS saat menampilkan pesan). */
    const escapeHtml = (text) => String(text).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    /** Tampilkan daftar error dalam satu pop-up. */
    function showErrors(messages, title = 'Please check your input') {
        const list = '<ul>' + messages.map((m) => `<li>${escapeHtml(m)}</li>`).join('') + '</ul>';
        return swalBase.fire({ icon: 'error', title, html: list, confirmButtonText: 'Got it' });
    }

    // ------------------------------------------------------------------
    // 1. Pesan dari server (dikirim layout sebagai JSON di #app-flash)
    // ------------------------------------------------------------------
    function showFlash() {
        const node = document.getElementById('app-flash');
        if (!node) return;
        const flash = JSON.parse(node.textContent || '{}');

        // Tandai field yang error dengan garis merah (class Bootstrap .is-invalid).
        Object.keys(flash.fields || {}).forEach((name) => {
            document.querySelectorAll(`[name="${name}"], [name="${name}[]"]`).forEach((el) => el.classList.add('is-invalid'));
        });

        if (flash.errors && flash.errors.length) {
            showErrors(flash.errors);
        } else if (flash.success) {
            toast.fire({ icon: 'success', title: flash.success });
        }
    }

    // ------------------------------------------------------------------
    // 2. Filter input angka
    // ------------------------------------------------------------------
    /** Format angka dengan titik ribuan: 200000 => "200.000". */
    const thousands = (digits) => digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');

    const formatters = {
        // Hanya digit 0-9 (NIK, jumlah garasi). Panjang dibatasi atribut maxlength.
        digits: (v, el) => v.replace(/\D/g, '').slice(0, el.maxLength > 0 ? el.maxLength : undefined),
        // Telepon: hanya digit, maks 15 digit, ditampilkan dengan "-" tiap 4 angka: 0812-3456-7890.
        phone: (v) => v.replace(/\D/g, '').slice(0, 15).replace(/(\d{4})(?=\d)/g, '$1-'),
        // Desimal positif (kWh): digit dan satu titik; koma diubah jadi titik; tanpa "e", "+", "-".
        decimal: (v) => {
            const clean = v.replace(',', '.').replace(/[^\d.]/g, '');
            const [whole, ...rest] = clean.split('.');
            return rest.length ? `${whole}.${rest.join('').slice(0, 2)}` : whole;
        },
        // Rupiah: "Rp 200.000". Server membuang semua karakter non-digit sebelum validasi.
        rupiah: (v) => {
            const digits = v.replace(/\D/g, '').replace(/^0+(?=\d)/, '').slice(0, 10);
            return digits ? 'Rp ' + thousands(digits) : '';
        },
    };

    function applyFormatter(el) {
        const fn = formatters[el.dataset.input];
        if (!fn) return;
        const formatted = fn(el.value, el);
        if (formatted !== el.value) el.value = formatted;
    }

    function bindInputFilters(root = document) {
        root.querySelectorAll('[data-input]').forEach((el) => {
            applyFormatter(el); // rapikan nilai awal (misal old() dari server)
            el.addEventListener('input', () => applyFormatter(el));
            // Cegah tombol yang tidak boleh diketik sama sekali.
            el.addEventListener('keydown', (e) => {
                if (['e', 'E', '+', '-'].includes(e.key) && el.dataset.input !== 'phone') e.preventDefault();
            });
        });
    }

    // ------------------------------------------------------------------
    // 3. Validasi sisi klien (form ber-atribut data-validate)
    // ------------------------------------------------------------------
    /** Ambil nama field yang ramah pengguna dari <label for=...> atau data-label. */
    function labelOf(el) {
        if (el.dataset.label) return el.dataset.label;
        const label = el.id ? document.querySelector(`label[for="${el.id}"]`) : null;
        return label ? label.textContent.replace('*', '').trim() : el.name;
    }

    /** Elemen dianggap terlihat bila tidak berada di dalam elemen tersembunyi. */
    const isVisible = (el) => el.type === 'hidden' ? false : !el.closest('[hidden], .d-none');

    function validateForm(form) {
        const messages = [];
        const checkedRadios = new Set();

        form.querySelectorAll('[required]').forEach((el) => {
            if (el.disabled || !isVisible(el)) return;
            let empty;
            if (el.type === 'radio') {
                if (checkedRadios.has(el.name)) return;
                checkedRadios.add(el.name);
                empty = !form.querySelector(`[name="${el.name}"]:checked`);
            } else if (el.type === 'file') {
                empty = el.files.length === 0;
            } else {
                empty = el.value.trim() === '';
            }
            el.classList.toggle('is-invalid', empty);
            if (empty) messages.push(`${labelOf(el)} is still empty.`);
        });

        // Aturan tambahan opsional per field.
        form.querySelectorAll('[data-exact-digits]').forEach((el) => {
            const n = Number(el.dataset.exactDigits);
            if (isVisible(el) && el.value && el.value.replace(/\D/g, '').length !== n) {
                el.classList.add('is-invalid');
                messages.push(`${labelOf(el)} must be exactly ${n} digits.`);
            }
        });
        form.querySelectorAll('[data-min-digits]').forEach((el) => {
            const n = Number(el.dataset.minDigits);
            if (isVisible(el) && el.value && el.value.replace(/\D/g, '').length < n) {
                el.classList.add('is-invalid');
                messages.push(`${labelOf(el)} must be at least ${n} digits.`);
            }
        });
        form.querySelectorAll('input[type="email"]').forEach((el) => {
            if (el.value && !/^[^@\s]+@[^@\s]+\.[A-Za-z]{2,}$/.test(el.value.trim())) {
                el.classList.add('is-invalid');
                messages.push(`${labelOf(el)} must include a full domain, e.g. name@gmail.com.`);
            }
        });
        form.querySelectorAll('[data-match]').forEach((el) => {
            const other = form.querySelector(el.dataset.match);
            if (other && el.value && el.value !== other.value) {
                el.classList.add('is-invalid');
                messages.push(`${labelOf(el)} does not match ${labelOf(other)}.`);
            }
        });
        form.querySelectorAll('[data-max-of]').forEach((el) => {
            const other = form.querySelector(el.dataset.maxOf);
            if (isVisible(el) && other && el.value !== '' && other.value !== '' && Number(el.value) > Number(other.value)) {
                el.classList.add('is-invalid');
                messages.push(`${labelOf(el)} cannot be greater than ${labelOf(other)}.`);
            }
        });

        // Hook validasi khusus halaman: window.App.validators[nama](form) mengembalikan array pesan.
        const custom = form.dataset.validate && window.App.validators[form.dataset.validate];
        if (custom) messages.push(...custom(form));

        if (messages.length) showErrors(messages, 'Please check your input');
        return messages.length === 0;
    }

    // ------------------------------------------------------------------
    // 4. Konfirmasi "Apakah Anda yakin?" (form ber-atribut data-confirm)
    // ------------------------------------------------------------------
    function confirmSubmit(form) {
        const d = form.dataset;
        // Isi dialog bisa dibangun dinamis oleh halaman: window.App.confirmBuilders[nama](form) => HTML.
        const builder = d.confirmBuilder && window.App.confirmBuilders[d.confirmBuilder];
        const html = builder ? builder(form) : escapeHtml(d.confirmText || 'Please make sure the data is correct.');

        swalBase.fire({
            icon: d.confirmIcon || 'question',
            title: d.confirm || 'Are you sure?',
            html,
            showCancelButton: true,
            confirmButtonText: d.confirmButton || 'Yes, continue',
            cancelButtonText: 'Cancel',
            customClass: {
                confirmButton: `btn ${d.confirmDanger !== undefined ? 'btn-danger' : 'btn-primary'} mx-1`,
                cancelButton: 'btn btn-light border mx-1',
            },
        }).then((result) => {
            if (!result.isConfirmed) return;
            form.dataset.confirmed = '1';
            // requestSubmit() memicu event submit lagi (sudah ditandai confirmed sehingga lolos).
            form.requestSubmit ? form.requestSubmit() : form.submit();
        });
    }

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (form.dataset.confirmed === '1') {
            delete form.dataset.confirmed;
            form.querySelectorAll('button[type="submit"], button:not([type])').forEach((b) => b.disabled = true); // cegah klik ganda
            return;
        }
        if (form.hasAttribute('data-validate') && !validateForm(form)) {
            event.preventDefault();
            return;
        }
        if (form.hasAttribute('data-confirm')) {
            event.preventDefault();
            confirmSubmit(form);
        }
    });

    // Hapus tanda merah begitu pengguna mulai memperbaiki field.
    document.addEventListener('input', (e) => e.target.classList?.remove('is-invalid'));
    document.addEventListener('change', (e) => e.target.classList?.remove('is-invalid'));

    // API kecil untuk halaman lain.
    window.App = { swal: swalBase, toast, showErrors, escapeHtml, thousands, bindInputFilters, validators: {}, confirmBuilders: {} };

    document.addEventListener('DOMContentLoaded', () => {
        bindInputFilters();
        showFlash();
    });
})();
