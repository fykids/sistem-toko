const paymentFields = ['selling_price', 'down_payment', 'settlement_amount', 'leasing_disbursement'];
const paymentDateFields = {
    down_payment: 'down_payment_date',
    settlement_amount: 'settlement_date',
    leasing_disbursement: 'leasing_disbursement_date',
};
const collectionFields = {
    additional_repairs: ['name', 'date', 'cost', 'payment_status', 'payment_note'],
    tax_items: ['name', 'date', 'cost', 'payment_status', 'payment_note'],
    intertine_expenses: ['name', 'cost'],
};
const rupiahFormatter = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });
const currencyFormatter = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});

const parseAmount = (value) => {
    const normalizedValue = String(value ?? '').replace(/\./g, '').replace(',', '.');
    return Number(normalizedValue) || 0;
};

const formatAmount = (value) => rupiahFormatter.format(Number(value) || 0);

const formatCurrencyInput = (input, preserveCaret = false) => {
    const originalValue = input.value;
    const selectionStart = input.selectionStart ?? originalValue.length;
    const textBeforeCaret = originalValue.slice(0, selectionStart);
    const digitsBeforeCaret = (textBeforeCaret.match(/\d/g) ?? []).length;
    const hasDecimalBeforeCaret = textBeforeCaret.includes(',');
    const decimalDigitsBeforeCaret = hasDecimalBeforeCaret
        ? (textBeforeCaret.slice(textBeforeCaret.indexOf(',') + 1).match(/\d/g) ?? []).length
        : 0;
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
        if (hasDecimalBeforeCaret && input.value.includes(',')) {
            const caretPosition = input.value.indexOf(',') + 1 + decimalDigitsBeforeCaret;
            input.setSelectionRange(caretPosition, caretPosition);
        } else {
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
    }
};

document.querySelectorAll('.sell-module-form').forEach((form) => {
    const vehicleSelect = form.querySelector('[data-jual-vehicle-select]');
    const currentVehicleInput = form.querySelector('[data-jual-current-vehicle]');
    const preview = form.querySelector('[data-jual-vehicle-preview]');
    const sellingPrice = form.querySelector('[data-jual-selling-price]');
    const paymentType = form.querySelector('[data-jual-payment-type]');
    const paymentAmount = form.querySelector('[data-jual-payment-amount]');
    const paymentDate = form.querySelector('[data-jual-payment-date]');
    const remainingPayment = form.querySelector('[data-jual-remaining-payment]');
    const leasingRows = form.querySelector('[data-jual-leasing-rows]');
    let paymentValues = {};
    let paymentDateValues = {};
    let preserveInitialAmount = paymentAmount?.dataset.preserveValue === 'true';
    let preserveInitialSellingPrice = sellingPrice?.dataset.preserveValue === 'true';
    let preserveInitialPaymentDate = paymentDate?.dataset.preserveValue === 'true';

    const selectedVehicle = () => {
        const json = vehicleSelect?.selectedOptions[0]?.dataset.vehiclePreview
            ?? currentVehicleInput?.value;

        return json ? JSON.parse(json) : null;
    };

    const updateRemainingPayment = () => {
        if (!remainingPayment) {
            return;
        }

        const vehicle = selectedVehicle();
        const currentSellingPrice = sellingPrice
            ? parseAmount(sellingPrice.value)
            : Number(paymentValues.selling_price ?? vehicle?.selling_price ?? 0);
        const paidAmount = paymentFields
            .filter((field) => field !== 'selling_price')
            .reduce((total, field) => total + Number(paymentValues[field] ?? vehicle?.[field] ?? 0), 0);

        remainingPayment.textContent = currencyFormatter.format(Math.max(currentSellingPrice - paidAmount, 0));
    };

    const updateLeasingPreview = (vehicle = selectedVehicle()) => {
        if (!leasingRows) {
            return;
        }

        const options = Array.isArray(vehicle?.leasing_options)
            ? vehicle.leasing_options.filter((option) => option && typeof option === 'object')
            : [];
        const sellingPriceValue = sellingPrice ? parseAmount(sellingPrice.value) : 0;

        if (options.length === 0) {
            const emptyRow = document.createElement('tr');
            const emptyCell = document.createElement('td');
            emptyCell.colSpan = 7;
            emptyCell.textContent = vehicle
                ? 'Belum ada informasi leasing untuk kendaraan ini.'
                : 'Pilih mobil untuk melihat informasi leasing.';
            emptyRow.append(emptyCell);
            leasingRows.replaceChildren(emptyRow);
            return;
        }

        const rows = options.map((option) => {
            const row = document.createElement('tr');
            const nameCell = document.createElement('th');
            const companyName = document.createElement('span');
            nameCell.scope = 'row';
            nameCell.className = 'td-leasing-label';
            companyName.className = 'leasing-company-name';
            companyName.textContent = option.name ?? '-';
            nameCell.append(companyName);
            row.append(nameCell);

            const disbursement = Number(option.disbursement) || 0;
            const values = [
                ['disbursement', option.disbursement],
                ['selling_price', sellingPriceValue],
                ['dp', Math.max(sellingPriceValue - disbursement, 0)],
                ['installment_3yr', option.installment_3yr],
                ['installment_4yr', option.installment_4yr],
                ['installment_5yr', option.installment_5yr],
            ];

            values.forEach(([field, value]) => {
                const cell = document.createElement('td');
                const labels = {
                    disbursement: 'Nilai pencairan (Rp)',
                    selling_price: 'Harga jual (Rp)',
                    dp: 'DP (Rp)',
                    installment_3yr: 'Angsuran 3 tahun (Rp/bulan)',
                    installment_4yr: 'Angsuran 4 tahun (Rp/bulan)',
                    installment_5yr: 'Angsuran 5 tahun (Rp/bulan)',
                };
                cell.dataset.label = labels[field];
                if (field === 'selling_price' || field === 'dp') {
                    cell.dataset.leasingValue = field;
                }
                cell.textContent = value === null || value === undefined || value === ''
                    ? '-'
                    : formatAmount(value);
                row.append(cell);
            });

            return row;
        });

        leasingRows.replaceChildren(...rows);
    };

    const updatePaymentAmount = () => {
        if (!paymentType || !paymentAmount) {
            return;
        }

        const field = paymentType.value;
        if (preserveInitialAmount) {
            preserveInitialAmount = false;
        } else {
            paymentAmount.value = formatAmount(paymentValues[field] ?? selectedVehicle()?.[field] ?? 0);
        }
        if (paymentDate) {
            if (preserveInitialPaymentDate) {
                preserveInitialPaymentDate = false;
            } else {
                paymentDate.value = paymentDateValues[field] ?? '';
            }
        }
        updateRemainingPayment();
    };

    const updatePreview = () => {
        const vehicle = selectedVehicle();
        const emptyHint = preview?.querySelector('[data-jual-preview-empty]');
        const details = preview?.querySelector('.field-grid.two-column');

        if (emptyHint) {
            emptyHint.hidden = Boolean(vehicle);
        }
        if (details) {
            details.hidden = !vehicle;
        }

        paymentValues = Object.fromEntries(paymentFields.map((field) => [
            field,
            Number(vehicle?.[field] ?? 0),
        ]));
        paymentDateValues = Object.fromEntries(Object.entries(paymentDateFields).map(([field, dateField]) => [
            field,
            vehicle?.[dateField] ?? '',
        ]));
        updateLeasingPreview(vehicle);
        if (sellingPrice) {
            if (preserveInitialSellingPrice) {
                paymentValues.selling_price = parseAmount(sellingPrice.value);
            } else {
                sellingPrice.value = formatAmount(vehicle?.selling_price ?? 0);
            }
        }
        if (preserveInitialAmount && paymentType && paymentAmount) {
            paymentValues[paymentType.value] = parseAmount(paymentAmount.value);
        }

        preview?.querySelectorAll('[data-jual-collection]').forEach((list) => {
            const items = vehicle?.[list.dataset.jualCollection];
            const fields = collectionFields[list.dataset.jualCollection] ?? [];
            const rows = Array.isArray(items) ? items.filter((item) => item && typeof item === 'object') : [];
            const listItems = rows.map((item) => {
                const values = fields
                    .map((field) => field === 'cost' && item[field] !== undefined && item[field] !== null
                        ? currencyFormatter.format(Number(item[field]) || 0)
                        : item[field])
                    .filter((value) => value !== undefined && value !== null && value !== '');
                const row = document.createElement('li');
                row.textContent = values.join(' · ') || 'Tidak ada rincian.';
                return row;
            });

            if (listItems.length === 0) {
                const emptyRow = document.createElement('li');
                emptyRow.textContent = 'Tidak ada rincian.';
                listItems.push(emptyRow);
            }

            list.replaceChildren(...listItems);
            list.closest('.form-group').hidden = !vehicle;
        });

        preview?.querySelectorAll('[data-jual-field]').forEach((field) => {
            const value = vehicle?.[field.dataset.jualField];
            const format = field.dataset.format;

            field.textContent = !vehicle || value === null || value === undefined || value === ''
                ? '—'
                : format === 'currency'
                    ? currencyFormatter.format(Number(value) || 0)
                    : format === 'number'
                        ? rupiahFormatter.format(Number(value) || 0)
                        : value;
        });

        updatePaymentAmount();
    };

    paymentAmount?.addEventListener('input', () => {
        if (paymentType) {
            paymentValues[paymentType.value] = parseAmount(paymentAmount.value);
        }
        preserveInitialAmount = false;
        updateRemainingPayment();
    });

    paymentDate?.addEventListener('input', () => {
        if (paymentType) {
            paymentDateValues[paymentType.value] = paymentDate.value;
        }
    });

    sellingPrice?.addEventListener('input', () => {
        preserveInitialSellingPrice = false;
        paymentValues.selling_price = parseAmount(sellingPrice.value);
        updateRemainingPayment();
        updateLeasingPreview();
    });

    paymentType?.addEventListener('change', updatePaymentAmount);
    vehicleSelect?.addEventListener('change', () => {
        preserveInitialAmount = false;
        preserveInitialSellingPrice = false;
        updatePreview();
    });

    form.querySelectorAll('[data-rupiah-input]').forEach((input) => {
        if (input.value !== '') {
            input.value = formatAmount(parseAmount(input.value));
        }

        input.addEventListener('input', () => formatCurrencyInput(input, true));
    });

    updatePreview();

    form.addEventListener('submit', () => {
        form.querySelectorAll('[data-rupiah-input]').forEach((input) => {
            input.value = input.value.replace(/\./g, '').replace(',', '.');
        });
    });
});
