CREATE TABLE IF NOT EXISTS nota_settings (
    id INTEGER PRIMARY KEY CHECK (id = 1),
    showroom_name TEXT NOT NULL DEFAULT 'Cempaka Jaya Mobilindo',
    subtitle TEXT NOT NULL DEFAULT 'Bukti transaksi penjualan kendaraan',
    showroom_address TEXT NOT NULL DEFAULT '',
    showroom_phone TEXT NOT NULL DEFAULT '',
    invoice_prefix TEXT NOT NULL DEFAULT 'NJM',
    footer_note TEXT NOT NULL DEFAULT '',
    signatory_name TEXT NOT NULL DEFAULT 'Pihak Showroom',
    signatory_title TEXT NOT NULL DEFAULT '',
    show_buyer_nik INTEGER NOT NULL DEFAULT 1 CHECK (show_buyer_nik IN (0, 1)),
    show_vehicle_identifiers INTEGER NOT NULL DEFAULT 1 CHECK (show_vehicle_identifiers IN (0, 1)),
    show_payment_breakdown INTEGER NOT NULL DEFAULT 1 CHECK (show_payment_breakdown IN (0, 1)),
    show_payment_status INTEGER NOT NULL DEFAULT 1 CHECK (show_payment_status IN (0, 1)),
    show_signatures INTEGER NOT NULL DEFAULT 1 CHECK (show_signatures IN (0, 1)),
    accent_theme TEXT NOT NULL DEFAULT 'emerald' CHECK (accent_theme IN ('emerald', 'classic', 'navy')),
    paper_size TEXT NOT NULL DEFAULT 'a4' CHECK (paper_size IN ('a4', 'a5-landscape')),
    updated_by INTEGER,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

INSERT OR IGNORE INTO nota_settings (id) VALUES (1);
