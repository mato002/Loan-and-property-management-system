/**
 * Mark every data-entry form field as required (*) or optional so users can tell them apart.
 * Driven by the HTML required / aria-required attributes on the control.
 */

const CONTROL_SELECTOR = [
    'input:not([type="hidden"]):not([type="submit"]):not([type="button"]):not([type="reset"]):not([type="image"]):not([type="search"])',
    'select',
    'textarea',
].join(', ');

const SKIP_CLOSEST = [
    '.property-filter-toolbar',
    '.property-filter-field',
    '[data-skip-field-hints]',
    '[data-property-auto-filter]',
    'form[role="search"]',
].join(', ');

function isRequiredControl(control) {
    return control.hasAttribute('required') || control.getAttribute('aria-required') === 'true';
}

function shouldSkipControl(control) {
    if (!(control instanceof HTMLElement)) {
        return true;
    }
    if (control.disabled) {
        return true;
    }
    if (control.closest(SKIP_CLOSEST)) {
        return true;
    }
    if (control.getAttribute('data-skip-field-hint') === '1') {
        return true;
    }
    if (control.type === 'checkbox' && control.name === 'remember') {
        return true;
    }

    return false;
}

function findLabelForControl(control) {
    const id = control.getAttribute('id');
    if (id) {
        try {
            const labeled = document.querySelector(`label[for="${CSS.escape(id)}"]`);
            if (labeled) {
                return labeled;
            }
        } catch {
            // ignore invalid id
        }
    }

    const wrapping = control.closest('label');
    if (wrapping) {
        return wrapping;
    }

    let node = control.parentElement;
    for (let depth = 0; node && depth < 8; depth += 1) {
        const directLabels = [...node.children].filter((el) => el.tagName === 'LABEL');
        if (directLabels.length === 1) {
            return directLabels[0];
        }
        if (directLabels.length > 1) {
            let sibling = control.previousElementSibling;
            while (sibling) {
                if (sibling.tagName === 'LABEL') {
                    return sibling;
                }
                sibling = sibling.previousElementSibling;
            }
        }
        node = node.parentElement;
    }

    return null;
}

function labelAlreadyMarked(label) {
    if (label.querySelector('.property-field-required, .property-field-optional')) {
        return true;
    }

    const text = (label.textContent || '').replace(/\s+/g, ' ').trim().toLowerCase();
    if (text.includes('(optional') || /\boptional\b/.test(text)) {
        return true;
    }
    if (label.querySelector('.text-red-600, .text-red-500, .text-rose-600, .text-rose-500')) {
        return true;
    }

    return false;
}

function insertHint(label, hint) {
    const controlInLabel = label.querySelector('input, select, textarea');
    if (controlInLabel) {
        controlInLabel.before(hint);
        return;
    }
    label.append(' ', hint);
}

function markLabel(label, required) {
    if (!(label instanceof HTMLElement) || labelAlreadyMarked(label)) {
        return;
    }

    if (required) {
        const star = document.createElement('span');
        star.className = 'property-field-required';
        star.setAttribute('title', 'Required');
        star.setAttribute('aria-hidden', 'true');
        star.textContent = '*';

        const sr = document.createElement('span');
        sr.className = 'sr-only';
        sr.textContent = 'required';

        insertHint(label, star);
        star.after(sr);
        return;
    }

    const optional = document.createElement('span');
    optional.className = 'property-field-optional';
    optional.textContent = '(optional)';
    insertHint(label, optional);
}

export function markFormFieldRequirements(root = document) {
    if (!(root instanceof Document) && !(root instanceof Element)) {
        return;
    }

    const scope = root instanceof Document ? root : root;
    const seen = new WeakSet();

    scope.querySelectorAll(CONTROL_SELECTOR).forEach((control) => {
        if (shouldSkipControl(control)) {
            return;
        }

        const label = findLabelForControl(control);
        if (!(label instanceof HTMLElement) || seen.has(label)) {
            return;
        }
        seen.add(label);

        const groupName = control.getAttribute('name');
        const type = (control.getAttribute('type') || '').toLowerCase();
        let required = isRequiredControl(control);

        if (groupName && (type === 'radio' || type === 'checkbox')) {
            const form = control.form || control.closest('form');
            const peers = form
                ? form.querySelectorAll(`[name="${CSS.escape(groupName)}"]`)
                : [control];
            required = [...peers].some((peer) => isRequiredControl(peer));
        }

        markLabel(label, required);
    });
}

let scheduled = null;

function scheduleMark(root = document) {
    if (scheduled) {
        return;
    }
    scheduled = window.requestAnimationFrame(() => {
        scheduled = null;
        markFormFieldRequirements(root);
    });
}

document.addEventListener('turbo:load', () => markFormFieldRequirements(document));
document.addEventListener('turbo:frame-load', (event) => {
    const target = event.target;
    if (target instanceof Element) {
        markFormFieldRequirements(target);
    }
});
document.addEventListener('DOMContentLoaded', () => markFormFieldRequirements(document));

if (document.readyState !== 'loading') {
    markFormFieldRequirements(document);
}

if (typeof MutationObserver !== 'undefined') {
    const observer = new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            if (mutation.type === 'attributes' || (mutation.addedNodes && mutation.addedNodes.length > 0)) {
                scheduleMark(document);
                return;
            }
        }
    });
    const start = () => {
        if (document.body) {
            observer.observe(document.body, {
                childList: true,
                subtree: true,
                attributes: true,
                attributeFilter: ['required', 'aria-required', 'disabled'],
            });
        }
    };
    if (document.body) {
        start();
    } else {
        document.addEventListener('DOMContentLoaded', start, { once: true });
    }
}
