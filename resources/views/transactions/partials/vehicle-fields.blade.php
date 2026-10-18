<section class="form-section vehicle-section">
    <header class="section-heading"><span class="section-number">01</span>
        <div>
            <h2>Data kendaraan</h2>
        </div>
    </header>
    <div class="section-content field-grid vehicle-fields">
        <div class="form-group"><label for="plate-number">Plat nomor <span aria-hidden="true">*</span></label><input
                id="plate-number" type="text" name="plate_number"
                value="{{ old('plate_number', $transaction?->plate_number) }}" placeholder="Contoh: B 1234 CD"
                required autocomplete="off"></div>
        <div class="form-group"><label for="brand">Merek <span aria-hidden="true">*</span></label><input id="brand"
                type="text" name="brand" value="{{ old('brand', $transaction?->brand) }}" placeholder="Contoh: Toyota"
                required>
        </div>
        <div class="form-group"><label for="model">Model <span aria-hidden="true">*</span></label><input id="model"
                type="text" name="model" value="{{ old('model', $transaction?->model) }}"
                placeholder="Contoh: Innova Diesel G"
                required></div>
        <div class="form-group"><label for="year">Tahun <span aria-hidden="true">*</span></label><input id="year"
                type="number" name="year" value="{{ old('year', $transaction?->year) }}" placeholder="Contoh: 2014"
                required></div>
        <div class="form-group"><label for="transmission">Transmisi <span aria-hidden="true">*</span></label><input
                id="transmission" type="text" name="transmission"
                value="{{ old('transmission', $transaction?->transmission) }}" placeholder="Contoh: Manual"
                required></div>
        <div class="form-group"><label for="color">Warna <span aria-hidden="true">*</span></label><input id="color"
                type="text" name="color" value="{{ old('color', $transaction?->color) }}" placeholder="Contoh: Hitam"
                required></div>
        <div class="form-group"><label for="chassis-number">Nomor rangka</label><input id="chassis-number"
                type="text" name="chassis_number" value="{{ old('chassis_number', $transaction?->chassis_number) }}"
                placeholder="Masukkan nomor rangka" maxlength="255"></div>
        <div class="form-group"><label for="engine-number">Nomor mesin</label><input id="engine-number" type="text"
                name="engine_number" value="{{ old('engine_number', $transaction?->engine_number) }}"
                placeholder="Masukkan nomor mesin" maxlength="255"></div>
        <div class="form-group"><label for="mileage-km">Kilometer (Km)</label><input id="mileage-km" type="number"
                name="mileage_km" value="{{ old('mileage_km', $transaction?->mileage_km) }}"
                placeholder="Contoh: 85000" min="0" step="1"></div>
        <div class="form-group"><label for="owner-name">Atas nama STNK</label><input id="owner-name" type="text"
                name="owner_name" value="{{ old('owner_name', $transaction?->owner_name) }}"
                placeholder="Nama pemilik pada STNK"></div>
        <div class="form-group"><label for="manager-name">Penanggung jawab</label><input id="manager-name"
                type="text" name="manager_name" value="{{ old('manager_name', $transaction?->manager_name) }}"
                placeholder="Nama penanggung jawab">
        </div>
    </div>
</section>