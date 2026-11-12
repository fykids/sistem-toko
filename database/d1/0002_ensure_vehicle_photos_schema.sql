CREATE TABLE IF NOT EXISTS vehicle_photos (
    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
    car_transaction_id INTEGER NOT NULL,
    path VARCHAR NOT NULL,
    is_cover TINYINT(1) NOT NULL DEFAULT '0',
    position INTEGER NOT NULL DEFAULT '0',
    created_at DATETIME,
    updated_at DATETIME,
    FOREIGN KEY (car_transaction_id) REFERENCES car_transactions(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS vehicle_photos_car_transaction_id_position_index
    ON vehicle_photos (car_transaction_id, position);

CREATE INDEX IF NOT EXISTS vehicle_photos_path_index
    ON vehicle_photos (path);
