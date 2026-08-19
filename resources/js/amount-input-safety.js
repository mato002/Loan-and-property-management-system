const AMOUNT_INPUT_SELECTOR = [
    'input[type="number"][name*="amount" i]',
    'input[type="number"][id*="amount" i]',
    'input[type="number"][placeholder*="amount" i]',
].join(', ');

function isAmountNumberInput(target) {
    return target instanceof HTMLInputElement
        && target.type === 'number'
        && target.matches(AMOUNT_INPUT_SELECTOR);
}

function bindAmountInputWheelGuard() {
    document.addEventListener(
        'wheel',
        (event) => {
            if (!isAmountNumberInput(event.target)) {
                return;
            }

            if (document.activeElement !== event.target) {
                return;
            }

            event.preventDefault();
        },
        { passive: false, capture: true },
    );
}

bindAmountInputWheelGuard();
