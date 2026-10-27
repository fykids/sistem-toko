<details data-jual-vehicle-preview @if ($selectedVehicle) open @endif>
    <summary id="jual-vehicle-preview-title" class="form-section-title">Preview rincian mobil terpilih</summary>
    <p class="form-help" data-jual-preview-empty @if ($selectedVehicle) hidden @endif>
        Pilih kendaraan untuk menampilkan rincian kendaraan.
    </p>
    <div class="field-grid two-column" aria-labelledby="jual-vehicle-preview-title"
        @if (! $selectedVehicle) hidden @endif>
        @foreach ([
            'plate_number' => ['Nomor polisi', 'text'],
            'manager_name' => ['Penanggung jawab', 'text'],
            'brand' => ['Merek', 'text'],
            'model' => ['Model', 'text'],
            'year' => ['Tahun', 'text'],
            'transmission' => ['Transmisi', 'text'],
            'color' => ['Warna', 'text'],
            'chassis_number' => ['Nomor rangka', 'text'],
            'engine_number' => ['Nomor mesin', 'text'],
            'mileage_km' => ['Kilometer', 'number'],
            'owner_name' => ['Nama pemilik', 'text'],
            'buy_date' => ['Tanggal beli', 'date'],
            'purchase_operational' => ['OPS', 'currency'],
            'other_costs' => ['KR beli', 'currency'],
            'total_repair_cost' => ['Total biaya perbaikan', 'currency'],
            'setor_price' => ['Harga setor', 'currency'],
            'opening_price' => ['Harga buka', 'currency'],
            'selling_price' => ['Harga jual', 'currency'],
            'sell_date' => ['Tanggal jual', 'date'],
            'down_payment' => ['DP', 'currency'],
            'down_payment_date' => ['Tanggal DP', 'date'],
            'settlement_amount' => ['Pelunasan', 'currency'],
            'settlement_date' => ['Tanggal pelunasan', 'date'],
            'leasing_disbursement' => ['Pencairan leasing', 'currency'],
            'leasing_disbursement_date' => ['Tanggal pencairan leasing', 'date'],
            'remaining_payment' => ['Sisa pembayaran', 'currency'],
        ] as $field => [$label, $format])
            <div class="form-group">
                <label for="jual-preview-{{ $field }}">{{ $label }}</label>
                <output id="jual-preview-{{ $field }}" data-jual-field="{{ $field }}" data-format="{{ $format }}">
                    @php($value = $selectedVehicle?->getAttribute($field))
                    @if ($format === 'currency')
                        Rp {{ number_format((float) $value) }}
                    @elseif ($format === 'date')
                        {{ $value ?: '—' }}
                    @elseif ($format === 'number')
                        {{ $value === null ? '—' : number_format((int) $value) }}
                    @else
                        {{ filled($value) ? $value : '—' }}
                    @endif
                </output>
            </div>
        @endforeach

        @if ($canViewSensitiveData)
            @foreach ([
                'buy_price' => 'Modal beli',
                'total_capital' => 'Total modal',
                'gross_profit' => 'Laba kotor',
                'showroom_fee' => 'Estimasi komisi showroom',
                'total_intertine_expense' => 'Total pengeluaran intertine',
                'net_profit' => 'Laba bersih',
                'leasing_ripan' => 'Ripan leasing',
                'insurance_ripan' => 'Ripan asuransi',
                'booster_ripan' => 'Ripan booster',
            ] as $field => $label)
                <div class="form-group">
                    <label for="jual-preview-{{ $field }}">{{ $label }}</label>
                    <output id="jual-preview-{{ $field }}" data-jual-field="{{ $field }}" data-format="currency">
                        Rp {{ number_format((float) $selectedVehicle?->getAttribute($field)) }}
                    </output>
                </div>
            @endforeach
        @endif
    </div>

    @foreach ([
        'additional_repairs' => ['Rincian biaya perbaikan', ['name', 'date', 'cost', 'payment_status', 'payment_note']],
        'tax_items' => ['Rincian pajak', ['name', 'date', 'cost', 'payment_status', 'payment_note']],
        'intertine_expenses' => ['Rincian biaya intertine', ['name', 'cost']],
    ] as $field => [$label, $columns])
        <div class="form-group" @if (! $selectedVehicle) hidden @endif>
            <h4 class="form-section-title">{{ $label }}</h4>
            <ul data-jual-collection="{{ $field }}">
                @forelse ($selectedVehicle?->getAttribute($field) ?? [] as $item)
                    <li>
                        {{ collect($columns)->map(fn (string $column): mixed => $column === 'cost'
                            ? (isset($item[$column]) ? 'Rp '.number_format((float) $item[$column]) : null)
                            : ($item[$column] ?? null))->filter()->implode(' · ') }}
                    </li>
                @empty
                    <li>Tidak ada rincian.</li>
                @endforelse
            </ul>
        </div>
    @endforeach
</details>
