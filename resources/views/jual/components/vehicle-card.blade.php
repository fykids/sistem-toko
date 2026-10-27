<section class="sell-vehicle-section" aria-labelledby="jual-vehicle-title">
    <h3 id="jual-vehicle-title" class="form-section-title">Data kendaraan</h3>
    <div class="sell-vehicle-fields" data-jual-vehicle-fields>
        <div class="sell-form-row">
            <label for="jual-vehicle-select">Plat nomor</label>
            <div class="sell-form-control">
                @if ($isEditing)
                    <input id="jual-vehicle-select" type="text" value="{{ $selectedVehicle->plate_number }}" readonly>
                    <input type="hidden" data-jual-current-vehicle
                        value="{{ json_encode($selectedVehicle->attributesToArray(), JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_TAG) }}">
                @else
                    <select id="jual-vehicle-select" name="car_transaction_id" data-jual-vehicle-select required>
                        <option value="">Pilih kendaraan</option>
                        @foreach ($availableVehicles as $vehicle)
                            <option value="{{ $vehicle->id }}"
                                data-vehicle-preview="{{ json_encode($vehicle->attributesToArray(), JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_TAG) }}"
                                @selected((string) old('car_transaction_id', $selectedVehicle?->id) === (string) $vehicle->id)>
                                {{ $vehicle->plate_number }}
                            </option>
                        @endforeach
                    </select>
                    @error('car_transaction_id')<span class="form-help">{{ $message }}</span>@enderror
                @endif
            </div>
        </div>

        <div class="sell-vehicle-readonly-fields">
            @foreach ([
                'brand' => 'Merek',
                'model' => 'Model',
                'year' => 'Tahun',
                'transmission' => 'Transmisi',
                'color' => 'Warna',
                'chassis_number' => 'Nomor rangka',
                'engine_number' => 'Nomor mesin',
                'mileage_km' => 'Kilometer (Km)',
                'owner_name' => 'Atas nama STNK',
                'manager_name' => 'Penanggung jawab',
            ] as $field => $label)
                <div class="sell-form-row">
                    <label for="jual-vehicle-{{ $field }}">{{ $label }}</label>
                    <input id="jual-vehicle-{{ $field }}" type="text"
                        value="{{ $selectedVehicle?->getAttribute($field) ?? '' }}"
                        data-jual-vehicle-field="{{ $field }}" placeholder="-" readonly>
                </div>
            @endforeach
        </div>

        @unless ($isEditing)
            @unless ($hasAvailableVehicles)
                <p class="form-help">Tidak ada kendaraan yang tersedia untuk dicatat pada Menu Jual.</p>
            @endunless
        @endunless
    </div>
</section>

<section class="sell-consumer-section" aria-labelledby="jual-consumer-title">
    <h3 id="jual-consumer-title" class="form-section-title">Informasi konsumen</h3>
    <div class="sell-consumer-fields">
        <div class="sell-form-row">
            <label for="jual-buyer-name">Nama konsumen</label>
            <div class="sell-form-control">
                <input id="jual-buyer-name" type="text" name="nama_konsumen"
                    value="{{ old('nama_konsumen', $saleNote->nama_konsumen) }}" maxlength="255" required
                    placeholder="Masukkan nama konsumen">
                @error('nama_konsumen')<span class="form-help">{{ $message }}</span>@enderror
            </div>
        </div>
        <div class="sell-form-row">
            <label for="jual-buyer-nik">NIK</label>
            <div class="sell-form-control">
                <input id="jual-buyer-nik" type="text" name="buyer_nik"
                    value="{{ old('buyer_nik', $saleNote->buyer_nik) }}" inputmode="numeric"
                    maxlength="16" autocomplete="off" placeholder="Masukkan 16 digit NIK">
                @error('buyer_nik')<span class="form-help">{{ $message }}</span>@enderror
            </div>
        </div>
        <div class="sell-form-row">
            <label for="jual-buyer-address">Alamat</label>
            <div class="sell-form-control">
                <textarea id="jual-buyer-address" name="buyer_address" rows="2" maxlength="2000"
                    placeholder="Masukkan alamat lengkap konsumen">{{ old('buyer_address', $saleNote->buyer_address) }}</textarea>
                @error('buyer_address')<span class="form-help">{{ $message }}</span>@enderror
            </div>
        </div>
        <div class="sell-form-row">
            <label for="jual-buyer-phone">No. HP</label>
            <div class="sell-form-control">
                <input id="jual-buyer-phone" type="tel" name="buyer_phone"
                    value="{{ old('buyer_phone', $saleNote->buyer_phone) }}" maxlength="30"
                    autocomplete="tel" placeholder="Masukkan nomor WhatsApp/HP">
                @error('buyer_phone')<span class="form-help">{{ $message }}</span>@enderror
            </div>
        </div>
    </div>
</section>
