@can('viewSensitiveData', \App\Models\CarTransaction::class)
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
@endcan
