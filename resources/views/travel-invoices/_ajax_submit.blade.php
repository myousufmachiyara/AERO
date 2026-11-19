{{--
    Saves the tour/travel invoice form in the background (same behaviour as
    the Ticket Sale Invoice): validation errors are listed at the top and the
    field is outlined — on whichever tab it's on — with no page reload, so
    nothing typed is lost. Falls back to a normal submit without JS.
--}}
<script>
(function () {
    const formEl = document.getElementById('travel-invoice-form');
    const errorBox = document.getElementById('travel-form-errors');
    if (!formEl || !errorBox) return;

    const tkTab = () => window.TourTicketTab || null;

    function clearFieldError(el) {
        if (tkTab()) { tkTab().clearFieldError(el); } else { el.classList.remove('is-invalid'); }
        el.classList.remove('is-invalid');
    }

    function markInvalid(el) {
        if (!el) return;
        el.classList.add('is-invalid');
        const s2 = el.nextElementSibling;
        if (s2 && s2.classList.contains('select2')) {
            const sel = s2.querySelector('.select2-selection');
            if (sel) sel.style.borderColor = '#dc3545';
        }
    }

    // lines.3.detail.check_in → [name="lines[3][detail][check_in]"]
    function fieldForKey(key) {
        const tk = tkTab() && tkTab().fieldForKey(key);
        if (tk) return tk;
        const parts = key.split('.');
        const name = parts[0] + parts.slice(1).map(p => '[' + p + ']').join('');
        return formEl.querySelector('[name="' + name + '"]');
    }

    function showTabOf(el) {
        const pane = el && el.closest('.tab-pane');
        if (!pane || pane.classList.contains('active')) return;
        const btn = document.querySelector('[data-bs-target="#' + pane.id + '"]');
        if (btn) btn.click();
    }

    function showErrors(errors, fallbackMessage) {
        formEl.querySelectorAll('.is-invalid').forEach(clearFieldError);
        const messages = [];
        let firstEl = null;
        Object.entries(errors || {}).forEach(([key, list]) => {
            // A Ticket tab Master Details field fails the same way on every ticket — show it once.
            const shared = tkTab() && tkTab().isSharedKey(key);
            (list || []).forEach(msg => {
                const text = shared ? msg.replace(/^(The )?Ticket row \d+ /i, '$1') : msg;
                if (!messages.includes(text)) messages.push(text);
            });
            const el = fieldForKey(key);
            markInvalid(el);
            if (!firstEl && el) firstEl = el;
        });
        if (!messages.length) messages.push(fallbackMessage || 'Something went wrong. Please try again.');

        errorBox.innerHTML = '<strong>Please fix the following:</strong><ul class="mb-0"></ul>';
        const ul = errorBox.querySelector('ul');
        messages.forEach(m => { const li = document.createElement('li'); li.textContent = m; ul.appendChild(li); });
        errorBox.style.display = '';
        if (firstEl) showTabOf(firstEl);
        errorBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    formEl.setAttribute('novalidate', 'novalidate');
    formEl.addEventListener('input', e => { if (e.target.classList) clearFieldError(e.target); });

    formEl.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (tkTab()) tkTab().beforeSubmit();

        const local = tkTab() ? tkTab().clientErrors() : {};
        const customer = formEl.querySelector('[name="customer_id"]');
        if (customer && !customer.value) local.customer_id = ['Please select a customer.'];
        const date = formEl.querySelector('[name="invoice_date"]');
        if (date && !date.value) local.invoice_date = ['Please enter the invoice date.'];
        if (Object.keys(local).length) { showErrors(local); return; }

        const btn = formEl.querySelector('[type="submit"]');
        const btnHtml = btn ? btn.innerHTML : '';
        if (btn) { btn.disabled = true; btn.innerHTML = 'Saving…'; }
        try {
            const res = await fetch(formEl.action, {
                method: 'POST', // _method=PUT (edit) travels in the form data
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(formEl),
                credentials: 'same-origin',
            });
            let json = {};
            try { json = await res.json(); } catch (_) {}
            if (res.ok && json.redirect) { window.location.href = json.redirect; return; }
            showErrors(json.errors, res.status === 419
                ? 'Your session expired. Please refresh the page and try again (copy anything you typed first).'
                : json.message);
        } catch (err) {
            showErrors({}, 'Could not reach the server: ' + err.message);
        }
        if (btn) { btn.disabled = false; btn.innerHTML = btnHtml; }
    });
})();
</script>
