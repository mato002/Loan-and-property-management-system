/**
 * Super Admin GET filter forms: submit on select change; debounced submit on search (name="q").
 * Uses native form.submit() so Turbo Drive does not intercept (full navigation = reliable filters).
 */
function debounce(fn, ms) {
    let t;
    return function debounced(...args) {
        clearTimeout(t);
        t = setTimeout(() => fn.apply(this, args), ms);
    };
}

const debouncedSubmitByForm = new WeakMap();

function submitSaFilterForm(form) {
    if (!(form instanceof HTMLFormElement)) {
        return;
    }
    const method = (form.getAttribute('method') || 'get').toLowerCase();
    if (method !== 'get') {
        return;
    }
    HTMLFormElement.prototype.submit.call(form);
}

function debouncedSubmitForForm(form) {
    let d = debouncedSubmitByForm.get(form);
    if (!d) {
        d = debounce(() => submitSaFilterForm(form), 420);
        debouncedSubmitByForm.set(form, d);
    }
    return d;
}

function isSaAutoFilterForm(form) {
    return (
        form instanceof HTMLFormElement &&
        form.hasAttribute('data-sa-auto-filter') &&
        (form.getAttribute('method') || 'get').toLowerCase() === 'get'
    );
}

document.addEventListener('change', (e) => {
    const target = e.target;
    if (!(target instanceof HTMLElement)) {
        return;
    }
    const form = target.closest('form[data-sa-auto-filter]');
    if (!isSaAutoFilterForm(form)) {
        return;
    }
    if (target instanceof HTMLSelectElement) {
        submitSaFilterForm(form);
        return;
    }
    if (target instanceof HTMLInputElement && target.name === 'q') {
        submitSaFilterForm(form);
    }
});

document.addEventListener('input', (e) => {
    const target = e.target;
    if (!(target instanceof HTMLInputElement) || target.name !== 'q') {
        return;
    }
    const form = target.closest('form[data-sa-auto-filter]');
    if (!isSaAutoFilterForm(form)) {
        return;
    }
    debouncedSubmitForForm(form)();
});
