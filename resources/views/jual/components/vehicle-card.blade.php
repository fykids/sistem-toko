<section aria-labelledby="jual-vehicle-title">
    <h3 id="jual-vehicle-title" class="form-section-title">Data utama</h3>
    <div class="field-grid two-column">
        <div class="form-group">
            <label for="jual-vehicle-select">Pilih mobil</label>
    @if ($isEditing)
            <input id="jual-vehicle-select" type="text"
                value="{{ $selectedVehicle->plate_number }} · {{ $selectedVehicle->brand }} {{ $selectedVehicle->model }} ({{ $selectedVehicle->year }})"
                readonly>
            <input type="hidden" data-jual-current-vehicle
                value="{{ json_encode($selectedVehicle->attributesToArray(), JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_TAG) }}">
    @else
            <select id="jual-vehicle-select" name="car_transaction_id" data-jual-vehicle-select required>
                <option value="">Pilih kendaraan</option>
                @foreach ($availableVehicles as $vehicle)
                    <option value="{{ $vehicle->id }}"
                        data-vehicle-preview="{{ json_encode($vehicle->attributesToArray(), JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_TAG) }}"
                        @selected((string) old('car_transaction_id', $selectedVehicle?->id) === (string) $vehicle->id)>
                        {{ $vehicle->plate_number }} · {{ $vehicle->brand }} {{ $vehicle->model }} ({{ $vehicle->year }})
                    </option>
                @endforeach
            </select>
            @error('car_transaction_id')<span class="form-help">{{ $message }}</span>@enderror
    @endif
        </div>
        <div class="form-group">
            <label for="jual-buyer-name">Nama pembeli</label>
            <input id="jual-buyer-name" type="text" name="nama_konsumen"
                value="{{ old('nama_konsumen', $saleNote->nama_konsumen) }}" maxlength="255" required
                placeholder="Masukkan nama pembeli">
            @error('nama_konsumen')<span class="form-help">{{ $message }}</span>@enderror
        </div>
    </div>
    @unless ($isEditing)
        @unless ($hasAvailableVehicles)
            <p class="form-help">Tidak ada kendaraan yang tersedia untuk dicatat pada Menu Jual.</p>
        @endunless
    @endunless
</section>
