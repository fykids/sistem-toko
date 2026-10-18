@php($leasingCompanies = ['BCA', 'MUF', 'BUANA', 'ADIRA', 'SINAR MAS', 'WORI', 'WOM'])
@php($leasingFields = [
    'disbursement' => 'Nilai pencairan (Rp)',
    'selling_price' => 'Harga jual (Rp)',
    'dp' => 'DP (Rp)',
    'installment_3yr' => 'Angsuran 3 tahun (Rp/bulan)',
    'installment_4yr' => 'Angsuran 4 tahun (Rp/bulan)',
    'installment_5yr' => 'Angsuran 5 tahun (Rp/bulan)',
])
@php($leasingOptions = old('leasing_options', $transaction?->leasing_options ?? []))
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
                <tbody>
                    @foreach ($leasingCompanies as $index => $company)
                        <tr>
                            <th scope="row" class="td-leasing-label">
                                <span class="leasing-company-name">{{ $company }}</span>
                                <input type="hidden" name="leasing_options[{{ $index }}][name]" value="{{ $company }}">
                            </th>
                            @foreach ($leasingFields as $field => $label)
                                <td>
                                    <label class="leasing-sr-only" for="leasing-{{ $index }}-{{ $field }}">
                                        {{ $company }} — {{ $label }}
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
                                        value="{{ old("leasing_options.{$index}.{$field}", data_get($leasingOptions, "{$index}.{$field}")) }}"
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
