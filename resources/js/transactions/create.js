let vehiclePhotoPreviewUrls = [];

document.addEventListener('turbo:before-cache', () => {
    vehiclePhotoPreviewUrls.forEach((url) => URL.revokeObjectURL(url));
    vehiclePhotoPreviewUrls = [];
});

document.addEventListener('turbo:load', () => {
    const transactionForm = document.querySelector('.transaction-form');
    if (!transactionForm || transactionForm.classList.contains('sell-module-form')) {
        return;
    }

    const rupiahFormatter = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });
    const sellingPriceInput = document.getElementById('selling-price');
    const downPaymentInput = document.getElementById('down-payment');
    const settlementAmountInput = document.getElementById('settlement-amount');
    const leasingDisbursementInput = document.getElementById('leasing-disbursement');
    const remainingPaymentInput = document.getElementById('remaining-payment');
    const plannedPaymentDateInput = document.getElementById('planned-payment-date');
    const leasingRipanInput = document.getElementById('leasing-ripan');
    const insuranceRipanInput = document.getElementById('insurance-ripan');
    const boosterRipanInput = document.getElementById('booster-ripan');
    const buyPriceInput = document.getElementById('buy-price');
    const purchaseOperationalInput = document.getElementById('purchase-operational');
    const otherCostsInput = document.getElementById('other-costs');
    const calculatedSaleProfitInput = document.getElementById('calculated-sale-profit');
    const calculatedTotalRepairCostInput = document.getElementById('calculated-total-repair-cost');
    const calculatedTotalProfitInput = document.getElementById('calculated-total-profit');
    const calculatedTotalCapitalInput = document.getElementById('calculated-total-capital');
    const setorPriceInput = document.getElementById('setor-price');
    const calculatedSetorProfitInput = document.getElementById('calculated-setor-profit');
    const calculatedSetorProfitPercentageInput = document.getElementById('calculated-setor-profit-percentage');
    const openingPriceInput = document.getElementById('opening-price');
    const calculatedOpeningProfitInput = document.getElementById('calculated-opening-profit');
    const calculatedOpeningProfitPercentageInput = document.getElementById('calculated-opening-profit-percentage');
    const calculatedShowroomFeeInput = document.getElementById('calculated-showroom-fee');
    const calculatedTotalIntertineExpensesInput = document.getElementById('calculated-total-intertine-expenses');
    const calculatedNetProfitInput = document.getElementById('calculated-net-profit');
    const leasingVehicleName = document.getElementById('leasing-vehicle-name');
    const vehiclePhotoInput = document.getElementById('vehicle-photo-input');
    const vehiclePhotoButton = document.getElementById('vehicle-photo-button');
    const vehiclePhotoDropzone = document.getElementById('vehicle-photo-dropzone');
    const vehiclePhotoGallery = document.getElementById('vehicle-photo-gallery');
    const vehiclePhotoPlaceholder = document.getElementById('vehicle-photo-placeholder');
    const vehiclePhotoStatus = document.getElementById('vehicle-photo-status');
    let selectedVehiclePhotos = [];
    const vehicleSummaryFields = [
        'brand',
        'model',
        'year',
        'transmission',
        'color',
        'plate_number',
    ].map((name) => transactionForm.elements.namedItem(name));

    function updateLeasingVehicleSummary() {
        if (!leasingVehicleName) {
            return;
        }

        const vehicleName = vehicleSummaryFields
            .map((field) => field?.value.trim())
            .filter(Boolean)
            .join(' ');

        leasingVehicleName.textContent = vehicleName || 'Lengkapi data kendaraan';
    }

    if (vehiclePhotoInput && vehiclePhotoGallery && vehiclePhotoPlaceholder) {
        function visiblePhotoCards() {
            return [...vehiclePhotoGallery.querySelectorAll('[data-photo-card]')]
                .filter((card) => !card.hidden);
        }

        function updatePhotoPlaceholder() {
            vehiclePhotoPlaceholder.hidden = visiblePhotoCards().length > 0;
        }

        function selectDefaultCover() {
            const checkedCover = transactionForm.querySelector('input[name="foto_utama"]:checked');
            if (checkedCover && !checkedCover.closest('[data-photo-card]')?.hidden) {
                return;
            }

            if (checkedCover) {
                checkedCover.checked = false;
            }

            const firstAvailableCover = visiblePhotoCards().at(0)?.querySelector('input[name="foto_utama"]');
            if (firstAvailableCover) {
                firstAvailableCover.checked = true;
            }
        }

        function clearNewPhotoPreviews() {
            vehiclePhotoGallery.querySelectorAll('[data-new-photo-card]').forEach((card) => card.remove());
            vehiclePhotoPreviewUrls.forEach((url) => URL.revokeObjectURL(url));
            vehiclePhotoPreviewUrls = [];
        }

        function syncPhotoInput() {
            const transfer = new DataTransfer();
            selectedVehiclePhotos.forEach((photo) => transfer.items.add(photo));
            vehiclePhotoInput.files = transfer.files;
        }

        function renderNewPhotoPreviews() {
            clearNewPhotoPreviews();

            selectedVehiclePhotos.forEach((photo, index) => {
                const token = `new:${index}`;
                const card = document.createElement('article');
                const image = document.createElement('img');
                const actions = document.createElement('div');
                const coverLabel = document.createElement('label');
                const coverInput = document.createElement('input');
                const removeButton = document.createElement('button');
                const previewUrl = URL.createObjectURL(photo);
                const alreadyHasCover = transactionForm.querySelector('input[name="foto_utama"]:checked');

                card.className = 'vehicle-photo-item';
                card.dataset.photoCard = '';
                card.dataset.newPhotoCard = '';
                card.dataset.photoToken = token;
                image.src = previewUrl;
                image.alt = `Pratinjau ${photo.name}`;
                actions.className = 'vehicle-photo-item-actions';
                coverLabel.className = 'vehicle-photo-cover-choice';
                coverInput.type = 'radio';
                coverInput.name = 'foto_utama';
                coverInput.value = token;
                coverInput.checked = !alreadyHasCover || Boolean(alreadyHasCover.closest('[data-photo-card]')?.hidden);
                coverLabel.append(coverInput, document.createTextNode(' Foto utama'));
                removeButton.className = 'vehicle-photo-remove';
                removeButton.type = 'button';
                removeButton.dataset.removeNewPhoto = String(index);
                removeButton.textContent = 'Hapus';
                actions.append(coverLabel, removeButton);
                card.append(image, actions);
                vehiclePhotoGallery.append(card);
                vehiclePhotoPreviewUrls.push(previewUrl);
            });

            updatePhotoPlaceholder();
            selectDefaultCover();
        }

        function addVehiclePhotos(files) {
            const supportedPhotos = [...files].filter((photo) =>
                ['image/jpeg', 'image/png', 'image/webp'].includes(photo.type),
            );
            const remainingSlots = 20 - selectedVehiclePhotos.length
                - vehiclePhotoGallery.querySelectorAll(
                    '[data-photo-card]:not([data-new-photo-card]):not([hidden])',
                ).length;
            const photosToAdd = supportedPhotos.slice(0, Math.max(remainingSlots, 0));

            if (photosToAdd.length < supportedPhotos.length && vehiclePhotoStatus) {
                vehiclePhotoStatus.textContent = 'Maksimal 20 foto kendaraan dapat dipilih.';
            } else if (vehiclePhotoStatus) {
                vehiclePhotoStatus.textContent = '';
            }

            selectedVehiclePhotos.push(...photosToAdd);
            syncPhotoInput();
            renderNewPhotoPreviews();
        }

        vehiclePhotoButton?.addEventListener('click', () => vehiclePhotoInput.click());
        vehiclePhotoDropzone?.addEventListener('click', () => vehiclePhotoInput.click());
        vehiclePhotoDropzone?.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                vehiclePhotoInput.click();
            }
        });
        vehiclePhotoDropzone?.addEventListener('dragover', (event) => {
            event.preventDefault();
            vehiclePhotoDropzone.classList.add('is-dragging');
        });
        vehiclePhotoDropzone?.addEventListener('dragleave', () => {
            vehiclePhotoDropzone.classList.remove('is-dragging');
        });
        vehiclePhotoDropzone?.addEventListener('drop', (event) => {
            event.preventDefault();
            vehiclePhotoDropzone.classList.remove('is-dragging');
            addVehiclePhotos(event.dataTransfer?.files ?? []);
        });
        vehiclePhotoInput.addEventListener('change', () => {
            addVehiclePhotos(vehiclePhotoInput.files ?? []);
            vehiclePhotoInput.value = '';
        });

        vehiclePhotoGallery.addEventListener('click', (event) => {
            const removeButton = event.target.closest('[data-remove-photo], [data-remove-new-photo]');
            if (!removeButton) {
                return;
            }

            const card = removeButton.closest('[data-photo-card]');
            const newPhotoIndex = removeButton.dataset.removeNewPhoto;

            if (newPhotoIndex !== undefined) {
                selectedVehiclePhotos.splice(Number(newPhotoIndex), 1);
                syncPhotoInput();
                renderNewPhotoPreviews();
                return;
            }

            card.hidden = true;
            const removalInput = card.querySelector('[data-remove-photo-input], [data-remove-legacy-input]');
            if (removalInput) {
                removalInput.disabled = false;
            }

            const coverInput = card.querySelector('input[name="foto_utama"]');
            if (coverInput) {
                coverInput.checked = false;
            }

            selectDefaultCover();
            updatePhotoPlaceholder();
        });

        updatePhotoPlaceholder();
        selectDefaultCover();
    }

    function toNumericAmount(value) {
        const normalizedValue = value.replace(/\./g, '').replace(',', '.');
        return Number(normalizedValue) || 0;
    }

    function formatRupiahInput(input, preserveCaret = false) {
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

        const formattedInteger = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        input.value = formattedInteger + (decimalIndex === -1 ? '' : `,${decimalPart}`);

        if (preserveCaret && document.activeElement === input) {
            if (hasDecimalBeforeCaret && input.value.includes(',')) {
                input.setSelectionRange(input.value.indexOf(',') + 1 + decimalDigitsBeforeCaret, input.value.indexOf(',') + 1 + decimalDigitsBeforeCaret);
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
    }

    transactionForm.querySelectorAll('[data-rupiah-input]').forEach((input) => {
        if (input.value !== '') {
            input.value = rupiahFormatter.format(Number(input.value) || 0);
        }
    });

    function updateRemainingPayment() {
        const sellingPrice = toNumericAmount(sellingPriceInput.value);
        const downPayment = toNumericAmount(downPaymentInput.value);
        const settlementAmount = toNumericAmount(settlementAmountInput.value);
        const leasingDisbursement = toNumericAmount(leasingDisbursementInput.value);
        const remainingPayment = Math.max(
            sellingPrice - downPayment - settlementAmount - leasingDisbursement,
            0,
        );

        remainingPaymentInput.value = rupiahFormatter.format(remainingPayment);

        if (plannedPaymentDateInput) {
            plannedPaymentDateInput.required = remainingPayment > 0;
        }
    }

    function syncLeasingSellingPrices() {
        transactionForm.querySelectorAll('[data-leasing-selling-price]').forEach((leasingSellingPriceInput) => {
            leasingSellingPriceInput.value = sellingPriceInput.value;
        });
    }

    function updateLeasingDownPayments() {
        transactionForm.querySelectorAll('[data-leasing-selling-price]').forEach((leasingSellingPriceInput) => {
            const leasingRow = leasingSellingPriceInput.closest('tr');
            const disbursementInput = leasingRow?.querySelector('[data-leasing-disbursement]');
            const downPaymentInput = leasingRow?.querySelector('[data-leasing-dp]');

            if (!disbursementInput || !downPaymentInput) {
                return;
            }

            const downPayment = toNumericAmount(leasingSellingPriceInput.value)
                - toNumericAmount(disbursementInput.value);
            downPaymentInput.value = rupiahFormatter.format(Math.max(downPayment, 0));
        });
    }

    function calculateTotalCapital() {
        const variableCapitalInputs = transactionForm.querySelectorAll(
            '[name^="tax_items["][name$="[cost]"], [name^="additional_repairs["][name$="[cost]"]',
        );
        const variableCapital = Array.from(variableCapitalInputs).reduce((total, input) => {
            const amount = input.type === 'hidden'
                ? Number(input.value) || 0
                : toNumericAmount(input.value);
            return total + amount;
        }, 0);
        return toNumericAmount(buyPriceInput.value)
            + toNumericAmount(purchaseOperationalInput.value)
            + toNumericAmount(otherCostsInput.value)
            + variableCapital;
    }

    function updateTotalRepairCost() {
        if (!calculatedTotalRepairCostInput) {
            return;
        }

        const totalRepairCost = Array.from(transactionForm.querySelectorAll(
            '[name^="additional_repairs["][name$="[cost]"]',
        )).reduce((total, input) => total + toNumericAmount(input.value), 0);

        calculatedTotalRepairCostInput.value = rupiahFormatter.format(totalRepairCost);
    }

    function updateProfitCalculation() {
        const totalCapital = calculateTotalCapital();
        const saleProfit = toNumericAmount(sellingPriceInput.value) - totalCapital;
        const totalProfit = saleProfit
            + toNumericAmount(leasingRipanInput.value)
            + toNumericAmount(insuranceRipanInput.value)
            + toNumericAmount(boosterRipanInput.value);
        const setorPrice = toNumericAmount(setorPriceInput.value);
        const setorProfit = setorPrice - totalCapital;
        const setorProfitPercentage = setorPrice === 0 ? 0 : 100 - (totalCapital / setorPrice * 100);
        const openingPrice = toNumericAmount(openingPriceInput.value);
        const openingProfit = openingPrice - totalCapital;
        const openingProfitPercentage = openingPrice === 0 ? 0 : 100 - (totalCapital / openingPrice * 100);
        const showroomFee = Math.round(saleProfit * 0.1 * 100) / 100;
        const intertineExpenses = Array.from(transactionForm.querySelectorAll(
            '[name^="intertine_expenses["][name$="[cost]"]',
        )).reduce((total, input) => total + toNumericAmount(input.value), 0);
        const totalIntertineExpenses = showroomFee + intertineExpenses;
        const netProfit = saleProfit - totalIntertineExpenses;

        if (calculatedSaleProfitInput) {
            calculatedSaleProfitInput.value = rupiahFormatter.format(saleProfit);
        }
        if (calculatedTotalProfitInput) {
            calculatedTotalProfitInput.value = rupiahFormatter.format(totalProfit);
        }
        if (calculatedTotalCapitalInput) {
            calculatedTotalCapitalInput.value = rupiahFormatter.format(totalCapital);
        }
        if (calculatedSetorProfitInput) {
            calculatedSetorProfitInput.value = rupiahFormatter.format(setorProfit);
        }
        if (calculatedSetorProfitPercentageInput) {
            calculatedSetorProfitPercentageInput.value = `${rupiahFormatter.format(setorProfitPercentage)}%`;
        }
        if (calculatedOpeningProfitInput) {
            calculatedOpeningProfitInput.value = rupiahFormatter.format(openingProfit);
        }
        if (calculatedOpeningProfitPercentageInput) {
            calculatedOpeningProfitPercentageInput.value = `${rupiahFormatter.format(openingProfitPercentage)}%`;
        }
        if (calculatedShowroomFeeInput) {
            calculatedShowroomFeeInput.value = rupiahFormatter.format(showroomFee);
        }
        if (calculatedTotalIntertineExpensesInput) {
            calculatedTotalIntertineExpensesInput.value = rupiahFormatter.format(totalIntertineExpenses);
        }
        if (calculatedNetProfitInput) {
            calculatedNetProfitInput.value = rupiahFormatter.format(netProfit);
        }
    }

    sellingPriceInput.addEventListener('input', updateRemainingPayment);
    sellingPriceInput.addEventListener('change', () => {
        formatRupiahInput(sellingPriceInput);
        updateRemainingPayment();
        syncLeasingSellingPrices();
        updateLeasingDownPayments();
    });
    downPaymentInput.addEventListener('input', updateRemainingPayment);
    settlementAmountInput.addEventListener('input', updateRemainingPayment);
    leasingDisbursementInput.addEventListener('input', updateRemainingPayment);
    updateRemainingPayment();
    transactionForm.querySelectorAll('[data-leasing-disbursement], [data-leasing-selling-price]').forEach((input) => {
        input.addEventListener('input', updateLeasingDownPayments);
    });
    syncLeasingSellingPrices();
    updateLeasingDownPayments();
    updateTotalRepairCost();
    updateProfitCalculation();

    transactionForm.addEventListener('input', (event) => {
        if (vehicleSummaryFields.includes(event.target)) {
            updateLeasingVehicleSummary();
        }

        if (event.target.matches('[data-rupiah-input]')) {
            formatRupiahInput(event.target, true);

            if (event.target.matches('#selling-price')) {
                syncLeasingSellingPrices();
                updateLeasingDownPayments();
            } else if (event.target.matches('[data-leasing-disbursement], [data-leasing-selling-price]')) {
                updateLeasingDownPayments();
            }
        }

        if (event.target.matches(
            '#selling-price, #setor-price, #opening-price, #leasing-ripan, #insurance-ripan, #booster-ripan, #buy-price, #purchase-operational, #other-costs, [name^="tax_items["][name$="[cost]"], [name^="additional_repairs["][name$="[cost]"], [name^="intertine_expenses["][name$="[cost]"]',
        )) {
            updateProfitCalculation();
        }

        if (event.target.matches('[name^="additional_repairs["][name$="[cost]"]')) {
            updateTotalRepairCost();
        }
    });

    updateLeasingVehicleSummary();

    transactionForm.addEventListener('submit', () => {
        transactionForm.querySelectorAll('[data-rupiah-input]').forEach((input) => {
            input.value = input.value.replace(/\./g, '').replace(',', '.');
        });
    });

    function updateItemNoteRequirement(item) {
        const status = item.querySelector('.item-payment-status');
        const note = item.querySelector('.item-payment-note input, input.item-payment-note');
        if (status && note) {
            note.required = status.value === 'lainnya';
        }
    }

    function setupItemList(listId, templateId, addButtonId) {
        const itemList = document.getElementById(listId);
        const itemTemplate = document.getElementById(templateId);
        if (!itemList || !itemTemplate) {
            return;
        }

        let nextIndex = Number(itemList.dataset.nextIndex);

        const addItem = () => {
            const item = itemTemplate.innerHTML.replaceAll('__INDEX__', nextIndex++);
            itemList.insertAdjacentHTML('beforeend', item);
            const newItem = itemList.lastElementChild;
            if (newItem) {
                updateRepeatableRequirements(newItem);
            }
            updateRemoveButtons();
        };

        const updateRemoveButtons = () => {
            const isSingleItem = itemList.querySelectorAll('.repeatable-item').length === 1;
            itemList.querySelectorAll('.remove-item').forEach((button) => {
                button.hidden = isSingleItem;
            });
        };

        const addButton = addButtonId ? document.getElementById(addButtonId) : null;
        if (addButton) {
            addButton.addEventListener('click', addItem);
        }

        itemList.querySelectorAll('.repeatable-item').forEach(updateItemNoteRequirement);

        const updateRepeatableRequirements = (item) => {
            if (!item || listId !== 'additional-repairs') {
                return;
            }

            const fields = item.querySelectorAll('input[name], select[name]');
            const isFilled = Array.from(fields).some((field) => field.value !== '');
            fields.forEach((field) => {
                field.required = isFilled;
            });
        };

        itemList.querySelectorAll('.repeatable-item').forEach(updateRepeatableRequirements);
        updateRemoveButtons();
        itemList.addEventListener('input', (event) => {
            updateRepeatableRequirements(event.target.closest('.repeatable-item'));
        });
        itemList.addEventListener('change', (event) => {
            if (event.target.matches('.item-payment-status')) {
                updateItemNoteRequirement(event.target.closest('.repeatable-item'));
            }

            updateRepeatableRequirements(event.target.closest('.repeatable-item'));
        });

        itemList.addEventListener('click', (event) => {
            if (event.target.closest('.remove-item')) {
                const item = event.target.closest('.repeatable-item');
                if (itemList.querySelectorAll('.repeatable-item').length === 1) {
                    item.querySelectorAll('input[name], select[name]').forEach((field) => {
                        field.value = '';
                        field.required = false;
                    });
                } else {
                    item.remove();
                }

                updateRemoveButtons();
                updateTotalRepairCost();
                updateProfitCalculation();
            }
        });
    }

    function setupLeasingList() {
        const leasingList = document.getElementById('leasing-options');
        const leasingTemplate = document.getElementById('leasing-option-template');
        const addButton = document.getElementById('add-leasing-option');

        if (!leasingList || !leasingTemplate || !addButton) {
            return;
        }

        let nextIndex = Number(leasingList.dataset.nextIndex);
        const maxItems = Number(leasingList.dataset.maxItems);

        const updateAddButton = () => {
            addButton.disabled = leasingList.querySelectorAll('.repeatable-item').length >= maxItems;
        };

        addButton.addEventListener('click', () => {
            if (leasingList.querySelectorAll('.repeatable-item').length >= maxItems) {
                return;
            }

            const rowMarkup = leasingTemplate.innerHTML.replaceAll('__INDEX__', nextIndex++);
            leasingList.insertAdjacentHTML('beforeend', rowMarkup);

            const newRow = leasingList.lastElementChild;
            const newSellingPriceInput = newRow?.querySelector('[data-leasing-selling-price]');
            if (newSellingPriceInput && sellingPriceInput) {
                newSellingPriceInput.value = sellingPriceInput.value;
            }

            updateLeasingDownPayments();
            updateAddButton();
        });

        leasingList.addEventListener('click', (event) => {
            const removeButton = event.target.closest('.remove-item');
            if (!removeButton) {
                return;
            }

            removeButton.closest('.repeatable-item')?.remove();
            updateLeasingDownPayments();
            updateAddButton();
        });

        updateAddButton();
    }

    setupItemList('additional-repairs', 'repair-item-template', 'add-repair');
    setupItemList('tax-items', 'tax-item-template', 'add-tax-item');
    setupLeasingList();
});
