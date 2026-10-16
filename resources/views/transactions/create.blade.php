@extends('layout')

@section('content')
@php($transaction = $transaction ?? null)
@php($isEditing = $transaction !== null)
<div class="page-heading">
    <div>
        <p class="page-eyebrow">Pencatatan kendaraan</p>
        <h1>{{ $isEditing ? 'Edit Transaksi' : 'Transaksi Baru' }}</h1>
    </div>
    <a href="{{ route('transactions.index') }}" class="btn btn-secondary">← Kembali ke transaksi</a>
</div>
<form action="{{ $isEditing ? route('transactions.update', $transaction) : route('transactions.store') }}" method="POST"
    class="transaction-form">
    @csrf
    @if ($isEditing)
        @method('PUT')
    @endif
    @if ($errors->any())
        <div class="error-panel" role="alert">
            <strong>Periksa kembali data berikut:</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    <section class="form-section vehicle-section">
        <header class="section-heading"><span class="section-number">01</span>
            <div>
                <h2>Data kendaraan</h2>
                <p>Identitas utama dan dokumen kendaraan</p>
            </div>
        </header>
        <div class="section-content field-grid vehicle-fields">
            <div class="form-group"><label for="plate-number">Plat nomor <span aria-hidden="true">*</span></label><input
                    id="plate-number" type="text" name="plate_number"
                    value="{{ old('plate_number', $transaction?->plate_number) }}" required autocomplete="off"></div>
            <div class="form-group"><label for="brand">Merek <span aria-hidden="true">*</span></label><input id="brand"
                    type="text" name="brand" value="{{ old('brand', $transaction?->brand ?? 'TOYOTA') }}" required>
            </div>
            <div class="form-group"><label for="model">Model <span aria-hidden="true">*</span></label><input id="model"
                    type="text" name="model" value="{{ old('model', $transaction?->model ?? 'INOVA DIESEL G') }}"
                    required></div>
            <div class="form-group"><label for="year">Tahun <span aria-hidden="true">*</span></label><input id="year"
                    type="number" name="year" value="{{ old('year', $transaction?->year ?? 2014) }}" required></div>
            <div class="form-group"><label for="transmission">Transmisi <span aria-hidden="true">*</span></label><input
                    id="transmission" type="text" name="transmission"
                    value="{{ old('transmission', $transaction?->transmission ?? 'MANUAL') }}" required></div>
            <div class="form-group"><label for="color">Warna <span aria-hidden="true">*</span></label><input id="color"
                    type="text" name="color" value="{{ old('color', $transaction?->color ?? 'HITAM') }}" required></div>
            <div class="form-group"><label for="chassis-number">Nomor rangka</label><input id="chassis-number"
                    type="text" name="chassis_number" value="{{ old('chassis_number', $transaction?->chassis_number) }}"
                    maxlength="255"></div>
            <div class="form-group"><label for="engine-number">Nomor mesin</label><input id="engine-number" type="text"
                    name="engine_number" value="{{ old('engine_number', $transaction?->engine_number) }}"
                    maxlength="255"></div>
            <div class="form-group"><label for="mileage-km">Kilometer (Km)</label><input id="mileage-km" type="number"
                    name="mileage_km" value="{{ old('mileage_km', $transaction?->mileage_km) }}" min="0" step="1"></div>
            <div class="form-group"><label for="owner-name">Atas nama STNK</label><input id="owner-name" type="text"
                    name="owner_name" value="{{ old('owner_name', $transaction?->owner_name) }}"></div>
            <div class="form-group"><label for="manager-name">Penanggung jawab</label><input id="manager-name"
                    type="text" name="manager_name" value="{{ old('manager_name', $transaction?->manager_name) }}">
            </div>
        </div>
    </section>

    <section class="form-section marketing-section">
        <header class="section-heading"><span class="section-number">03</span>
            <div>
                <h2>Strategi pemasaran</h2>
                <p>Atur harga setor dan harga buka kendaraan</p>
            </div>
        </header>
        <div class="section-content marketing-fields">
            <div class="form-group"><label for="calculated-total-capital">Total pengeluaran
                </label><input type="text" id="calculated-total-capital" value="0" readonly
                    aria-label="Total pengeluaran modal kendaraan (Rupiah)"></div>
            <div class="form-group setor-price-group">
                <label for="setor-price">Harga setor (Rp)</label>
                <div class="setor-price-inputs">
                    <input type="text" name="setor_price" id="setor-price" aria-label="Harga setor (Rupiah)"
                        value="{{ old('setor_price', $transaction?->setor_price ?? 0) }}" inputmode="decimal"
                        data-rupiah-input>
                    <input type="text" id="calculated-setor-profit" value="0" readonly
                        aria-label="Selisih harga setor dengan modal (Rupiah)">
                    <input type="text" id="calculated-setor-profit-percentage" value="0%" readonly
                        aria-label="Persentase selisih harga setor dari modal">
                </div>
            </div>
            <div class="form-group opening-price-group">
                <label for="opening-price">Harga buka (Rp)</label>
                <div class="opening-price-inputs">
                    <input type="text" name="opening_price" id="opening-price" aria-label="Harga buka (Rupiah)"
                        value="{{ old('opening_price', $transaction?->opening_price ?? 0) }}" inputmode="decimal"
                        data-rupiah-input>
                    <input type="text" id="calculated-opening-profit" value="0" readonly
                        aria-label="Selisih harga buka dengan modal (Rupiah)">
                    <input type="text" id="calculated-opening-profit-percentage" value="0%" readonly
                        aria-label="Persentase selisih harga buka dari modal">
                </div>
            </div>
            <div class="form-group selling-price-group">
                <label for="selling-price">Harga jual (Rp)</label>
                <div class="selling-price-inputs">
                    <input type="text" name="selling_price" id="selling-price"
                        value="{{ old('selling_price', $transaction?->selling_price ?? 0) }}" inputmode="decimal"
                        data-rupiah-input aria-label="Harga jual (Rupiah)">
                    <input type="text" id="calculated-sale-profit" value="0" inputmode="decimal" data-rupiah-input
                        readonly aria-label="Laba jual (Rupiah)">
                    <input id="sell-date" type="date" name="sell_date"
                        value="{{ old('sell_date', $transaction?->sell_date) }}" aria-label="Tanggal jual">
                </div>
            </div>
            <div class="sales-fields-row">
                <div class="form-group"><label for="down-payment">DP (Rp)</label><input type="text" name="down_payment"
                        id="down-payment" value="{{ old('down_payment', $transaction?->down_payment ?? 0) }}"
                        inputmode="decimal" data-rupiah-input></div>
                <div class="form-group"><label for="settlement-amount">Pelunasan (Rp)</label><input type="text"
                        name="settlement_amount" id="settlement-amount"
                        value="{{ old('settlement_amount', $transaction?->settlement_amount ?? 0) }}"
                        inputmode="decimal" data-rupiah-input></div>
                <div class="form-group"><label for="leasing-disbursement">Pencairan leasing (Rp)</label><input
                        type="text" name="leasing_disbursement" id="leasing-disbursement"
                        value="{{ old('leasing_disbursement', $transaction?->leasing_disbursement ?? 0) }}"
                        inputmode="decimal" data-rupiah-input></div>
                <div class="form-group"><label for="remaining-payment">Sisa pembayaran (Rp)</label><input type="text"
                        name="remaining_payment" id="remaining-payment"
                        value="{{ max((float) old('selling_price', $transaction?->selling_price ?? 0) - (float) old('down_payment', $transaction?->down_payment ?? 0) - (float) old('settlement_amount', $transaction?->settlement_amount ?? 0) - (float) old('leasing_disbursement', $transaction?->leasing_disbursement ?? 0), 0) }}"
                        inputmode="decimal" data-rupiah-input readonly><span class="form-help">Harga jual dikurangi DP,
                        pelunasan, dan pencairan leasing.</span></div>
            </div>
        </div>
    </section>

    <section class="form-section sales-section">
        <header class="section-heading"><span class="section-number">04</span>
            <div>
                <h2>Penjualan dan pembayaran</h2>
                <p>Kelola ripan, pengeluaran intertine, serta perhitungan laba transaksi</p>
            </div>
        </header>
        <div class="section-content field-grid two-column">
            <div class="sales-fields">
                <div class="form-group"><label for="leasing-ripan">Ripan leasing (Rp)</label><input type="text"
                        name="leasing_ripan" id="leasing-ripan" aria-label="Ripan leasing (Rupiah)"
                        value="{{ old('leasing_ripan', $transaction?->leasing_ripan ?? 0) }}" inputmode="decimal"
                        data-rupiah-input></div>
                <div class="form-group"><label for="insurance-ripan">Ripan asuransi (Rp)</label><input type="text"
                        name="insurance_ripan" id="insurance-ripan" aria-label="Ripan asuransi (Rupiah)"
                        value="{{ old('insurance_ripan', $transaction?->insurance_ripan ?? 0) }}" inputmode="decimal"
                        data-rupiah-input></div>
                <div class="form-group"><label for="booster-ripan">Ripan booster (Rp)</label><input type="text"
                        name="booster_ripan" id="booster-ripan" aria-label="Ripan booster (Rupiah)"
                        value="{{ old('booster_ripan', $transaction?->booster_ripan ?? 0) }}" inputmode="decimal"
                        data-rupiah-input></div>
            </div>
            <div class="form-group"><label for="calculated-total-profit">Total laba</label><input type="text"
                    id="calculated-total-profit" value="0" inputmode="decimal" data-rupiah-input readonly
                    aria-label="Total laba (Rupiah)"></div>
            @php($expenseInput = old('intertine_expenses', $transaction?->intertine_expenses ?? []))
            @php($expenseItems = collect(is_array($expenseInput) ? $expenseInput : [])->filter(fn($expense) => is_array($expense))->values())
            @php($intertineCategories = ['KR Jual', 'OP Survei', 'Fee CMO'])
            @php($intertineCategoryCosts = $expenseItems->filter(fn(array $expense) => in_array(strtolower(trim($expense['name'] ?? '')), array_map('strtolower', $intertineCategories), true))->mapWithKeys(fn(array $expense) => [strtolower(trim($expense['name'])) => $expense['cost'] ?? 0]))
            @php($legacyIntertineExpenses = $expenseItems->reject(fn(array $expense) => in_array(strtolower(trim($expense['name'] ?? '')), array_map('strtolower', $intertineCategories), true))->values())
            <div class="form-group"><label for="calculated-showroom-fee">Fee Showroom (Rp)</label><input type="text"
                    id="calculated-showroom-fee" value="0" readonly aria-label="Fee Showroom (Rupiah)"></div>
            <div id="intertine-expenses" class="intertine-expense-fields">
                @foreach ($intertineCategories as $index => $category)
                @php($categoryKey = strtolower($category))
                <div class="form-group intertine-expense-item">
                    <input type="hidden" name="intertine_expenses[{{ $index }}][name]" value="{{ $category }}">
                    <label for="intertine-expense-cost-{{ $index }}">{{ $category }}</label>
                    <input id="intertine-expense-cost-{{ $index }}" type="text"
                        name="intertine_expenses[{{ $index }}][cost]"
                        value="{{ old("intertine_expenses.{$index}.cost", $intertineCategoryCosts->get($categoryKey, 0)) }}"
                        inputmode="decimal" data-rupiah-input>
                </div>
                @endforeach
                @foreach ($legacyIntertineExpenses as $index => $expense)
                    <input type="hidden" name="intertine_expenses[{{ $index + count($intertineCategories) }}][name]"
                        value="{{ $expense['name'] ?? '' }}">
                    <input type="hidden" name="intertine_expenses[{{ $index + count($intertineCategories) }}][cost]"
                        value="{{ $expense['cost'] ?? 0 }}" data-rupiah-input>
                @endforeach
            </div>
            <div class="form-group"><label for="calculated-total-intertine-expenses">Total Pengeluaran Intertine
                    (Rp)</label><input type="text" id="calculated-total-intertine-expenses" value="0" readonly
                    aria-label="Total Pengeluaran Intertine (Rupiah)"></div>
            <div class="form-group"><label for="calculated-net-profit">Laba Bersih (Rp)</label><input type="text"
                    id="calculated-net-profit" value="0" readonly aria-label="Laba Bersih (Rupiah)"></div>
        </div>
    </section>

    <section class="form-section costs-section">
        <header class="section-heading"><span class="section-number">02</span>
            <div>
                <h2>Modal dan biaya kendaraan</h2>
                <p>Isi biaya pembelian, pajak, perbaikan, dan pengeluaran intertine</p>
            </div>
        </header>
        <div class="section-content">
            <div class="field-grid two-column">
                <div class="form-group"><label for="buy-price">Modal beli (Rp) <span
                            aria-hidden="true">*</span></label><input id="buy-price" type="text" name="buy_price"
                        value="{{ old('buy_price', $transaction?->buy_price) }}" inputmode="decimal" data-rupiah-input
                        required></div>
                <div class="form-group"><input id="buy-date" type="date" name="buy_date"
                        value="{{ old('buy_date', $transaction?->buy_date) }}" required></div>
                <div class="form-group"><label for="purchase-operational">OPS (Rp)</label><input
                        id="purchase-operational" type="text" name="purchase_operational"
                        value="{{ old('purchase_operational', $transaction?->purchase_operational ?? 0) }}"
                        inputmode="decimal" data-rupiah-input></div>
            </div>
            <div class="field-grid two-column">
                <div class="form-group"><label for="other-costs">KR Beli (Rp)</label><input id="other-costs" type="text"
                        name="other_costs" value="{{ old('other_costs', $transaction?->other_costs ?? 0) }}"
                        inputmode="decimal" data-rupiah-input></div>
            </div>
            @php($taxInput = old('tax_items', $transaction?->tax_items ?? []))
            @php($taxItemsForForm = collect(is_array($taxInput) ? $taxInput : [])->filter(fn($taxItem) => is_array($taxItem))->values())
            @php($taxItemsByName = $taxItemsForForm->keyBy(fn($taxItem) => strtolower($taxItem['name'] ?? '')))
            @php($primaryTaxItem = $taxItemsByName->get('pajak') ?? $taxItemsForForm->first())
            @php($otherTaxItems = $taxItemsForForm->reject(fn($taxItem) => $taxItem === $primaryTaxItem || in_array(strtolower($taxItem['name'] ?? ''), ['pajak', 'bbn', 'etle'], true))->values())
            <div class="field-grid two-column">
                <input type="hidden" name="tax_items[0][name]"
                    value="{{ old('tax_items.0.name', data_get($primaryTaxItem, 'name', 'Pajak')) }}">
                <input type="hidden" name="tax_items[0][payment_status]"
                    value="{{ old('tax_items.0.payment_status', data_get($primaryTaxItem, 'payment_status')) }}">
                <input type="hidden" name="tax_items[0][payment_note]"
                    value="{{ old('tax_items.0.payment_note', data_get($primaryTaxItem, 'payment_note')) }}">
                <div class="form-group"><label for="tax-price">Biaya Pajak <span
                            aria-hidden="true">*</span></label><input id="tax-price" type="text"
                        name="tax_items[0][cost]"
                        value="{{ old('tax_items.0.cost', data_get($primaryTaxItem, 'cost')) }}" inputmode="decimal"
                        data-rupiah-input required></div>
                <div class="form-group"><input id="tax-date" type="date" name="tax_items[0][date]"
                        value="{{ old('tax_items.0.date', data_get($primaryTaxItem, 'date')) }}"></div>
                <input type="hidden" name="tax_items[1][name]" value="BBN">
                <input type="hidden" name="tax_items[1][payment_status]"
                    value="{{ old('tax_items.1.payment_status', data_get($taxItemsByName->get('bbn'), 'payment_status')) }}">
                <input type="hidden" name="tax_items[1][payment_note]"
                    value="{{ old('tax_items.1.payment_note', data_get($taxItemsByName->get('bbn'), 'payment_note')) }}">
                <div class="form-group"><label for="bbn-price">Biaya BBN</label><input id="bbn-price" type="text"
                        name="tax_items[1][cost]"
                        value="{{ old('tax_items.1.cost', data_get($taxItemsByName->get('bbn'), 'cost')) }}"
                        inputmode="decimal" data-rupiah-input></div>
                <div class="form-group"><input id="bbn-date" type="date" name="tax_items[1][date]"
                        value="{{ old('tax_items.1.date', data_get($taxItemsByName->get('bbn'), 'date')) }}"></div>
                <input type="hidden" name="tax_items[2][name]" value="ETLE">
                <input type="hidden" name="tax_items[2][payment_status]"
                    value="{{ old('tax_items.2.payment_status', data_get($taxItemsByName->get('etle'), 'payment_status')) }}">
                <input type="hidden" name="tax_items[2][payment_note]"
                    value="{{ old('tax_items.2.payment_note', data_get($taxItemsByName->get('etle'), 'payment_note')) }}">
                <div class="form-group"><label for="etle-price">Biaya ETLE</label><input id="etle-price" type="text"
                        name="tax_items[2][cost]"
                        value="{{ old('tax_items.2.cost', data_get($taxItemsByName->get('etle'), 'cost')) }}"
                        inputmode="decimal" data-rupiah-input></div>
                @foreach ($otherTaxItems as $index => $taxItem)
                @php($taxItemIndex = $index + 3)
                <input type="hidden" name="tax_items[{{ $taxItemIndex }}][name]" value="{{ $taxItem['name'] ?? '' }}">
                <input type="hidden" name="tax_items[{{ $taxItemIndex }}][date]"
                    value="{{ old("tax_items.{$taxItemIndex}.date", $taxItem['date'] ?? '') }}">
                <input type="hidden" name="tax_items[{{ $taxItemIndex }}][cost]"
                    value="{{ old("tax_items.{$taxItemIndex}.cost", $taxItem['cost'] ?? '') }}">
                <input type="hidden" name="tax_items[{{ $taxItemIndex }}][payment_status]"
                    value="{{ old("tax_items.{$taxItemIndex}.payment_status", $taxItem['payment_status'] ?? '') }}">
                <input type="hidden" name="tax_items[{{ $taxItemIndex }}][payment_note]"
                    value="{{ old("tax_items.{$taxItemIndex}.payment_note", $taxItem['payment_note'] ?? '') }}">
                @endforeach
            </div>
            @php($repairInput = old('additional_repairs', $transaction?->additional_repairs ?? []))
            @php($repairItems = collect(is_array($repairInput) ? $repairInput : [])->filter(fn($repair) => is_array($repair))->all())
            @if (empty($repairItems))
            @php($repairItems = [['name' => '', 'date' => '', 'cost' => '', 'payment_status' => '']])
            @endif
            <div id="additional-repairs" class="repair-form-list form-tabel"
                data-next-index="{{ (collect($repairItems)->keys()->max() ?? -1) + 1 }}">
                <div class="repair-list-actions">
                    <span>Daftar biaya perbaikan</span>
                    <button id="add-repair" type="button" class="btn btn-secondary btn-small repair-add-button">
                        <span aria-hidden="true">+</span>
                        Tambah perbaikan
                    </button>
                </div>
                <div class="repair-form-labels" aria-hidden="true">
                    <span>Nama Perbaikan</span>
                    <span>Biaya (Rp)</span>
                    <span>Tgl</span>
                    <span>Status</span>
                    <span class="repair-label-actions"></span>
                </div>
                @foreach ($repairItems as $index => $repair)
                @php($hasRepairData = collect($repair)->contains(fn($value) => filled($value)))
                <div class="repair-form-row repeatable-item">
                    <div class="form-group"><label class="sr-only" for="repair-name-{{ $index }}">Nama
                            Perbaikan</label><input id="repair-name-{{ $index }}" type="text"
                            name="additional_repairs[{{ $index }}][name]" value="{{ $repair['name'] ?? '' }}" @if ($hasRepairData) required @endif maxlength="100"></div>
                    <div class="form-group"><label class="sr-only" for="repair-cost-{{ $index }}">Biaya perbaikan dalam
                            rupiah</label><input id="repair-cost-{{ $index }}" class="text-right" type="text"
                            name="additional_repairs[{{ $index }}][cost]" value="{{ $repair['cost'] ?? '' }}"
                            inputmode="decimal" data-rupiah-input @if ($hasRepairData) required @endif></div>
                    <div class="form-group"><label class="sr-only" for="repair-date-{{ $index }}">Tanggal
                            perbaikan</label><input id="repair-date-{{ $index }}" type="date"
                            name="additional_repairs[{{ $index }}][date]" value="{{ $repair['date'] ?? '' }}" @if ($hasRepairData) required @endif></div>
                    <div class="form-group"><label class="sr-only" for="repair-status-{{ $index }}">Status pembayaran
                            perbaikan</label><select id="repair-status-{{ $index }}"
                            name="additional_repairs[{{ $index }}][payment_status]" class="item-payment-status" @if ($hasRepairData) required @endif>
                            <option value="">Pilih status</option>
                            <option value="lunas" @selected(($repair['payment_status'] ?? '') === 'lunas')>Lunas</option>
                            <option value="belum_lunas" @selected(($repair['payment_status'] ?? '') === 'belum_lunas')>
                                Belum lunas</option>
                            <option value="panjar" @selected(($repair['payment_status'] ?? '') === 'panjar')>Panjar
                            </option>
                            <option value="lainnya" @selected(($repair['payment_status'] ?? '') === 'lainnya')>Lainnya
                            </option>
                        </select></div>
                        <div class="repair-row-actions">
                            <button type="button" class="icon-button remove-item" aria-label="Hapus biaya perbaikan"
                                title="Hapus biaya perbaikan" @if (count($repairItems) === 1) hidden @endif>
                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 6h18M8 6V4h8v2m3 0-.9 14H5.9L5 6m4 4v6m6-6v6" />
                            </svg>
                        </button>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </section>

    <div class="form-actions"><a href="{{ route('transactions.index') }}" class="btn btn-secondary">Batal</a><button
            type="submit" class="btn btn-accent">{{ $isEditing ? 'Simpan perubahan' : 'Simpan transaksi' }}</button>
    </div>
</form>

<template id="repair-item-template">
    <div class="repair-form-row repeatable-item">
        <div class="form-group"><label class="sr-only" for="repair-name-__INDEX__">Nama Perbaikan</label><input
                id="repair-name-__INDEX__" type="text" name="additional_repairs[__INDEX__][name]" required
                maxlength="100"></div>
        <div class="form-group"><label class="sr-only" for="repair-cost-__INDEX__">Biaya perbaikan dalam
                rupiah</label><input id="repair-cost-__INDEX__" class="text-right" type="text"
                name="additional_repairs[__INDEX__][cost]" inputmode="decimal" data-rupiah-input required></div>
        <div class="form-group"><label class="sr-only" for="repair-date-__INDEX__">Tanggal perbaikan</label><input
                id="repair-date-__INDEX__" type="date" name="additional_repairs[__INDEX__][date]" required></div>
        <div class="form-group"><label class="sr-only" for="repair-status-__INDEX__">Status pembayaran
                perbaikan</label><select id="repair-status-__INDEX__"
                name="additional_repairs[__INDEX__][payment_status]" class="item-payment-status" required>
                <option value="">Pilih status</option>
                <option value="lunas">Lunas</option>
                <option value="belum_lunas">Belum lunas</option>
                <option value="panjar">Panjar</option>
                <option value="lainnya">Lainnya</option>
            </select></div>
        <div class="repair-row-actions">
            <button type="button" class="icon-button remove-item" aria-label="Hapus biaya perbaikan"
                title="Hapus biaya perbaikan">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 6h18M8 6V4h8v2m3 0-.9 14H5.9L5 6m4 4v6m6-6v6" />
                </svg>
            </button>
        </div>
    </div>
</template>

<template id="tax-item-template">
    <div class="repair-group tax-item repeatable-item">
        <h5>Item Pajak</h5>
        <div class="form-group"><label>Nama Pajak</label><input type="text" name="tax_items[__INDEX__][name]" required
                maxlength="100"></div>
        <div class="form-group"><label>Tanggal Pajak</label><input type="date" name="tax_items[__INDEX__][date]"
                required></div>
        <div class="form-group"><label>Biaya (Rp)</label><input type="text" name="tax_items[__INDEX__][cost]"
                inputmode="decimal" data-rupiah-input required></div>
        <div class="form-group"><label>Status Pembayaran</label><select name="tax_items[__INDEX__][payment_status]"
                class="item-payment-status" required>
                <option value="">Pilih status</option>
                <option value="lunas">Lunas</option>
                <option value="belum_lunas">Belum lunas</option>
                <option value="panjar">Panjar</option>
                <option value="lainnya">Lainnya</option>
            </select></div>
        <div class="form-group item-payment-note"><label>Keterangan</label><input type="text"
                name="tax_items[__INDEX__][payment_note]" maxlength="255"></div>
        <button type="button" class="btn remove-item">Hapus</button>
    </div>
</template>

<script>
    const rupiahFormatter = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });
    const transactionForm = document.querySelector('.transaction-form');
    const sellingPriceInput = document.getElementById('selling-price');
    const downPaymentInput = document.getElementById('down-payment');
    const settlementAmountInput = document.getElementById('settlement-amount');
    const leasingDisbursementInput = document.getElementById('leasing-disbursement');
    const remainingPaymentInput = document.getElementById('remaining-payment');
    const leasingRipanInput = document.getElementById('leasing-ripan');
    const insuranceRipanInput = document.getElementById('insurance-ripan');
    const boosterRipanInput = document.getElementById('booster-ripan');
    const buyPriceInput = document.getElementById('buy-price');
    const purchaseOperationalInput = document.getElementById('purchase-operational');
    const otherCostsInput = document.getElementById('other-costs');
    const calculatedSaleProfitInput = document.getElementById('calculated-sale-profit');
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
        remainingPaymentInput.value = rupiahFormatter.format(Math.max(
            sellingPrice - downPayment - settlementAmount - leasingDisbursement,
            0,
        ));
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

    function updateProfitCalculation() {
        const totalCapital = calculateTotalCapital();
        const saleProfit = toNumericAmount(sellingPriceInput.value) - totalCapital;
        const totalProfit = saleProfit
            + toNumericAmount(leasingRipanInput.value)
            + toNumericAmount(insuranceRipanInput.value)
            + toNumericAmount(boosterRipanInput.value);
        const setorProfit = toNumericAmount(setorPriceInput.value) - totalCapital;
        const setorProfitPercentage = totalCapital === 0 ? 0 : (setorProfit / totalCapital) * 100;
        const openingProfit = toNumericAmount(openingPriceInput.value) - totalCapital;
        const openingProfitPercentage = totalCapital === 0 ? 0 : (openingProfit / totalCapital) * 100;
        const showroomFee = Math.round(saleProfit * 0.1 * 100) / 100;
        const intertineExpenses = Array.from(transactionForm.querySelectorAll(
            '[name^="intertine_expenses["][name$="[cost]"]',
        )).reduce((total, input) => total + toNumericAmount(input.value), 0);
        const totalIntertineExpenses = showroomFee + intertineExpenses;
        const netProfit = saleProfit - totalIntertineExpenses;

        calculatedSaleProfitInput.value = rupiahFormatter.format(saleProfit);
        calculatedTotalProfitInput.value = rupiahFormatter.format(totalProfit);
        calculatedTotalCapitalInput.value = rupiahFormatter.format(totalCapital);
        calculatedSetorProfitInput.value = rupiahFormatter.format(setorProfit);
        calculatedSetorProfitPercentageInput.value = `${rupiahFormatter.format(setorProfitPercentage)}%`;
        calculatedOpeningProfitInput.value = rupiahFormatter.format(openingProfit);
        calculatedOpeningProfitPercentageInput.value = `${rupiahFormatter.format(openingProfitPercentage)}%`;
        calculatedShowroomFeeInput.value = rupiahFormatter.format(showroomFee);
        calculatedTotalIntertineExpensesInput.value = rupiahFormatter.format(totalIntertineExpenses);
        calculatedNetProfitInput.value = rupiahFormatter.format(netProfit);
    }

    sellingPriceInput.addEventListener('input', updateRemainingPayment);
    downPaymentInput.addEventListener('input', updateRemainingPayment);
    settlementAmountInput.addEventListener('input', updateRemainingPayment);
    leasingDisbursementInput.addEventListener('input', updateRemainingPayment);
    updateProfitCalculation();

    transactionForm.addEventListener('input', (event) => {
        if (event.target.matches('[data-rupiah-input]')) {
            formatRupiahInput(event.target, true);
        }

        if (event.target.matches(
            '#selling-price, #setor-price, #opening-price, #leasing-ripan, #insurance-ripan, #booster-ripan, #buy-price, #purchase-operational, #other-costs, [name^="tax_items["][name$="[cost]"], [name^="additional_repairs["][name$="[cost]"], [name^="intertine_expenses["][name$="[cost]"]',
        )) {
            updateProfitCalculation();
        }
    });

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
                updateProfitCalculation();
            }
        });
    }

    setupItemList('additional-repairs', 'repair-item-template', 'add-repair');
    setupItemList('tax-items', 'tax-item-template', 'add-tax-item');
</script>
@endsection