<section class="jual-card jual-card--vehicle" aria-labelledby="jual-vehicle-title">
    <header class="jual-card-heading">
        <span class="jual-section-number">01</span>
        <div>
            <h2 id="jual-vehicle-title">Data kendaraan</h2>
            <p>{{ $isEditing ? 'Kendaraan dalam nota ini tidak dapat diganti.' : 'Pilih kendaraan untuk transaksi Jual.' }}</p>
        </div>
    </header>
    @if ($isEditing)
        <div class="jual-vehicle-summary">
            <strong>{{ $selectedVehicle->plate_number }}</strong>
            <span>{{ $selectedVehicle->brand }} {{ $selectedVehicle->model }} · {{ $selectedVehicle->year }}</span>
        </div>
    @else
        <label class="jual-field">
            <span>Kendaraan</span>
            <select name="car_transaction_id" required>
                <option value="">Pilih kendaraan</option>
                @foreach ($availableVehicles as $vehicle)
                    <option value="{{ $vehicle->id }}" @selected((string) old('car_transaction_id', $selectedVehicle?->id) === (string) $vehicle->id)>
                        {{ $vehicle->plate_number }} · {{ $vehicle->brand }} {{ $vehicle->model }} ({{ $vehicle->year }})
                    </option>
                @endforeach
            </select>
            @error('car_transaction_id')<span class="jual-error">{{ $message }}</span>@enderror
        </label>
        @if ($availableVehicles->isEmpty())
            <p class="jual-empty-hint">Tidak ada kendaraan yang tersedia untuk dicatat pada Menu Jual.</p>
        @endif
        <div class="jual-vehicle-summary" data-jual-vehicle-summary>
            @if ($selectedVehicle)
                <strong>{{ $selectedVehicle->plate_number }}</strong>
                <span>{{ $selectedVehicle->brand }} {{ $selectedVehicle->model }} · {{ $selectedVehicle->year }}</span>
            @else
                <span>Ringkasan kendaraan muncul setelah pemilihan.</span>
            @endif
        </div>
    @endif
</section>
