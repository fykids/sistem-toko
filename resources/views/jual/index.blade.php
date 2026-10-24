@extends('layout')

@section('page_class', 'page-shell--jual')

@section('content')
<div class="showroom-content jual-page">
    <section class="showroom-page-header jual-heading" aria-labelledby="jual-title">
        <div>
            <p class="showroom-page-eyebrow">Modul mandiri</p>
            <h1 id="jual-title">Jual</h1>
            <p>Input dan ringkasan data penjualan kendaraan.</p>
        </div>
        <div class="jual-page-actions">
            <span class="showroom-page-count">{{ $notes->total() }} transaksi</span>
            <a href="{{ route('jual.create') }}" class="showroom-primary-action">
                <span aria-hidden="true">+</span>
                Input penjualan
            </a>
        </div>
    </section>

    <section class="panel showroom-inventory jual-table-card" aria-label="Daftar transaksi Jual">
        <div class="table-scroll">
            <table class="data-table jual-table">
                <thead>
                    <tr>
                        <th>Kendaraan</th>
                        <th>Pembeli</th>
                        <th>Tanggal</th>
                        <th class="numeric">Harga deal</th>
                        @can('viewSensitiveData', \App\Models\CarTransaction::class)
                            <th class="numeric">Modal beli</th>
                            <th class="numeric">Total modal</th>
                            <th class="numeric">Laba deal</th>
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
                            <td data-label="Pembeli">{{ $saleNote->buyer_name }}</td>
                            <td data-label="Tanggal">{{ $saleNote->sell_date->format('d-m-Y') }}</td>
                            <td class="numeric" data-label="Harga deal">Rp {{ number_format((float) $saleNote->deal_price) }}</td>
                            @can('viewSensitiveData', \App\Models\CarTransaction::class)
                                @php($dealProfit = (float) $saleNote->deal_price - (float) $vehicle->total_capital)
                                @php($totalDealProfit = $dealProfit + (float) $vehicle->leasing_ripan + (float) $vehicle->insurance_ripan + (float) $vehicle->booster_ripan)
                                <td class="numeric" data-label="Modal beli">Rp {{ number_format((float) $vehicle->buy_price) }}</td>
                                <td class="numeric" data-label="Total modal">Rp {{ number_format((float) $vehicle->total_capital) }}</td>
                                <td class="numeric" data-label="Laba deal">Rp {{ number_format($totalDealProfit) }}</td>
                            @endcan
                            <td data-label="Aksi">
                                <a class="btn btn-secondary" href="{{ route('jual.show', $saleNote) }}">Detail</a>
                                <a class="btn btn-secondary" href="{{ route('jual.edit', $saleNote) }}">Edit</a>
                                <form action="{{ route('jual.destroy', $saleNote) }}" method="POST" class="jual-delete-form">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-secondary" type="submit">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="table-empty" colspan="{{ $canViewSensitiveData ? 8 : 5 }}">
                                <strong>Belum ada transaksi Jual</strong>
                                Catat penjualan kendaraan melalui menu ini untuk memulai.
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
