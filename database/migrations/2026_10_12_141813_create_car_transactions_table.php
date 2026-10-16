<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('car_transactions', function (Blueprint $table) {
            $table->id();
            
            // Informasi Kendaraan
            $table->string('plate_number');
            $table->string('manager_name')->nullable(); // Pengurus
            $table->string('brand'); // Merek
            $table->string('model');
            $table->year('year');
            $table->string('transmission');
            $table->string('color');
            $table->string('chassis_number')->nullable(); // No Rangka
            $table->string('engine_number')->nullable(); // No Mesin
            $table->string('owner_name')->nullable(); // Atas Nama
            
            // Tanggal & Durasi
            $table->date('buy_date');
            $table->date('sell_date')->nullable();
            $table->integer('days_held')->default(0);

            // Modal & Pengeluaran
            $table->decimal('buy_price', 15, 2)->default(0); // Modal Beli
            $table->decimal('purchase_operational', 15, 2)->default(0); // OP Pembelian/Uang Jalan
            $table->decimal('tax_cost', 15, 2)->default(0); // Biaya Pajak
            
            // Biaya Perbaikan (Detail Opsional/Total)
            $table->decimal('battery_cost', 15, 2)->default(0); // Ganti Aki
            $table->decimal('undercarriage_cost', 15, 2)->default(0); // Service Kaki-kaki
            $table->decimal('bumper_paint_cost', 15, 2)->default(0); // Cat Bumper
            $table->decimal('oil_change_cost', 15, 2)->default(0); // Ganti Oli
            $table->decimal('total_repair_cost', 15, 2)->default(0);
            
            $table->decimal('total_capital', 15, 2)->default(0); // Total Modal

            // Penjualan & Pembayaran
            $table->decimal('selling_price', 15, 2)->default(0); // Harga Jual
            $table->decimal('down_payment', 15, 2)->default(0); // DP
            $table->decimal('remaining_payment', 15, 2)->default(0); // Sisa Pembayaran
            $table->decimal('settlement_amount', 15, 2)->default(0); // Pelunasan

            // Perhitungan Laba
            $table->decimal('gross_profit', 15, 2)->default(0); // Laba Jual
            $table->decimal('showroom_fee', 15, 2)->default(0); // Fee Showroom (Internal)
            $table->decimal('total_internal_expense', 15, 2)->default(0); // Total Pengeluaran Internal
            $table->decimal('net_profit', 15, 2)->default(0); // Laba Bersih

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('car_transactions');
    }
};