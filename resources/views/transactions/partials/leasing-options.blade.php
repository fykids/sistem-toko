@php($leasingCompanies = ['BCA', 'MUF', 'BUANA', 'ADIRA', 'SINAR MAS', 'WORI', 'WOM'])
@php($defaultLeasingOptions = collect($leasingCompanies)->map(fn ($company) => ['name' => $company])->all())
@php($leasingFields = [
    'disbursement' => 'Nilai pencairan (Rp)',
    'selling_price' => 'Harga jual (Rp)',
    'dp' => 'DP (Rp)',
    'installment_3yr' => 'Angsuran 3 tahun (Rp/bulan)',
    'installment_4yr' => 'Angsuran 4 tahun (Rp/bulan)',
    'installment_5yr' => 'Angsuran 5 tahun (Rp/bulan)',
])
@php($leasingOptions = collect(old('leasing_options', $transaction?->leasing_options ?? $defaultLeasingOptions))->values())
@php($vehicleSummary = collect([
    old('brand', $transaction?->brand),
    old('model', $transaction?->model),
    old('year', $transaction?->year),
    old('transmission', $transaction?->transmission),
    old('color', $transaction?->color),
    old('plate_number', $transaction?->plate_number),
])->filter(fn ($value) => filled($value))->implode(' '))

<section class="form-section leasing-card-wrapper" aria-labelledby="leasing-card-title">
    <header class="section-heading">
        <span class="section-number">05</span>
        <div>
            <h2 id="leasing-card-title">Pilihan leasing</h2>
        </div>
        <div class="leasing-vehicle-summary" aria-live="polite">
            <span>Kendaraan</span>
            <strong id="leasing-vehicle-name">{{ $vehicleSummary ?: 'Lengkapi data kendaraan' }}</strong>
        </div>
    </header>

    <div class="section-content leasing-section-content">
        <div class="leasing-table-actions">
            <button id="add-leasing-option" type="button" class="btn btn-secondary btn-small">
                <span aria-hidden="true">+</span>
                Tambah leasing
            </button>
        </div>
        <div class="leasing-table-responsive" role="region" aria-label="Tabel simulasi leasing" tabindex="0">
            <table class="leasing-custom-table">
                <thead>
                    <tr>
                        <th scope="col" rowspan="2" class="th-leasing-name">Nama leasing</th>
                        <th scope="col" rowspan="2" class="th-disbursement">Nilai pencairan (Rp)</th>
                        <th scope="col" rowspan="2" class="th-sale-price">Harga jual (Rp)</th>
                        <th scope="col" rowspan="2" class="th-dp">DP (Rp)</th>
                        <th scope="colgroup" colspan="3" class="th-installment-group">Angsuran (Rp/bulan)</th>
                    </tr>
                    <tr>
                        <th scope="col" class="th-sub">3 tahun</th>
                        <th scope="col" class="th-sub">4 tahun</th>
                        <th scope="col" class="th-sub">5 tahun</th>
                    </tr>
                </thead>
                <tbody id="leasing-options"
                    data-next-index="{{ ($leasingOptions->keys()->max() ?? -1) + 1 }}"
                    data-max-items="50">
                    @foreach ($leasingOptions as $index => $option)
                        <tr class="repeatable-item">
                            <th scope="row" class="td-leasing-label">
                                <label class="leasing-sr-only" for="leasing-{{ $index }}-name">Nama leasing</label>
                                <input
                                    id="leasing-{{ $index }}-name"
                                    class="leasing-input leasing-name-input"
                                    type="text"
                                    name="leasing_options[{{ $index }}][name]"
                                    value="{{ data_get($option, 'name') }}"
                                    placeholder="Nama leasing"
                                    maxlength="100"
                                    autocomplete="off"
                                >
                                <button type="button" class="icon-button remove-item leasing-remove-button"
                                    aria-label="Hapus leasing {{ data_get($option, 'name') ?: $index + 1 }}"
                                    title="Hapus leasing">
                                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M3 6h18M8 6V4h8v2m3 0-.9 14H5.9L5 6m4 4v6m6-6v6" />
                                    </svg>
                                </button>
                            </th>
                            @foreach ($leasingFields as $field => $label)
                                <td>
                                    <label class="leasing-sr-only" for="leasing-{{ $index }}-{{ $field }}">
                                        {{ data_get($option, 'name') ?: 'Leasing ' . ($index + 1) }} — {{ $label }}
                                    </label>
                                    <input
                                        id="leasing-{{ $index }}-{{ $field }}"
                                        class="leasing-input"
                                        @if ($field === 'selling_price') data-leasing-selling-price @endif
                                        @if ($field === 'dp') data-leasing-dp @endif
                                        @if ($field === 'disbursement') data-leasing-disbursement @endif
                                        @if ($field === 'dp') readonly aria-readonly="true" @endif
                                        type="text"
                                        name="leasing_options[{{ $index }}][{{ $field }}]"
                                        value="{{ data_get($option, $field) }}"
                                        placeholder="Rp 0"
                                        inputmode="decimal"
                                        autocomplete="off"
                                        data-rupiah-input
                                    >
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</section>

<template id="leasing-option-template">
    <tr class="repeatable-item">
        <th scope="row" class="td-leasing-label">
            <label class="leasing-sr-only" for="leasing-__INDEX__-name">Nama leasing</label>
            <input id="leasing-__INDEX__-name" class="leasing-input leasing-name-input" type="text"
                name="leasing_options[__INDEX__][name]" placeholder="Nama leasing" maxlength="100"
                autocomplete="off">
            <button type="button" class="icon-button remove-item leasing-remove-button"
                aria-label="Hapus leasing" title="Hapus leasing">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 6h18M8 6V4h8v2m3 0-.9 14H5.9L5 6m4 4v6m6-6v6" />
                </svg>
            </button>
        </th>
        @foreach ($leasingFields as $field => $label)
            <td>
                <label class="leasing-sr-only" for="leasing-__INDEX__-{{ $field }}">
                    Leasing baru — {{ $label }}
                </label>
                <input id="leasing-__INDEX__-{{ $field }}" class="leasing-input"
                    @if ($field === 'selling_price') data-leasing-selling-price @endif
                    @if ($field === 'dp') data-leasing-dp readonly aria-readonly="true" @endif
                    @if ($field === 'disbursement') data-leasing-disbursement @endif
                    type="text" name="leasing_options[__INDEX__][{{ $field }}]" placeholder="Rp 0"
                    inputmode="decimal" autocomplete="off" data-rupiah-input>
            </td>
        @endforeach
    </tr>
</template>
