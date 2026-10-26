<section class="panel showroom-inventory" id="transaction-list"
    data-dashboard-records aria-labelledby="transaction-list-title">
    <div class="toolbar">
        <div>
            <h2 class="toolbar-title" id="transaction-list-title">Inventaris kendaraan</h2>
            <span class="cell-secondary" data-list-description>Semua kendaraan tercatat</span>
        </div>
        <div class="toolbar-actions">
            <form class="search-wrap" action="{{ route('dashboard') }}" method="GET">
                <span class="search-symbol" aria-hidden="true">⌕</span>
                <span class="sr-only">Cari plat, merek, atau model</span>
                <input type="search" id="transaction-search" name="q"
                    value="{{ $search }}" placeholder="Cari plat, merek, model..." autocomplete="off">
                <input type="hidden" name="view" value="{{ $view }}">
                @if ($startDate)
                    <input type="hidden" name="start_date" value="{{ $startDate }}">
                @endif
                @if ($endDate)
                    <input type="hidden" name="end_date" value="{{ $endDate }}">
                @endif
                <button class="sr-only" type="submit">Cari kendaraan</button>
            </form>
            <div class="filter-tabs" data-dashboard-filters role="group"
                aria-label="Tampilkan daftar kendaraan">
                <button type="button" data-filter="dashboard" aria-pressed="{{ $view === 'dashboard' ? 'true' : 'false' }}">Semua</button>
                <button type="button" data-filter="inventory" aria-pressed="{{ $view === 'inventory' ? 'true' : 'false' }}">Stok</button>
                <button type="button" data-filter="sales" aria-pressed="{{ $view === 'sales' ? 'true' : 'false' }}">Terjual</button>
            </div>
            @can('create', \App\Models\CarTransaction::class)
                <a class="showroom-primary-action" href="{{ route('transactions.create') }}"
                    data-dashboard-page="dashboard">
                    <span aria-hidden="true">+</span>
                    Transaksi baru
                </a>
            @endcan
        </div>
    </div>
    <div class="table-scroll">
        <table
            class="data-table transaction-list-table {{ $canViewSensitiveData ? 'transaction-list-table--sensitive' : 'transaction-list-table--restricted' }}">
            <thead>
                <tr>
                    <th>Kendaraan</th>
                    <th>Status</th>
                    <th>Tanggal beli</th>
                    <th>Pajak</th>
                    <th>Hari</th>
                    @can('viewSensitiveData', \App\Models\CarTransaction::class)
                        <th class="numeric">Modal beli</th>
                        <th class="numeric">Total modal</th>
                    @endcan
                    <th class="numeric">Harga jual</th>
                    <th class="numeric">Harga stor</th>
                    <th class="numeric">Harga deal</th>
                    @can('viewSensitiveData', \App\Models\CarTransaction::class)
                        <th class="numeric">Laba jual</th>
                        <th class="numeric">Laba stor</th>
                        <th class="numeric">Laba deal</th>
                    @endcan
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody id="transaction-rows">
                @foreach ($transactions as $transaction)
                    @php($taxStatus = $transaction->taxStatus())
                    @php($showroomAgeStatus = $transaction->showroomAgeStatus())
                    <tr data-status="{{ $transaction->sell_date ? 'sold' : 'stock' }}">
                        <td data-label="Kendaraan">
                            <span class="cell-primary">{{ $transaction->plate_number }}</span>
                            <span class="cell-secondary">{{ $transaction->brand }} {{ $transaction->model }} ·
                                {{ $transaction->year }}</span>
                        </td>
                        <td data-label="Status"><span
                            class="status-badge {{ $transaction->sell_date ? '' : 'pending' }}">{{ $transaction->sell_date ? 'Terjual' : 'Stok' }}</span>
                        </td>
                        <td data-label="Tanggal beli">{{ $transaction->buy_date }}</td>
                        <td data-label="Pajak">
                            <span class="status-badge {{ $taxStatus['badgeClass'] }}">{{ $taxStatus['label'] }}</span>
                        </td>
                        <td data-label="Hari">
                            <span class="status-badge {{ $showroomAgeStatus['badgeClass'] }}">{{ $showroomAgeStatus['days'] === null ? '—' : $showroomAgeStatus['days'].' hari' }}</span>
                        </td>
                        @can('viewSensitiveData', \App\Models\CarTransaction::class)
                            <td class="numeric" data-label="Modal beli">Rp {{ number_format($transaction->buy_price) }}</td>
                            <td class="numeric" data-label="Total modal"><strong>Rp {{ number_format($transaction->total_capital) }}</strong></td>
                        @endcan
                        <td class="numeric" data-label="Harga jual">Rp {{ number_format($transaction->selling_price) }}</td>
                        <td class="numeric" data-label="Harga stor">Rp {{ number_format($transaction->setor_price) }}</td>
                        <td class="numeric" data-label="Harga deal">Rp {{ number_format($transaction->opening_price) }}</td>
                        @can('viewSensitiveData', \App\Models\CarTransaction::class)
                            <td class="numeric {{ $transaction->gross_profit >= 0 ? 'profit-positive' : 'profit-negative' }}"
                                data-label="Laba jual">Rp {{ number_format($transaction->gross_profit) }}</td>
                            <td class="numeric {{ ($transaction->setor_price - $transaction->total_capital) >= 0 ? 'profit-positive' : 'profit-negative' }}"
                                data-label="Laba stor">
                                Rp {{ number_format($transaction->setor_price - $transaction->total_capital) }}
                            </td>
                            <td class="numeric {{ $transaction->calculatedTotalProfit() >= 0 ? 'profit-positive' : 'profit-negative' }}"
                                data-label="Laba deal">Rp {{ number_format($transaction->calculatedTotalProfit()) }}</td>
                        @endcan
                        <td data-label="Aksi">
                            <div class="row-actions">
                                <a class="transaction-action-icon"
                                    href="{{ route('transactions.show', $transaction) }}"
                                    aria-label="Lihat detail {{ $transaction->plate_number }}" title="Detail">
                                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none"
                                        stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                                        stroke-linejoin="round">
                                        <path d="M7 3.75h7l4 4v12.5H7a2 2 0 0 1-2-2v-12.5a2 2 0 0 1 2-2Z" />
                                        <path d="M14 3.75v4h4M8.5 12h7M8.5 15.5h7" />
                                    </svg>
                                </a>
                                @can('update', $transaction)
                                    <a class="transaction-action-icon"
                                        href="{{ route('transactions.edit', $transaction) }}"
                                        aria-label="Edit transaksi {{ $transaction->plate_number }}" title="Edit">
                                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none"
                                            stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                                            stroke-linejoin="round">
                                            <path d="m14 5 5 5M4 20l4.5-1 10.75-10.75a2.12 2.12 0 0 0-3-3L5.5 16 4 20Z" />
                                        </svg>
                                    </a>
                                @endcan
                                @can('delete', $transaction)
                                    <form action="{{ route('transactions.destroy', $transaction) }}" method="POST"
                                        onsubmit="return confirm('Hapus transaksi {{ $transaction->plate_number }}? Data yang dihapus tidak dapat dikembalikan.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="transaction-action-icon danger"
                                            aria-label="Hapus transaksi {{ $transaction->plate_number }}" title="Hapus">
                                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none"
                                                stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                                                stroke-linejoin="round">
                                                <path d="M4 7h16M10 11v6m4-6v6M5.5 7l1 13h11l1-13M9 7V4h6v3" />
                                            </svg>
                                        </button>
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @endforeach
                <tr class="empty-row" data-empty-state @if ($transactions->isNotEmpty()) hidden @endif>
                    <td colspan="{{ $tableColumnCount }}" class="table-empty">
                        <strong data-empty-title>Tidak ada kendaraan</strong>
                        <span data-empty-description>Belum ada data yang sesuai.</span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    <div class="table-foot" id="transaction-count" aria-live="polite">
        Menampilkan {{ $transactions->firstItem() ?? 0 }}-{{ $transactions->lastItem() ?? 0 }}
        dari {{ $transactions->total() }} kendaraan
    </div>
    @if ($transactions->hasPages())
        <div class="users-pagination">{{ $transactions->withQueryString()->links() }}</div>
    @endif
</section>
