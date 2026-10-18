@php($vehiclePhotoUrl = $transaction?->vehicle_photo_path
    ? \Illuminate\Support\Facades\Storage::disk('public')->url($transaction->vehicle_photo_path)
    : null)

<section class="form-section vehicle-photo-card" aria-labelledby="vehicle-photo-title">
    <header class="section-heading">
        <span class="section-number">06</span>
        <div>
            <h2 id="vehicle-photo-title">Foto kendaraan</h2>
        </div>
    </header>

    <div class="section-content vehicle-photo-content">
        <button
            id="vehicle-photo-button"
            class="vehicle-photo-button"
            type="button"
            aria-controls="vehicle-photo-input"
            aria-describedby="vehicle-photo-help @error('vehicle_photo') vehicle-photo-error @enderror"
        >{{ $vehiclePhotoUrl ? 'Ganti foto' : 'Pilih foto kendaraan' }}</button>
        <input
            id="vehicle-photo-input"
            class="vehicle-photo-input"
            type="file"
            name="vehicle_photo"
            accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
            tabindex="-1"
            aria-hidden="true"
        >
        <small id="vehicle-photo-help" class="form-help">Format JPG, PNG, atau WEBP. Maksimal 5 MB.</small>
        @error('vehicle_photo')
            <small id="vehicle-photo-error" class="vehicle-photo-error" role="alert">{{ $message }}</small>
        @enderror

        <div class="vehicle-photo-preview-frame">
            <img
                id="vehicle-photo-preview"
                class="vehicle-photo-preview"
                alt="Pratinjau foto kendaraan"
                @if ($vehiclePhotoUrl)
                    src="{{ $vehiclePhotoUrl }}"
                @else
                    hidden
                @endif
            >
            <p id="vehicle-photo-placeholder" class="vehicle-photo-placeholder" @if ($vehiclePhotoUrl) hidden @endif>
                Pratinjau foto akan ditampilkan di sini.
            </p>
        </div>
    </div>
</section>
