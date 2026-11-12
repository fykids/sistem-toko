CREATE TABLE IF NOT EXISTS showroom_expenses (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    expense_date TEXT NOT NULL,
    category TEXT NOT NULL CHECK (category IN (
        'Operasional Kantor',
        'Listrik, Air & Internet',
        'Gaji & Uang Makan',
        'Perawatan & Kebersihan',
        'Pajak & Administrasi',
        'Pemasaran & Iklan',
        'Lainnya'
    )),
    title TEXT NOT NULL,
    amount INTEGER NOT NULL DEFAULT 0 CHECK (amount >= 0),
    payment_method TEXT NOT NULL DEFAULT 'Tunai' CHECK (payment_method IN (
        'Tunai',
        'Transfer Bank',
        'QRIS / E-Wallet'
    )),
    recipient_name TEXT,
    notes TEXT,
    receipt_path TEXT,
    created_by INTEGER,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_showroom_expenses_expense_date
    ON showroom_expenses (expense_date);

CREATE INDEX IF NOT EXISTS idx_showroom_expenses_category
    ON showroom_expenses (category);
