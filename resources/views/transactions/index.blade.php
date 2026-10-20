@extends('layout')

@section('page_class', 'page-shell--dashboard')

@section('content')
    <div class="showroom-layout" data-showroom-layout data-showroom-view="dashboard"
        data-sidebar-collapsed="false">
        <button class="showroom-sidebar-scrim" type="button" data-sidebar-close aria-label="Tutup navigasi"></button>

        <aside class="showroom-sidebar" id="showroom-sidebar" aria-label="Navigasi utama">
            <a class="showroom-brand" href="#dashboard" aria-label="Sistem Toko Mobil, dashboard">
                <span class="showroom-brand-mark" aria-hidden="true">ST</span>
                <span class="showroom-brand-copy">
                    <strong>Sistem Toko</strong>
                    <span>Showroom mobil</span>
                </span>
            </a>

            <p class="showroom-nav-heading">Menu utama</p>
            <nav class="showroom-nav">
                <a class="showroom-nav-link is-active" href="#dashboard" data-dashboard-nav="dashboard"
                    aria-current="page">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                        stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="8" height="8" rx="1.5" />
                        <rect x="13" y="3" width="8" height="5" rx="1.5" />
                        <rect x="13" y="10" width="8" height="11" rx="1.5" />
                        <rect x="3" y="13" width="8" height="8" rx="1.5" />
                    </svg>
                    <span>Dashboard</span>
                </a>
                <a class="showroom-nav-link" href="#inventaris" data-dashboard-nav="inventory">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                        stroke-linecap="round" stroke-linejoin="round">
                        <path d="m3 13 1.6-5.2A2.5 2.5 0 0 1 7 6h10a2.5 2.5 0 0 1 2.4 1.8L21 13" />
                        <path d="M4 13h16a1 1 0 0 1 1 1v4H3v-4a1 1 0 0 1 1-1ZM6 18v2m12-2v2M6 10h.01M18 10h.01" />
                    </svg>
                    <span>Inventaris</span>
                </a>
                <a class="showroom-nav-link" href="#penjualan" data-dashboard-nav="sales">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                        stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 19V5m0 14h16" />
                        <path d="m7 15 4-4 3 2 5-6" />
                        <path d="M15 7h4v4" />
                    </svg>
                    <span>Penjualan</span>
                </a>
                <a class="showroom-nav-link" href="#laporan" data-dashboard-nav="reports">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                        stroke-linecap="round" stroke-linejoin="round">
                        <path d="M5 3h10l4 4v14H5z" />
                        <path d="M14 3v5h5M8 12h8M8 16h8" />
                    </svg>
                    <span>Laporan</span>
                </a>
            </nav>

            <div class="showroom-sidebar-divider"></div>
            <p class="showroom-nav-heading">Pengaturan</p>
            <button class="showroom-nav-link showroom-nav-button" type="button" data-database-open>
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                    stroke-linecap="round" stroke-linejoin="round">
                    <ellipse cx="12" cy="5" rx="8" ry="3" />
                    <path d="M4 5v14c0 1.7 3.6 3 8 3s8-1.3 8-3V5M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3" />
                </svg>
                <span>Manajemen Database</span>
            </button>

            <div class="showroom-sidebar-footer">
                <span class="showroom-status-dot" aria-hidden="true"></span>
                <span class="showroom-sidebar-footer-copy">
                    <strong>Operasional aktif</strong>
                    <span>Data showroom tersimpan</span>
                </span>
            </div>
        </aside>

        <div class="showroom-main">
            <header class="showroom-topbar">
                <button class="showroom-menu-toggle" type="button" aria-label="Buka navigasi" aria-expanded="false"
                    aria-controls="showroom-sidebar" data-sidebar-toggle>
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                        stroke-linecap="round">
                        <path d="M4 7h16M4 12h16M4 17h16" />
                    </svg>
                </button>
                <div class="showroom-topbar-context">
                    <span>Showroom</span>
                    <span aria-hidden="true">/</span>
                    <strong data-current-view-title>Dashboard</strong>
                </div>
                <button class="showroom-topbar-database" type="button" data-database-open>
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                        stroke-linecap="round" stroke-linejoin="round">
                        <ellipse cx="12" cy="5" rx="8" ry="3" />
                        <path d="M4 5v14c0 1.7 3.6 3 8 3s8-1.3 8-3V5M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3" />
                    </svg>
                    <span>Database</span>
                </button>
            </header>

            <div class="showroom-content">
                <section class="showroom-dashboard-heading" id="dashboard" data-dashboard-page="dashboard"
                    aria-labelledby="dashboard-title">
                    <h1 id="dashboard-title">Dashboard Showroom</h1>
                </section>

                <section class="showroom-page-header" id="inventaris" data-dashboard-page="inventory"
                    aria-labelledby="inventory-page-title" hidden>
                    <div>
                        <p class="showroom-page-eyebrow">Kendaraan tersedia</p>
                        <h1 id="inventory-page-title">Inventaris Kendaraan</h1>
                        <p>Kelola kendaraan yang masih tersedia dan belum terjual.</p>
                    </div>
                    <span class="showroom-page-count">{{ $stockCount }} unit tersedia</span>
                </section>

                <section class="showroom-page-header" id="penjualan" data-dashboard-page="sales"
                    aria-labelledby="sales-page-title" hidden>
                    <div>
                        <p class="showroom-page-eyebrow">Transaksi selesai</p>
                        <h1 id="sales-page-title">Penjualan Kendaraan</h1>
                        <p>Riwayat kendaraan yang telah terjual beserta nilai transaksinya.</p>
                    </div>
                    <span class="showroom-page-count showroom-page-count--sales">{{ $soldCount }} unit terjual</span>
                </section>

                <section class="showroom-page-header" id="laporan" data-dashboard-page="reports"
                    aria-labelledby="reports-page-title" hidden>
                    <div>
                        <p class="showroom-page-eyebrow">Performa showroom</p>
                        <h1 id="reports-page-title">Laporan Penjualan</h1>
                        <p>Ringkasan modal, omzet, dan laba bersih dari kendaraan yang sudah terjual.</p>
                    </div>
                    <span class="showroom-page-count">Berdasarkan data transaksi</span>
                </section>

                <section class="stats-grid" id="ringkasan" data-dashboard-page="reports"
                    aria-label="Ringkasan transaksi" hidden>
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

                <section class="showroom-report-grid" data-dashboard-page="reports"
                    aria-label="Ringkasan laporan penjualan" hidden>
                    <article class="showroom-report-card">
                        <span class="showroom-report-icon showroom-report-icon--sold" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 19V5m0 14h16" />
                                <path d="m7 15 4-4 3 2 5-6" />
                                <path d="M15 7h4v4" />
                            </svg>
                        </span>
                        <span class="showroom-report-label">Kendaraan terjual</span>
                        <strong>{{ number_format($soldCount) }}</strong>
                        <span class="showroom-report-note">Dari {{ number_format($transactions->count()) }} kendaraan tercatat</span>
                    </article>
                    <article class="showroom-report-card">
                        <span class="showroom-report-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 3v18m5-14H9a3 3 0 0 0 0 6h6a3 3 0 0 1 0 6H6" />
                            </svg>
                        </span>
                        <span class="showroom-report-label">Total omzet</span>
                        <strong>Rp {{ number_format($soldRevenue) }}</strong>
                        <span class="showroom-report-note">Akumulasi harga jual kendaraan terjual</span>
                    </article>
                    <article class="showroom-report-card">
                        <span class="showroom-report-icon showroom-report-icon--capital" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                stroke-linecap="round" stroke-linejoin="round">
                                <ellipse cx="12" cy="5" rx="8" ry="3" />
                                <path d="M4 5v14c0 1.7 3.6 3 8 3s8-1.3 8-3V5M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3" />
                            </svg>
                        </span>
                        <span class="showroom-report-label">Modal kendaraan terjual</span>
                        <strong>Rp {{ number_format($soldCapital) }}</strong>
                        <span class="showroom-report-note">Modal pembelian dan biaya kendaraan</span>
                    </article>
                    <article class="showroom-report-card">
                        <span class="showroom-report-icon showroom-report-icon--profit" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path d="m4 16 6-6 4 4 6-8" />
                                <path d="M14 6h6v6" />
                                <path d="M4 20h16" />
                            </svg>
                        </span>
                        <span class="showroom-report-label">Laba bersih</span>
                        <strong class="{{ $soldNetProfit >= 0 ? 'is-positive' : 'is-negative' }}">
                            Rp {{ number_format($soldNetProfit) }}
                        </strong>
                        <span class="showroom-report-note">Setelah biaya internal showroom</span>
                    </article>
                </section>

                <section class="panel showroom-inventory" id="transaction-list"
                    data-dashboard-records aria-labelledby="transaction-list-title">
                    <div class="toolbar">
                        <div>
                            <h2 class="toolbar-title" id="transaction-list-title">Inventaris kendaraan</h2>
                            <span class="cell-secondary" data-list-description>Semua kendaraan tercatat</span>
                        </div>
                        <div class="toolbar-actions">
                            <label class="search-wrap">
                                <span class="search-symbol" aria-hidden="true">⌕</span>
                                <span class="sr-only">Cari plat, merek, atau model</span>
                                <input type="search" id="transaction-search" placeholder="Cari plat, merek, model..."
                                    autocomplete="off">
                            </label>
                            <div class="filter-tabs" data-dashboard-filters role="group"
                                aria-label="Tampilkan daftar kendaraan">
                                <button type="button" data-filter="all" aria-pressed="true">Semua</button>
                                <button type="button" data-filter="inventory" aria-pressed="false">Stok</button>
                                <button type="button" data-filter="sales" aria-pressed="false">Terjual</button>
                            </div>
                            <a class="showroom-primary-action" href="{{ route('transactions.create') }}"
                                data-dashboard-page="dashboard">
                                <span aria-hidden="true">+</span>
                                Transaksi baru
                            </a>
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
                                @foreach ($transactions as $transaction)
                                    <tr data-status="{{ $transaction->sell_date ? 'sold' : 'stock' }}"
                                        data-search="{{ strtolower($transaction->plate_number . ' ' . $transaction->brand . ' ' . $transaction->model . ' ' . $transaction->year) }}">
                                        <td data-label="Kendaraan">
                                            <span class="cell-primary">{{ $transaction->plate_number }}</span>
                                            <span class="cell-secondary">{{ $transaction->brand }} {{ $transaction->model }} ·
                                                {{ $transaction->year }}</span>
                                        </td>
                                        <td data-label="Status"><span
                                                class="status-badge {{ $transaction->sell_date ? '' : 'pending' }}">{{ $transaction->sell_date ? 'Terjual' : 'Stok' }}</span>
                                        </td>
                                        <td data-label="Tanggal beli">{{ $transaction->buy_date }}</td>
                                        <td class="numeric" data-label="Modal beli">Rp {{ number_format($transaction->buy_price) }}</td>
                                        <td class="numeric" data-label="Total modal"><strong>Rp {{ number_format($transaction->total_capital) }}</strong>
                                        </td>
                                        <td class="numeric" data-label="Harga jual">Rp {{ number_format($transaction->selling_price) }}</td>
                                        <td class="numeric" data-label="Harga stor">Rp {{ number_format($transaction->setor_price) }}</td>
                                        <td class="numeric" data-label="Harga deal">Rp {{ number_format($transaction->opening_price) }}</td>
                                        <td
                                            class="numeric {{ $transaction->gross_profit >= 0 ? 'profit-positive' : 'profit-negative' }}"
                                            data-label="Laba jual">
                                            Rp {{ number_format($transaction->gross_profit) }}</td>
                                        <td
                                            class="numeric {{ ($transaction->setor_price - $transaction->total_capital) >= 0 ? 'profit-positive' : 'profit-negative' }}"
                                            data-label="Laba stor">
                                            Rp {{ number_format($transaction->setor_price - $transaction->total_capital) }}
                                        </td>
                                        <td
                                            class="numeric {{ $transaction->calculatedTotalProfit() >= 0 ? 'profit-positive' : 'profit-negative' }}"
                                            data-label="Laba deal">
                                            Rp {{ number_format($transaction->calculatedTotalProfit()) }}</td>
                                        <td data-label="Aksi">
                                            <div class="row-actions">
                                                <a class="transaction-action-icon"
                                                    href="{{ route('transactions.show', $transaction) }}"
                                                    aria-label="Lihat detail {{ $transaction->plate_number }}" title="Detail">
                                                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none"
                                                        stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                                                        stroke-linejoin="round">
                                                        <path
                                                            d="M7 3.75h7l4 4v12.5H7a2 2 0 0 1-2-2v-12.5a2 2 0 0 1 2-2Z" />
                                                        <path d="M14 3.75v4h4M8.5 12h7M8.5 15.5h7" />
                                                    </svg>
                                                </a>
                                                <a class="transaction-action-icon"
                                                    href="{{ route('transactions.edit', $transaction) }}"
                                                    aria-label="Edit transaksi {{ $transaction->plate_number }}" title="Edit">
                                                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none"
                                                        stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                                                        stroke-linejoin="round">
                                                        <path
                                                            d="m14 5 5 5M4 20l4.5-1 10.75-10.75a2.12 2.12 0 0 0-3-3L5.5 16 4 20Z" />
                                                    </svg>
                                                </a>
                                                <form action="{{ route('transactions.destroy', $transaction) }}"
                                                    method="POST"
                                                    onsubmit="return confirm('Hapus transaksi {{ $transaction->plate_number }}? Data yang dihapus tidak dapat dikembalikan.');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="transaction-action-icon danger"
                                                        aria-label="Hapus transaksi {{ $transaction->plate_number }}"
                                                        title="Hapus">
                                                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none"
                                                            stroke="currentColor" stroke-width="1.8"
                                                            stroke-linecap="round" stroke-linejoin="round">
                                                            <path
                                                                d="M4 7h16M10 11v6m4-6v6M5.5 7l1 13h11l1-13M9 7V4h6v3" />
                                                        </svg>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                                <tr class="empty-row" data-empty-state @if ($transactions->isNotEmpty()) hidden @endif>
                                    <td colspan="12" class="table-empty">
                                        <strong data-empty-title>Tidak ada kendaraan</strong>
                                        <span data-empty-description>Belum ada data yang sesuai.</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="table-foot" id="transaction-count" aria-live="polite">
                        Menampilkan {{ $transactions->count() }} kendaraan
                    </div>
                </section>
            </div>
        </div>
    </div>

    <div class="showroom-modal" data-database-modal hidden aria-hidden="true">
        <section class="showroom-modal-dialog" role="dialog" aria-modal="true"
            aria-labelledby="database-modal-title" aria-describedby="database-modal-description">
            <header class="showroom-modal-header">
                <div>
                    <span class="showroom-modal-kicker">Pengaturan sistem</span>
                    <h2 id="database-modal-title">Manajemen Database</h2>
                    <p id="database-modal-description">Amankan dan kelola salinan data transaksi showroom.</p>
                </div>
                <button class="showroom-modal-close" type="button" data-database-close aria-label="Tutup modal">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                        stroke-linecap="round" stroke-linejoin="round">
                        <path d="m6 6 12 12M18 6 6 18" />
                    </svg>
                </button>
            </header>
            <div class="showroom-modal-body">
                <section class="showroom-database-card" aria-labelledby="database-download-title">
                    <span class="showroom-database-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                            stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 3v12m0 0 4-4m-4 4-4-4" />
                            <path d="M5 16v4h14v-4" />
                        </svg>
                    </span>
                    <div class="showroom-database-card-copy">
                        <h3 id="database-download-title">Download Backup</h3>
                        <p>Unduh salinan database SQLite untuk disimpan dengan aman.</p>
                    </div>
                    <form action="{{ route('transactions.export') }}" method="GET">
                        <button class="showroom-download-button" type="submit">Backup SQLite</button>
                    </form>
                </section>

                <section class="showroom-database-card showroom-upload-card" aria-labelledby="database-upload-title">
                    <div class="showroom-database-card-copy">
                        <span class="showroom-modal-kicker">Impor data</span>
                        <h3 id="database-upload-title">Upload file backup</h3>
                        <p>Pilih atau seret file database ke area di bawah. Format .sqlite atau .db.</p>
                    </div>
                    <form class="showroom-upload-form" action="{{ route('transactions.import') }}" method="POST"
                        enctype="multipart/form-data">
                        @csrf
                        <label class="showroom-dropzone" data-dropzone>
                            <input class="showroom-file-input" id="transaction-backup" name="backup" type="file"
                                accept=".sqlite,.db" required>
                            <span class="showroom-upload-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 16V4m0 0L7 9m5-5 5 5" />
                                    <path d="M5 14v5h14v-5" />
                                </svg>
                            </span>
                            <span class="showroom-dropzone-title">Tarik file ke sini atau <strong>pilih file</strong></span>
                            <span class="showroom-file-name" data-file-name>Belum ada file dipilih</span>
                        </label>
                        <button class="showroom-import-button" type="submit">
                            Upload dan gabungkan data
                        </button>
                    </form>
                </section>
                <p class="showroom-backup-note">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                        stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11Z" />
                        <path d="m9 12 2 2 4-4" />
                    </svg>
                    Pastikan file backup berasal dari sumber tepercaya.
                </p>
            </div>
        </section>
    </div>

@endsection