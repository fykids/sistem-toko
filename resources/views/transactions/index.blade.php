@extends('layout')

@section('content')
    @php
        $stockCount = $transactions->whereNull('sell_date')->count();
        $soldCount = $transactions->whereNotNull('sell_date')->count();
        $recordedCapital = $transactions->sum('total_capital');
    @endphp

    <div class="page-heading dashboard-heading">
        <div>
            <p class="page-eyebrow">Operasional showroom</p>
            <h1>Dashboard showroom</h1>
            <p class="page-description">Pantau inventaris, penjualan, dan modal kendaraan dari satu tempat.</p>
        </div>
        <a class="btn dashboard-create" href="{{ route('transactions.create') }}">
            <span aria-hidden="true">+</span>
            Transaksi baru
        </a>
    </div>

    <section class="stats-grid" aria-label="Ringkasan transaksi">
        <div class="stat-panel">
            <span class="stat-label">Kendaraan tercatat</span>
            <strong class="stat-value">{{ $transactions->count() }}</strong>
            <span class="stat-note">Seluruh transaksi showroom</span>
        </div>
        <div class="stat-panel">
            <span class="stat-label">Tersedia di stok</span>
            <strong class="stat-value">{{ $stockCount }}</strong>
            <span class="stat-note">Belum memiliki tanggal penjualan</span>
        </div>
        <div class="stat-panel">
            <span class="stat-label">Total modal kendaraan</span>
            <strong class="stat-value green">Rp {{ number_format($recordedCapital) }}</strong>
            <span class="stat-note">Akumulasi modal seluruh kendaraan</span>
        </div>
    </section>

    <section class="panel" aria-labelledby="transaction-list-title">
        <div class="toolbar">
            <div>
                <h2 class="toolbar-title" id="transaction-list-title">Inventaris kendaraan</h2>
                <span class="cell-secondary">{{ $soldCount }} kendaraan terjual</span>
            </div>
            <div class="toolbar-actions">
                <label class="search-wrap">
                    <span class="search-symbol" aria-hidden="true">⌕</span>
                    <span class="sr-only">Cari plat, merek, atau model</span>
                    <input type="search" id="transaction-search" placeholder="Cari plat, merek, model..." autocomplete="off">
                </label>
                <div class="filter-tabs" role="group" aria-label="Filter status transaksi">
                    <button type="button" data-filter="all" aria-pressed="true">Semua</button>
                    <button type="button" data-filter="stock" aria-pressed="false">Stok</button>
                    <button type="button" data-filter="sold" aria-pressed="false">Terjual</button>
                </div>
            </div>
        </div>
        <div class="table-scroll">
            <table class="data-table transaction-list-table">
                <thead>
                    <tr>
                        <th>Kendaraan</th>
                        <th>Status</th>
                        <th>Tanggal beli</th>
                        <th class="numeric">Modal beli</th>
                        <th class="numeric">Total modal</th>
                        <th class="numeric">Harga jual</th>
                        <th class="numeric">Harga stor</th>
                        <th class="numeric">Harga deal</th>
                        <th class="numeric">Laba jual</th>
                        <th class="numeric">Laba stor</th>
                        <th class="numeric">Laba deal</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody id="transaction-rows">
                    @forelse ($transactions as $transaction)
                        <tr data-status="{{ $transaction->sell_date ? 'sold' : 'stock' }}" data-search="{{ strtolower($transaction->plate_number . ' ' . $transaction->brand . ' ' . $transaction->model . ' ' . $transaction->year) }}">
                            <td>
                                <span class="cell-primary">{{ $transaction->plate_number }}</span>
                                <span class="cell-secondary">{{ $transaction->brand }} {{ $transaction->model }} · {{ $transaction->year }}</span>
                            </td>
                            <td><span class="status-badge {{ $transaction->sell_date ? '' : 'pending' }}">{{ $transaction->sell_date ? 'Terjual' : 'Stok' }}</span></td>
                            <td>{{ $transaction->buy_date }}</td>
                            <td class="numeric">Rp {{ number_format($transaction->buy_price) }}</td>
                            <td class="numeric"><strong>Rp {{ number_format($transaction->total_capital) }}</strong></td>
                            <td class="numeric">Rp {{ number_format($transaction->selling_price) }}</td>
                            <td class="numeric">Rp {{ number_format($transaction->setor_price) }}</td>
                            <td class="numeric">Rp {{ number_format($transaction->opening_price) }}</td>
                            <td class="numeric {{ $transaction->gross_profit >= 0 ? 'profit-positive' : 'profit-negative' }}">Rp {{ number_format($transaction->gross_profit) }}</td>
                            <td class="numeric {{ ($transaction->setor_price - $transaction->total_capital) >= 0 ? 'profit-positive' : 'profit-negative' }}">Rp {{ number_format($transaction->setor_price - $transaction->total_capital) }}</td>
                            <td class="numeric {{ $transaction->calculatedTotalProfit() >= 0 ? 'profit-positive' : 'profit-negative' }}">Rp {{ number_format($transaction->calculatedTotalProfit()) }}</td>
                            <td>
                                <div class="row-actions">
                                    <a class="transaction-action-icon" href="{{ route('transactions.show', $transaction) }}" aria-label="Lihat nota {{ $transaction->plate_number }}" title="Nota">
                                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M7 3.75h7l4 4v12.5H7a2 2 0 0 1-2-2v-12.5a2 2 0 0 1 2-2Z" />
                                            <path d="M14 3.75v4h4M8.5 12h7M8.5 15.5h7" />
                                        </svg>
                                    </a>
                                    <a class="transaction-action-icon" href="{{ route('transactions.edit', $transaction) }}" aria-label="Edit transaksi {{ $transaction->plate_number }}" title="Edit">
                                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="m14 5 5 5M4 20l4.5-1 10.75-10.75a2.12 2.12 0 0 0-3-3L5.5 16 4 20Z" />
                                        </svg>
                                    </a>
                                    <form action="{{ route('transactions.destroy', $transaction) }}" method="POST" onsubmit="return confirm('Hapus transaksi {{ $transaction->plate_number }}? Data yang dihapus tidak dapat dikembalikan.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="transaction-action-icon danger" aria-label="Hapus transaksi {{ $transaction->plate_number }}" title="Hapus">
                                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M4 7h16M10 11v6m4-6v6M5.5 7l1 13h11l1-13M9 7V4h6v3" />
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr class="empty-row"><td colspan="12" class="table-empty"><strong>Belum ada transaksi</strong>Mulai dengan mencatat kendaraan pertama.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($transactions->isNotEmpty())
            <div class="table-foot" id="transaction-count">Menampilkan {{ $transactions->count() }} transaksi</div>
        @endif
    </section>

    <script>
        const transactionSearch = document.getElementById('transaction-search');
        const transactionRows = [...document.querySelectorAll('#transaction-rows tr[data-status]')];
        const filterButtons = [...document.querySelectorAll('.filter-tabs button')];
        let activeFilter = 'all';

        function filterTransactions() {
            const query = transactionSearch.value.trim().toLowerCase();
            let visibleCount = 0;

            transactionRows.forEach((row) => {
                const matchesQuery = row.dataset.search.includes(query);
                const matchesStatus = activeFilter === 'all' || row.dataset.status === activeFilter;
                row.hidden = !(matchesQuery && matchesStatus);
                visibleCount += row.hidden ? 0 : 1;
            });

            const countLabel = document.getElementById('transaction-count');
            if (countLabel) {
                countLabel.textContent = `Menampilkan ${visibleCount} dari {{ $transactions->count() }} transaksi`;
            }
        }

        transactionSearch.addEventListener('input', filterTransactions);
        filterButtons.forEach((button) => {
            button.addEventListener('click', () => {
                activeFilter = button.dataset.filter;
                filterButtons.forEach((filterButton) => filterButton.setAttribute('aria-pressed', String(filterButton === button)));
                filterTransactions();
            });
        });
    </script>
@endsection