<section class="jual-card jual-card--payment" aria-labelledby="jual-payment-title">
    <header class="jual-card-heading">
        <span class="jual-section-number">03</span>
        <div>
            <h2 id="jual-payment-title">Harga & pembayaran</h2>
            <p>Detail pokok penjualan kendaraan.</p>
        </div>
    </header>
    <label class="jual-field">
        <span>Harga deal (Rp)</span>
        <input type="number" name="deal_price" value="{{ old('deal_price', $saleNote->deal_price) }}" min="0" step="0.01" required>
        @error('deal_price')<span class="jual-error">{{ $message }}</span>@enderror
    </label>
    <label class="jual-field">
        <span>Metode pembayaran</span>
        <select name="payment_method" required>
            <option value="">Pilih metode pembayaran</option>
            @foreach (['Tunai', 'Transfer', 'Kredit', 'Lainnya'] as $paymentMethod)
                <option value="{{ $paymentMethod }}" @selected(old('payment_method', $saleNote->payment_method) === $paymentMethod)>{{ $paymentMethod }}</option>
            @endforeach
        </select>
        @error('payment_method')<span class="jual-error">{{ $message }}</span>@enderror
    </label>
    <label class="jual-field">
        <span>Tanggal jual</span>
        <input type="date" name="sell_date" value="{{ old('sell_date', $saleNote->sell_date?->format('Y-m-d') ?? now()->toDateString()) }}" required>
        @error('sell_date')<span class="jual-error">{{ $message }}</span>@enderror
    </label>
    <label class="jual-field">
        <span>Catatan</span>
        <textarea name="notes" rows="3" maxlength="5000">{{ old('notes', $saleNote->notes) }}</textarea>
        @error('notes')<span class="jual-error">{{ $message }}</span>@enderror
    </label>
</section>
