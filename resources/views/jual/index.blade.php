@extends('layout')

@section('page_class', 'page-shell--jual')

@section('content')
<div class="showroom-content jual-page">
    <section class="showroom-page-header jual-heading" aria-labelledby="jual-title">
        <div>
            <p class="showroom-page-eyebrow">Penjualan</p>
            <h1 id="jual-title">Laporan Penjualan</h1>
            <p>Data konsumen, kendaraan, dan nilai transaksi dari catatan penjualan.</p>
        </div>
        <div class="jual-page-actions">
            <span class="showroom-page-count">{{ $notes->total() }} transaksi</span>
            <a href="{{ route('jual.create') }}" class="showroom-primary-action">
                <span aria-hidden="true">+</span>
                Input penjualan
            </a>
        </div>
    </section>

    @php($paymentStatuses = [
        'all' => 'Semua',
        'unpaid' => 'Belum Lunas',
        'leasing' => 'Proses Leasing',
        'paid' => 'Lunas',
    ])
    <nav class="jual-page-actions" aria-label="Filter status pembayaran">
        @foreach ($paymentStatuses as $statusValue => $statusLabel)
            <a href="{{ route('jual.index', ['status' => $statusValue]) }}"
                @class(['btn', 'btn-accent' => $status === $statusValue, 'btn-secondary' => $status !== $statusValue])
                @if ($status === $statusValue) aria-current="page" @endif>
                {{ $statusLabel }}
            </a>
        @endforeach
    </nav>

    <section class="panel showroom-inventory jual-table-card" aria-label="Daftar transaksi Jual">
        <div class="table-scroll">
            <table class="data-table jual-table">
                <thead>
                    <tr>
                        <th>Kendaraan</th>
                        <th>Konsumen</th>
                        <th>Tanggal</th>
                        <th class="numeric">Harga deal</th>
                        <th>Status pembayaran</th>
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
                            <td data-label="Konsumen">{{ $saleNote->nama_konsumen }}</td>
                            <td data-label="Tanggal">{{ \Illuminate\Support\Carbon::parse($vehicle->sell_date)->format('d-m-Y') }}</td>
                            <td class="numeric" data-label="Harga deal">Rp {{ number_format((float) $vehicle->selling_price) }}</td>
                            @php($paymentStatus = $saleNote->paymentStatus())
                            <td data-label="Status pembayaran">
                                <span @class([
                                    'status-badge',
                                    'pending' => $paymentStatus !== 'paid',
                                ])>
                                    {{ ['paid' => 'Lunas', 'leasing' => 'Proses Leasing', 'unpaid' => 'Belum Lunas'][$paymentStatus] }}
                                </span>
                            </td>
                            @can('viewSensitiveData', \App\Models\CarTransaction::class)
                                @php($dealProfit = (float) $vehicle->selling_price - (float) $vehicle->total_capital)
                                @php($totalDealProfit = $dealProfit + (float) $vehicle->leasing_ripan + (float) $vehicle->insurance_ripan + (float) $vehicle->booster_ripan)
                                <td class="numeric" data-label="Modal beli">Rp {{ number_format((float) $vehicle->buy_price) }}</td>
                                <td class="numeric" data-label="Total modal">Rp {{ number_format((float) $vehicle->total_capital) }}</td>
                                <td class="numeric" data-label="Laba deal">Rp {{ number_format($totalDealProfit) }}</td>
                            @endcan
                            <td data-label="Aksi">
                                <a class="btn btn-secondary" href="{{ route('jual.show', $saleNote) }}">Detail</a>
                                @if ((float) $vehicle->remaining_payment > 0)
                                    <a class="btn btn-accent" href="{{ route('jual.payments.create', $saleNote) }}">Catat pelunasan</a>
                                @endif
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
                            <td class="table-empty" colspan="{{ $canViewSensitiveData ? 9 : 6 }}">
                                <strong>Belum ada transaksi Jual</strong>
                                Catat penjualan kendaraan melalui menu ini untuk memulai.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($notes->hasPages())
            <div class="table-foot">{{ $notes->withQueryString()->links() }}</div>
        @endif
    </section>
</div>
@endsection
