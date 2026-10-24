<section class="jual-card jual-card--buyer" aria-labelledby="jual-buyer-title">
    <header class="jual-card-heading">
        <span class="jual-section-number">02</span>
        <div>
            <h2 id="jual-buyer-title">Informasi pembeli</h2>
            <p>Identitas yang dicatat bersama transaksi.</p>
        </div>
    </header>
    <label class="jual-field">
        <span>Nama pembeli</span>
        <input type="text" name="buyer_name" value="{{ old('buyer_name', $saleNote->buyer_name) }}" maxlength="255" required>
        @error('buyer_name')<span class="jual-error">{{ $message }}</span>@enderror
    </label>
    <label class="jual-field">
        <span>NIK</span>
        <input type="text" name="buyer_nik" value="{{ old('buyer_nik', $saleNote->buyer_nik) }}" inputmode="numeric" maxlength="16">
        @error('buyer_nik')<span class="jual-error">{{ $message }}</span>@enderror
    </label>
    <label class="jual-field">
        <span>Nomor telepon</span>
        <input type="tel" name="buyer_phone" value="{{ old('buyer_phone', $saleNote->buyer_phone) }}" maxlength="30">
        @error('buyer_phone')<span class="jual-error">{{ $message }}</span>@enderror
    </label>
    <label class="jual-field">
        <span>Alamat</span>
        <textarea name="buyer_address" rows="3" maxlength="5000">{{ old('buyer_address', $saleNote->buyer_address) }}</textarea>
        @error('buyer_address')<span class="jual-error">{{ $message }}</span>@enderror
    </label>
</section>
