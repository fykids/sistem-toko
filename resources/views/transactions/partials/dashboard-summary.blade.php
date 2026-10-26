<section class="showroom-dashboard-heading" id="dashboard" data-dashboard-page="dashboard"
    aria-labelledby="dashboard-title">
    <p class="showroom-page-eyebrow">Ringkasan data</p>
    <h1 id="dashboard-title">Dashboard Showroom</h1>
    <p class="showroom-page-description">Pantau inventaris, penjualan, dan aktivitas showroom dalam satu tempat.</p>
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
    <form action="{{ route('dashboard') }}#laporan" method="GET"
        class="flex flex-nowrap items-end gap-2"
        aria-label="Filter periode laporan penjualan">
        <input type="hidden" name="view" value="reports">
        @if ($search !== '')
            <input type="hidden" name="q" value="{{ $search }}">
        @endif
        <div class="form-group mb-0 w-36">
            <label for="report-start-date">Dari tanggal</label>
            <input class="w-36" id="report-start-date" type="date" name="start_date"
                value="{{ $startDate }}">
        </div>
        <div class="form-group mb-0 w-36">
            <label for="report-end-date">Sampai tanggal</label>
            <input class="w-36" id="report-end-date" type="date" name="end_date"
                value="{{ $endDate }}">
        </div>
        <button class="showroom-primary-action self-end" type="submit">Filter</button>
        <a class="btn btn-secondary self-end"
            href="{{ route('dashboard', ['view' => 'reports']) }}#laporan">Reset</a>
    </form>
</section>

<section class="stats-grid" id="ringkasan" data-dashboard-page="reports"
    aria-label="Ringkasan transaksi" hidden>
    <div class="stat-panel">
        <span class="stat-label">Kendaraan tercatat</span>
        <strong class="stat-value">{{ $reportTransactionCount }}</strong>
        <span class="stat-note">Kendaraan masuk atau terjual pada periode terpilih</span>
    </div>
    <div class="stat-panel">
        <span class="stat-label">Tersedia di stok</span>
        <strong class="stat-value">{{ $reportStockCount }}</strong>
        <span class="stat-note">Unit stok masuk pada periode terpilih</span>
    </div>
    @can('viewSensitiveData', \App\Models\CarTransaction::class)
        <div class="stat-panel">
            <span class="stat-label">Total modal kendaraan</span>
            <strong class="stat-value green">Rp {{ number_format($reportRecordedCapital) }}</strong>
            <span class="stat-note">Modal unit masuk atau terjual pada periode terpilih</span>
        </div>
    @endcan
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
        <strong>{{ number_format($reportSoldCount) }}</strong>
        <span class="showroom-report-note">Sesuai periode tanggal penjualan</span>
    </article>
    <article class="showroom-report-card">
        <span class="showroom-report-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 3v18m5-14H9a3 3 0 0 0 0 6h6a3 3 0 0 1 0 6H6" />
            </svg>
        </span>
        <span class="showroom-report-label">Total omzet</span>
        <strong>Rp {{ number_format($reportSoldRevenue) }}</strong>
        <span class="showroom-report-note">Akumulasi harga jual pada periode terpilih</span>
    </article>
    @can('viewSensitiveData', \App\Models\CarTransaction::class)
        <article class="showroom-report-card">
            <span class="showroom-report-icon showroom-report-icon--capital" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                    stroke-linecap="round" stroke-linejoin="round">
                    <ellipse cx="12" cy="5" rx="8" ry="3" />
                    <path d="M4 5v14c0 1.7 3.6 3 8 3s8-1.3 8-3V5M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3" />
                </svg>
            </span>
            <span class="showroom-report-label">Modal kendaraan terjual</span>
            <strong>Rp {{ number_format($reportSoldCapital) }}</strong>
            <span class="showroom-report-note">Modal pembelian dan biaya pada periode terpilih</span>
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
            <strong class="{{ $reportSoldNetProfit >= 0 ? 'is-positive' : 'is-negative' }}">
                Rp {{ number_format($reportSoldNetProfit) }}
            </strong>
            <span class="showroom-report-note">Laba bersih pada periode terpilih</span>
        </article>
    @endcan
</section>
