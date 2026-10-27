@include('jual.components.vehicle-card')
@include('jual.components.payment-card')
<section class="jual-form-section jual-form-section--costs" aria-labelledby="jual-costs-title">
    <header class="jual-card-heading">
        <div>
            <h2 id="jual-costs-title">Biaya & komisi penjualan</h2>
            <p>Rincian ini tersimpan pada data kendaraan terpilih.</p>
        </div>
    </header>
    @include('transactions.partials.intertine-expense-fields', ['showLegacyIntertineExpenses' => false])
</section>

<div class="jual-form-actions">
    <a href="{{ route($formIndexRoute ?? 'jual.index') }}" class="btn btn-secondary">Batal</a>
    <button type="submit" class="btn btn-accent">{{ $isEditing ? 'Simpan perubahan' : 'Simpan penjualan' }}</button>
</div>
