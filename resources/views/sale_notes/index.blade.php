@extends('layout')

@section('page_class', 'page-shell--sale-note')

@section('content')
<div class="showroom-content sale-note-page">
    <section class="showroom-page-header sale-note-heading" aria-labelledby="sale-note-title">
        <div>
            <p class="showroom-page-eyebrow">Modul independen</p>
            <h1 id="sale-note-title">Nota Penjualan</h1>
            <p>Daftar nota pembeli yang terpisah dari alur Penjualan kendaraan.</p>
        </div>
        <div class="sale-note-page-actions">
            <span class="showroom-page-count">{{ $notes->total() }} nota</span>
            <a href="{{ route('nota-penjualan.create') }}" class="showroom-primary-action">
                <span aria-hidden="true">+</span>
                Buat nota
            </a>
        </div>
    </section>

    <section class="panel showroom-inventory sale-note-table-card" aria-label="Daftar nota penjualan">
        <div class="table-scroll">
            <table class="data-table sale-note-table">
                <thead>
                    <tr>
                        <th>Kendaraan</th>
                        <th>Nama pembeli</th>
                        <th>Tanggal jual</th>
                        <th class="numeric">Harga deal</th>
                        @can('viewSensitiveData', \App\Models\CarTransaction::class)
                            <th class="numeric">Modal Beli</th>
                            <th class="numeric">Total Modal</th>
                            <th class="numeric">Laba Jual</th>
                            <th class="numeric">Laba Stor</th>
                            <th class="numeric">Laba Deal</th>
                        @endcan
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($notes as $saleNote)
                        @php($vehicle = $saleNote->carTransaction)
                        <tr>
                            <td data-label="Kendaraan">
                                <span class="cell-primary">{{ $vehicle->plate_number }}</span>
                                <span class="cell-secondary">{{ $vehicle->brand }} {{ $vehicle->model }} · {{ $vehicle->year }}</span>
                            </td>
                            <td data-label="Nama pembeli">{{ $saleNote->buyer_name }}</td>
                            <td data-label="Tanggal jual">{{ $saleNote->sell_date->format('d-m-Y') }}</td>
                            <td class="numeric" data-label="Harga deal">Rp {{ number_format((float) $saleNote->deal_price) }}</td>
                            @can('viewSensitiveData', \App\Models\CarTransaction::class)
                                @php($saleProfit = (float) $saleNote->deal_price - (float) $vehicle->total_capital)
                                @php($totalDealProfit = $saleProfit + (float) $vehicle->leasing_ripan + (float) $vehicle->insurance_ripan + (float) $vehicle->booster_ripan)
                                <td class="numeric" data-label="Modal Beli">Rp {{ number_format((float) $vehicle->buy_price) }}</td>
                                <td class="numeric" data-label="Total Modal">Rp {{ number_format((float) $vehicle->total_capital) }}</td>
                                <td class="numeric" data-label="Laba Jual">Rp {{ number_format($saleProfit) }}</td>
                                <td class="numeric" data-label="Laba Stor">Rp {{ number_format((float) $vehicle->setor_price - (float) $vehicle->total_capital) }}</td>
                                <td class="numeric" data-label="Laba Deal">Rp {{ number_format($totalDealProfit) }}</td>
                            @endcan
                            <td data-label="Aksi">
                                <div class="sale-note-row-actions">
                                    <a class="btn btn-secondary" href="{{ route('nota-penjualan.show', $saleNote) }}">Detail</a>
                                    <a class="btn btn-secondary" href="{{ route('nota-penjualan.edit', $saleNote) }}">Edit</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="table-empty" colspan="{{ $canViewSensitiveData ? 10 : 5 }}">
                                <strong>Belum ada nota penjualan</strong>
                                Nota baru akan tercatat di modul ini tanpa mengubah menu Penjualan.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($notes->hasPages())
            <div class="table-foot">{{ $notes->links() }}</div>
        @endif
    </section>
</div>
@endsection
