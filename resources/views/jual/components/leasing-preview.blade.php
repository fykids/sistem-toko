@php($leasingCompanies = collect($selectedVehicle?->leasing_options ?? [])->filter(fn ($option) => is_array($option)))
@php($leasingFields = [
    'disbursement' => 'Nilai pencairan (Rp)',
    'selling_price' => 'Harga jual (Rp)',
    'dp' => 'DP (Rp)',
    'installment_3yr' => 'Angsuran 3 tahun (Rp/bulan)',
    'installment_4yr' => 'Angsuran 4 tahun (Rp/bulan)',
    'installment_5yr' => 'Angsuran 5 tahun (Rp/bulan)',
])

<section class="form-section leasing-card-wrapper sell-module-leasing-card col-span-12 lg:col-span-7 min-w-0 w-full"
    aria-labelledby="jual-leasing-title" data-jual-leasing-preview>
    <header class="section-heading">
        <span class="section-number">02</span>
        <div>
            <h2 id="jual-leasing-title">Informasi Pembayaran Leasing</h2>
            <p>Pratinjau read-only berdasarkan harga jual dan data leasing kendaraan.</p>
        </div>
    </header>
    <div class="section-content leasing-section-content">
        <div class="leasing-table-responsive !overflow-x-hidden" role="region" aria-label="Informasi pembayaran leasing">
            <table class="leasing-custom-table sell-module-leasing-table !min-w-0 table-fixed !text-xs [&_thead_th]:!whitespace-normal [&_th]:!p-1 [&_td]:!p-1 [&_th]:break-all [&_td]:break-all">
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
                <tbody data-jual-leasing-rows>
                    @forelse ($leasingCompanies as $option)
                        @php($disbursement = (float) ($option['disbursement'] ?? 0))
                        @php($sellingPrice = (float) ($selectedVehicle?->selling_price ?? 0))
                        <tr>
                            <th scope="row" class="td-leasing-label">
                                <span class="leasing-company-name">{{ $option['name'] ?? '-' }}</span>
                            </th>
                            <td data-label="Nilai pencairan (Rp)" data-leasing-value="disbursement">
                                {{ filled($option['disbursement'] ?? null) ? number_format($disbursement) : '-' }}
                            </td>
                            <td data-label="Harga jual (Rp)" data-leasing-value="selling_price">
                                {{ number_format($sellingPrice) }}
                            </td>
                            <td data-label="DP (Rp)" data-leasing-value="dp">
                                {{ number_format(max($sellingPrice - $disbursement, 0)) }}
                            </td>
                            @foreach (['installment_3yr', 'installment_4yr', 'installment_5yr'] as $field)
                                <td data-label="{{ $leasingFields[$field] }}">
                                    {{ filled($option[$field] ?? null) ? number_format((float) $option[$field]) : '-' }}
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr data-jual-leasing-empty>
                            <td colspan="7">Pilih mobil untuk melihat informasi leasing.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p class="leasing-currency-note">Nilai pencairan dan angsuran mengikuti data kendaraan. DP menyesuaikan harga jual.</p>
    </div>
</section>
