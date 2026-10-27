const paymentDateFields = {
    down_payment: 'down_payment_date',
    settlement_amount: 'settlement_date',
    leasing_disbursement: 'leasing_disbursement_date',
};
const rupiahFormatter = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });
const currencyFormatter = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});

const parseAmount = (value) => {
    const text = String(value ?? '').trim();

    if (text.includes(',')) {
        return Number(text.replace(/\./g, '').replace(',', '.')) || 0;
    }

    if (/^\d{1,3}(?:\.\d{3})+$/.test(text)) {
        return Number(text.replace(/\./g, '')) || 0;
    }

    return Number(text) || 0;
};

const formatAmount = (value) => rupiahFormatter.format(Number(value) || 0);

const formatCurrencyInput = (input, preserveCaret = false) => {
    const originalValue = input.value;
    const selectionStart = input.selectionStart ?? originalValue.length;
    const digitsBeforeCaret = (originalValue.slice(0, selectionStart).match(/\d/g) ?? []).length;
    const normalizedValue = originalValue.replace(/[^\d,]/g, '');
    const decimalIndex = normalizedValue.indexOf(',');
    let integerPart = decimalIndex === -1
        ? normalizedValue
        : normalizedValue.slice(0, decimalIndex);
    const decimalPart = decimalIndex === -1
        ? ''
        : normalizedValue.slice(decimalIndex + 1).replace(/,/g, '');

    if (decimalIndex !== -1 && integerPart === '') {
        integerPart = '0';
    }

    input.value = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.')
        + (decimalIndex === -1 ? '' : `,${decimalPart}`);

    if (preserveCaret && document.activeElement === input) {
        let caretPosition = 0;
        let digitsSeen = 0;

        while (caretPosition < input.value.length && digitsSeen < digitsBeforeCaret) {
            if (/\d/.test(input.value[caretPosition])) {
                digitsSeen++;
            }

            caretPosition++;
        }

        input.setSelectionRange(caretPosition, caretPosition);
    }
};

document.querySelectorAll('.sell-module-form').forEach((form) => {
    const vehicleSelect = form.querySelector('[data-jual-vehicle-select]');
    const currentVehicleInput = form.querySelector('[data-jual-current-vehicle]');
    const vehicleFields = form.querySelector('[data-jual-vehicle-fields]');
    const sellingPrice = form.querySelector('[data-jual-selling-price]');
    const paymentInputs = [...form.querySelectorAll('[data-jual-payment-amount]')];
    const remainingPayment = form.querySelector('[data-jual-remaining-payment]');
    const plannedPaymentDate = form.querySelector('[data-jual-planned-payment-date]');

    const selectedVehicle = () => {
        const json = vehicleSelect?.selectedOptions[0]?.dataset.vehiclePreview
            ?? currentVehicleInput?.value;

        return json ? JSON.parse(json) : null;
    };

    const updateRemainingPayment = () => {
        if (!remainingPayment || !sellingPrice) {
            return;
        }

        const paidAmount = paymentInputs.reduce((total, input) => total + parseAmount(input.value), 0);
        const balance = Math.max(parseAmount(sellingPrice.value) - paidAmount, 0);

        remainingPayment.textContent = currencyFormatter.format(balance);

        if (plannedPaymentDate) {
            plannedPaymentDate.required = balance > 0;
        }
    };

    const updateVehicleFields = (vehicle) => {
        vehicleFields?.querySelectorAll('[data-jual-vehicle-field]').forEach((field) => {
            const value = vehicle?.[field.dataset.jualVehicleField];
            field.value = value === null || value === undefined ? '' : String(value);
        });
    };

    const updatePaymentValues = (vehicle) => {
        if (sellingPrice) {
            sellingPrice.value = formatAmount(vehicle?.selling_price ?? 0);
        }

        paymentInputs.forEach((input) => {
            input.value = formatAmount(vehicle?.[input.name] ?? 0);
        });

        Object.values(paymentDateFields).forEach((dateField) => {
            const input = form.elements.namedItem(dateField);

            if (input instanceof HTMLInputElement) {
                input.value = vehicle?.[dateField] ?? '';
            }
        });

        const plannedDate = form.elements.namedItem('planned_payment_date');
        if (plannedDate instanceof HTMLInputElement) {
            plannedDate.value = vehicle?.planned_payment_date ?? '';
        }

        updateRemainingPayment();
    };

    form.querySelectorAll('[data-rupiah-input]').forEach((input) => {
        input.value = formatAmount(parseAmount(input.value));
        input.addEventListener('input', () => formatCurrencyInput(input, true));
    });

    sellingPrice?.addEventListener('input', updateRemainingPayment);
    paymentInputs.forEach((input) => input.addEventListener('input', updateRemainingPayment));

    vehicleSelect?.addEventListener('change', () => {
        const vehicle = selectedVehicle();
        updateVehicleFields(vehicle);
        updatePaymentValues(vehicle);
    });

    updateVehicleFields(selectedVehicle());
    updateRemainingPayment();

    form.addEventListener('submit', () => {
        form.querySelectorAll('[data-rupiah-input]').forEach((input) => {
            input.value = input.value.replace(/\./g, '').replace(',', '.');
        });
    });
});
