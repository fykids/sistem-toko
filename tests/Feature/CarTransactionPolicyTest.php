<?php

use App\Models\CarTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('master can view sensitive data and manage transactions', function () {
    $master = User::factory()->master()->create();
    $transaction = CarTransaction::make();

    expect($master->can('viewSensitiveData', CarTransaction::class))->toBeTrue()
        ->and($master->can('create', CarTransaction::class))->toBeTrue()
        ->and($master->can('update', $transaction))->toBeTrue()
        ->and($master->can('delete', $transaction))->toBeTrue();
});

test('admin can create transactions but cannot view sensitive data or change existing transactions', function () {
    $admin = User::factory()->create();
    $transaction = CarTransaction::make();

    expect($admin->can('viewSensitiveData', CarTransaction::class))->toBeFalse()
        ->and($admin->can('create', CarTransaction::class))->toBeTrue()
        ->and($admin->can('update', $transaction))->toBeFalse()
        ->and($admin->can('delete', $transaction))->toBeFalse();
});

test('transaction routes require an authenticated user', function () {
    $this->get(route('transactions.index'))
        ->assertRedirect('/login');
});

test('admin dashboard and transaction details omit sensitive financial values', function () {
    $transaction = CarTransaction::create([
        'plate_number' => 'B 9876 SEC',
        'brand' => 'Toyota',
        'model' => 'Avanza',
        'year' => 2022,
        'transmission' => 'Automatic',
        'color' => 'Hitam',
        'buy_date' => '2026-10-01',
        'buy_price' => 987654321,
        'selling_price' => 123456789,
    ]);
    $this->actingAs(User::factory()->create());

    $dashboard = $this->get(route('transactions.index'));

    $dashboard->assertOk()
        ->assertSee('Tanggal beli')
        ->assertDontSee('Modal beli')
        ->assertDontSee('Total modal kendaraan')
        ->assertDontSee('Laba jual')
        ->assertDontSee('987,654,321')
        ->assertDontSee(route('transactions.edit', $transaction), false);

    $this->get(route('transactions.show', $transaction))
        ->assertOk()
        ->assertSee('B 9876 SEC')
        ->assertSee('Tanggal beli')
        ->assertDontSee('987,654,321')
        ->assertDontSee('Total Modal');
});

test('admin-created transactions ignore any submitted purchase amount', function () {
    $this->actingAs(User::factory()->create());

    $this->post(route('transactions.store'), [
        'plate_number' => 'B 1234 ADM',
        'brand' => 'Honda',
        'model' => 'Civic',
        'year' => 2021,
        'transmission' => 'Automatic',
        'color' => 'Putih',
        'buy_date' => '2026-10-01',
        'buy_price' => 7654321,
        'leasing_ripan' => 450000,
        'insurance_ripan' => 150000,
        'booster_ripan' => 250000,
    ])->assertRedirect(route('transactions.index'));

    $this->assertDatabaseHas('car_transactions', [
        'plate_number' => 'B 1234 ADM',
        'buy_price' => 0,
        'leasing_ripan' => 0,
        'insurance_ripan' => 0,
        'booster_ripan' => 0,
    ]);
});

test('admin transaction form omits purchase amount and profit calculations but keeps transaction inputs', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('transactions.create'))
        ->assertOk()
        ->assertSee('id="buy-price" type="hidden" name="buy_price" value="0" data-rupiah-input', false)
        ->assertDontSee('admin-capital-notice', false)
        ->assertDontSee('Master perlu melengkapi atau mengoreksinya')
        ->assertSee('type="hidden" name="leasing_ripan" id="leasing-ripan" value="0"', false)
        ->assertSee('type="hidden" name="insurance_ripan" id="insurance-ripan" value="0"', false)
        ->assertSee('type="hidden" name="booster_ripan" id="booster-ripan" value="0"', false)
        ->assertDontSee('Ripan leasing (Rp)')
        ->assertDontSee('Ripan asuransi (Rp)')
        ->assertDontSee('Ripan booster (Rp)')
        ->assertSee('name="buy_date"', false)
        ->assertSee('name="setor_price"', false)
        ->assertSee('name="opening_price"', false)
        ->assertSee('name="selling_price"', false)
        ->assertDontSee('id="calculated-setor-profit"', false)
        ->assertDontSee('id="calculated-setor-profit-percentage"', false)
        ->assertDontSee('id="calculated-opening-profit"', false)
        ->assertDontSee('id="calculated-opening-profit-percentage"', false)
        ->assertDontSee('id="calculated-sale-profit"', false)
        ->assertDontSee('id="calculated-net-profit"', false)
        ->assertSee('class="setor-price-inputs price-inputs--restricted"', false)
        ->assertSee('class="selling-price-inputs selling-price-inputs--restricted"', false)
        ->assertSee('KR Jual')
        ->assertSee('OP Survei')
        ->assertSee('Fee CMO');
});

test('master transaction form keeps purchase notice absent and displays ripan fields', function () {
    $this->actingAs(User::factory()->master()->create());

    $this->get(route('transactions.create'))
        ->assertOk()
        ->assertSee('name="buy_price"', false)
        ->assertSee('id="leasing-ripan"', false)
        ->assertSee('id="insurance-ripan"', false)
        ->assertSee('id="booster-ripan"', false);
});

test('admin cannot edit or delete transactions or use backup routes', function () {
    $transaction = CarTransaction::create([
        'plate_number' => 'B 4567 ADM',
        'brand' => 'Honda',
        'model' => 'Civic',
        'year' => 2021,
        'transmission' => 'Automatic',
        'color' => 'Putih',
        'buy_date' => '2026-10-01',
        'buy_price' => 100000000,
    ]);
    $this->actingAs(User::factory()->create());

    $this->get(route('transactions.edit', $transaction))
        ->assertForbidden();

    $this->put(route('transactions.update', $transaction), [
        'plate_number' => 'B 4567 ADM',
        'brand' => 'Honda',
        'model' => 'Civic',
        'year' => 2021,
        'transmission' => 'Automatic',
        'color' => 'Putih',
        'buy_date' => '2026-10-01',
        'buy_price' => 100000000,
    ])->assertForbidden();

    $this->delete(route('transactions.destroy', $transaction))
        ->assertForbidden();

    $this->get(route('transactions.export'))
        ->assertForbidden();

    $this->post(route('transactions.import'))
        ->assertForbidden();

    $this->assertModelExists($transaction);
});
