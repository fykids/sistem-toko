CREATE TABLE "migrations"(
  "id" integer primary key autoincrement not null,
  "migration" varchar not null,
  "batch" integer not null
);
CREATE TABLE "sessions"(
  "id" varchar not null,
  "user_id" integer,
  "ip_address" varchar,
  "user_agent" text,
  "payload" text not null,
  "last_activity" integer not null,
  primary key("id")
);

CREATE INDEX "sessions_user_id_index" on "sessions"("user_id");
CREATE INDEX "sessions_last_activity_index" on "sessions"("last_activity");
CREATE TABLE "cache"(
  "key" varchar not null,
  "value" text not null,
  "expiration" integer not null,
  primary key("key")
);

CREATE INDEX "cache_expiration_index" on "cache"("expiration");
CREATE TABLE "cache_locks"(
  "key" varchar not null,
  "owner" varchar not null,
  "expiration" integer not null,
  primary key("key")
);

CREATE INDEX "cache_locks_expiration_index" on "cache_locks"("expiration");
CREATE TABLE "jobs"(
  "id" integer primary key autoincrement not null,
  "queue" varchar not null,
  "payload" text not null,
  "attempts" integer not null,
  "reserved_at" integer,
  "available_at" integer not null,
  "created_at" integer not null
);
CREATE INDEX "jobs_queue_index" on "jobs"("queue");
CREATE TABLE "job_batches"(
  "id" varchar not null,
  "name" varchar not null,
  "total_jobs" integer not null,
  "pending_jobs" integer not null,
  "failed_jobs" integer not null,
  "failed_job_ids" text not null,
  "options" text,
  "cancelled_at" integer,
  "created_at" integer not null,
  "finished_at" integer,
  primary key("id")
);

CREATE TABLE "failed_jobs"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar not null,
  "connection" varchar not null,
  "queue" varchar not null,
  "payload" text not null,
  "exception" text not null,
  "failed_at" datetime not null default CURRENT_TIMESTAMP
);
CREATE INDEX "failed_jobs_connection_queue_failed_at_index" on "failed_jobs"(
  "connection",
  "queue",
  "failed_at"
);
CREATE UNIQUE INDEX "failed_jobs_uuid_unique" on "failed_jobs"("uuid");
CREATE TABLE "users"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "password" varchar not null,
  "remember_token" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  "role" varchar not null default('admin'),
  "username" varchar not null
);
CREATE UNIQUE INDEX "users_username_unique" on "users"("username");
CREATE TABLE "buyers"(
  "id" integer primary key autoincrement not null,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE TABLE "car_transactions"(
  "id" integer primary key autoincrement not null,
  "plate_number" varchar not null,
  "manager_name" varchar,
  "brand" varchar not null,
  "model" varchar not null,
  "year" integer not null,
  "transmission" varchar not null,
  "color" varchar not null,
  "chassis_number" varchar,
  "engine_number" varchar,
  "owner_name" varchar,
  "buy_date" date not null,
  "sell_date" date,
  "days_held" integer not null default('0'),
  "buy_price" numeric not null default('0'),
  "purchase_operational" numeric not null default('0'),
  "total_repair_cost" numeric not null default('0'),
  "total_capital" numeric not null default('0'),
  "selling_price" numeric default '0',
  "down_payment" numeric not null default('0'),
  "remaining_payment" numeric not null default('0'),
  "settlement_amount" numeric not null default('0'),
  "gross_profit" numeric not null default('0'),
  "showroom_fee" numeric not null default('0'),
  "total_intertine_expense" numeric not null default('0'),
  "net_profit" numeric not null default('0'),
  "created_at" datetime,
  "updated_at" datetime,
  "additional_repairs" text,
  "tax_items" text,
  "intertine_expenses" text,
  "leasing_disbursement" numeric not null default('0'),
  "mileage_km" integer not null default('0'),
  "other_costs" numeric not null default('0'),
  "setor_price" numeric not null default('0'),
  "opening_price" numeric not null default('0'),
  "leasing_ripan" numeric not null default('0'),
  "insurance_ripan" numeric not null default('0'),
  "booster_ripan" numeric not null default('0'),
  "down_payment_date" date,
  "settlement_date" date,
  "leasing_disbursement_date" date,
  "leasing_options" text,
  "vehicle_photo_path" varchar,
  "planned_payment_date" date,
  "seller_name" varchar,
  "seller_nik" varchar,
  "seller_phone" varchar,
  "seller_address" text,
  "broker_name" varchar
);
CREATE INDEX "car_transactions_dashboard_sort_index" on "car_transactions"(
  "created_at",
  "id"
);
CREATE INDEX "car_transactions_purchase_date_index" on "car_transactions"(
  "buy_date"
);
CREATE INDEX "car_transactions_status_sort_index" on "car_transactions"(
  "sell_date",
  "created_at",
  "id"
);
CREATE TABLE "sale_payments"(
  "id" integer primary key autoincrement not null,
  "sale_note_id" integer not null,
  "amount" numeric not null,
  "payment_method" varchar not null,
  "payment_date" date not null,
  "notes" text,
  "recorded_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("sale_note_id") references "sale_notes"("id") on delete cascade,
  foreign key("recorded_by") references "users"("id") on delete set null
);
CREATE INDEX "sale_payments_sale_note_id_payment_date_id_index" on "sale_payments"(
  "sale_note_id",
  "payment_date",
  "id"
);
CREATE TABLE "sale_notes"(
  "id" integer primary key autoincrement not null,
  "car_transaction_id" integer not null,
  "nama_konsumen" varchar not null,
  "created_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "buyer_nik" varchar,
  "buyer_phone" varchar,
  "buyer_address" text,
  foreign key("car_transaction_id") references car_transactions("id") on delete cascade on update no action,
  foreign key("created_by") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "sale_notes_car_transaction_id_unique" on "sale_notes"(
  "car_transaction_id"
);
CREATE INDEX "sale_notes_creator_index" on "sale_notes"("created_by");
CREATE TABLE "vehicle_photos"(
  "id" integer primary key autoincrement not null,
  "car_transaction_id" integer not null,
  "path" varchar not null,
  "is_cover" tinyint(1) not null default '0',
  "position" integer not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("car_transaction_id") references "car_transactions"("id") on delete cascade
);
CREATE INDEX "vehicle_photos_car_transaction_id_position_index" on "vehicle_photos"(
  "car_transaction_id",
  "position"
);

INSERT INTO migrations VALUES(1,'0001_01_01_000000_create_users_table',1);
INSERT INTO migrations VALUES(2,'0001_01_01_000001_create_cache_table',1);
INSERT INTO migrations VALUES(3,'0001_01_01_000002_create_jobs_table',1);
INSERT INTO migrations VALUES(4,'2026_10_12_141813_create_car_transactions_table',1);
INSERT INTO migrations VALUES(5,'2026_10_12_143439_add_additional_repairs_to_car_transactions_table',1);
INSERT INTO migrations VALUES(6,'2026_10_12_144006_add_repair_dates_to_car_transactions_table',1);
INSERT INTO migrations VALUES(7,'2026_10_12_150936_remove_fixed_repair_columns_from_car_transactions_table',1);
INSERT INTO migrations VALUES(8,'2026_10_12_151315_replace_tax_cost_with_tax_items_on_car_transactions_table',1);
INSERT INTO migrations VALUES(9,'2026_10_12_155830_add_internal_expenses_to_car_transactions_table',1);
INSERT INTO migrations VALUES(10,'2026_10_12_160442_add_leasing_disbursement_to_car_transactions_table',1);
INSERT INTO migrations VALUES(11,'2026_10_12_162232_add_mileage_to_car_transactions_table',1);
INSERT INTO migrations VALUES(12,'2026_10_13_183501_add_other_costs_to_car_transactions_table',1);
INSERT INTO migrations VALUES(13,'2026_10_15_100000_add_setor_opening_prices_and_ripan_to_car_transactions_table',1);
INSERT INTO migrations VALUES(14,'2026_10_15_171622_rename_expense_columns_to_intertine',1);
INSERT INTO migrations VALUES(15,'2026_10_16_055122_update_mileage_km_default_on_car_transactions_table',1);
INSERT INTO migrations VALUES(16,'2026_10_16_060247_add_payment_dates_to_car_transactions_table',1);
INSERT INTO migrations VALUES(17,'2026_10_17_032230_add_leasing_options_to_car_transactions_table',1);
INSERT INTO migrations VALUES(18,'2026_10_18_084751_add_vehicle_photo_path_to_car_transactions_table',1);
INSERT INTO migrations VALUES(19,'2026_10_20_151251_add_role_to_users_table',1);
INSERT INTO migrations VALUES(20,'2026_10_22_105615_replace_user_email_with_username',1);
INSERT INTO migrations VALUES(21,'2026_10_22_153213_create_buyers_table',2);
INSERT INTO migrations VALUES(22,'2026_10_22_155818_create_sale_notes_table',3);
INSERT INTO migrations VALUES(23,'2026_10_22_162613_repair_sale_notes_schema',4);
INSERT INTO migrations VALUES(24,'2026_10_22_163037_rebuild_incomplete_sale_notes_table',5);
INSERT INTO migrations VALUES(25,'2026_10_24_042316_normalize_sale_notes_and_sync_vehicle_sale_fields',6);
INSERT INTO migrations VALUES(26,'2026_10_24_043626_remove_legacy_sell_date_from_sale_notes_table',7);
INSERT INTO migrations VALUES(27,'2026_10_24_052957_simplify_sale_notes_buyer_data',8);
INSERT INTO migrations VALUES(28,'2026_10_24_085008_add_dashboard_query_indexes_to_car_transactions_table',9);
INSERT INTO migrations VALUES(29,'2026_10_26_023944_allow_null_selling_price_on_car_transactions',10);
INSERT INTO migrations VALUES(30,'2026_10_26_030626_create_sale_payments_table',11);
INSERT INTO migrations VALUES(31,'2026_10_26_033708_repair_sale_payments_table_schema',12);
INSERT INTO migrations VALUES(32,'2026_10_26_045716_add_marketing_role_to_users_table',13);
INSERT INTO migrations VALUES(33,'2026_10_27_100605_add_planned_payment_and_buyer_contact_columns',14);
INSERT INTO migrations VALUES(34,'2026_11_04_000000_allow_sale_note_creator_deletion',15);
INSERT INTO migrations VALUES(35,'2026_11_04_091457_add_seller_and_broker_details_and_vehicle_photos_to_car_transactions_table',16);
INSERT INTO migrations VALUES(36,'2026_11_04_101652_repair_missing_vehicle_photos_table',17);
INSERT INTO migrations VALUES(37,'2026_11_04_101812_reconcile_transaction_seller_and_vehicle_photo_schema',18);
