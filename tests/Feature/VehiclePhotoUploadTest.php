<?php

use App\Models\CarTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->master()->create());
});

function validVehiclePhotoTransactionData(): array
{
    return [
        'plate_number' => 'B 1234 ABC',
        'brand' => 'Toyota',
        'model' => 'Avanza',
        'year' => 2022,
        'transmission' => 'Automatic',
        'color' => 'Hitam',
        'buy_date' => '2026-10-01',
        'buy_price' => 100000000,
        'seller_name' => 'Penjual Kendaraan',
        'nama_penjual' => 'Penjual Kendaraan',
    ];
}

test('the SPA transaction detail API exposes an existing vehicle photo', function () {
    Storage::fake('public');

    $photoPath = 'vehicle-photos/avanza.jpg';
    Storage::disk('public')->put($photoPath, 'existing photo');
    $transaction = CarTransaction::create(validVehiclePhotoTransactionData() + [
        'vehicle_photo_path' => $photoPath,
    ]);

    $this->get("/app/transactions/{$transaction->id}/edit")
        ->assertOk()
        ->assertSee('id="app"', false);

    $this->getJson(route('api.spa.transactions.show', $transaction))
        ->assertOk()
        ->assertJsonPath('data.vehicle_photo_url', Storage::disk('public')->url($photoPath));
});

test('it redirects to the transaction list when the edited transaction does not exist', function () {
    $this->get(route('transactions.edit', 999999))
        ->assertRedirect(route('transactions.index'))
        ->assertSessionHas('error', 'Data transaksi tidak ditemukan.');
});

test('it stores multiple photos and uses the selected photo as the cover', function () {
    Storage::fake('public');

    $response = $this->post(route('transactions.store'), array_replace(validVehiclePhotoTransactionData(), [
        'nama_penjual' => 'Dewi Penjual',
        'nik_penjual' => '1234567890123456',
        'no_hp_penjual' => '08123456789',
        'alamat_penjual' => 'Jl. Contoh No. 1',
        'nama_broker' => 'Budi Broker',
        'foto_kendaraan' => [
            UploadedFile::fake()->image('avanza-depan.jpg'),
            UploadedFile::fake()->image('avanza-samping.webp'),
        ],
        'foto_utama' => 'new:1',
    ]));

    $response->assertRedirect(route('transactions.index'));
    $transaction = CarTransaction::query()->firstOrFail();
    $photos = $transaction->vehiclePhotos()->orderBy('position')->get();

    expect($transaction->seller_name)->toBe('Dewi Penjual')
        ->and($transaction->seller_nik)->toBe('1234567890123456')
        ->and($transaction->seller_phone)->toBe('08123456789')
        ->and($transaction->seller_address)->toBe('Jl. Contoh No. 1')
        ->and($transaction->broker_name)->toBe('Budi Broker')
        ->and($photos)->toHaveCount(2)
        ->and($photos->where('is_cover', true))->toHaveCount(1)
        ->and($transaction->vehicle_photo_path)->toBe($photos[1]->path);

    Storage::disk('public')->assertExists($photos[0]->path);
    Storage::disk('public')->assertExists($photos[1]->path);
});

test('it removes selected gallery photos and changes the cover without deleting retained photos', function () {
    Storage::fake('public');

    $firstPath = 'vehicle-photos/front.jpg';
    $secondPath = 'vehicle-photos/interior.jpg';
    Storage::disk('public')->put($firstPath, 'front');
    Storage::disk('public')->put($secondPath, 'interior');
    $transaction = CarTransaction::create(validVehiclePhotoTransactionData() + [
        'vehicle_photo_path' => $firstPath,
    ]);
    $firstPhoto = $transaction->vehiclePhotos()->create([
        'path' => $firstPath,
        'is_cover' => true,
        'position' => 0,
    ]);
    $secondPhoto = $transaction->vehiclePhotos()->create([
        'path' => $secondPath,
        'is_cover' => false,
        'position' => 1,
    ]);

    $this->put(route('transactions.update', $transaction), validVehiclePhotoTransactionData() + [
        'hapus_foto' => [$firstPhoto->id],
        'foto_utama' => 'existing:'.$secondPhoto->id,
    ])->assertRedirect(route('transactions.index'));

    expect($transaction->fresh()->vehicle_photo_path)->toBe($secondPath)
        ->and($transaction->vehiclePhotos()->where('is_cover', true)->value('path'))->toBe($secondPath);

    $this->assertDatabaseMissing('vehicle_photos', ['id' => $firstPhoto->id]);
    $this->assertDatabaseHas('vehicle_photos', ['id' => $secondPhoto->id, 'is_cover' => true]);
    Storage::disk('public')->assertMissing($firstPath);
    Storage::disk('public')->assertExists($secondPath);
});

test('it rejects invalid seller identity and broker input without saving transaction data', function () {
    Storage::fake('public');

    $this->from(route('transactions.create'))
        ->post(route('transactions.store'), array_replace(validVehiclePhotoTransactionData(), [
            'nama_penjual' => '',
            'nik_penjual' => '12345',
            'nama_broker' => str_repeat('B', 256),
        ]))
        ->assertRedirect(route('transactions.create'))
        ->assertSessionHasErrors(['nama_penjual', 'nik_penjual', 'nama_broker']);

    $this->assertDatabaseCount('car_transactions', 0);
    $this->assertDatabaseCount('vehicle_photos', 0);
});

test('it stores an uploaded vehicle photo and displays it on transaction details', function () {
    Storage::fake('public');

    $response = $this->post(route('transactions.store'), validVehiclePhotoTransactionData() + [
        'vehicle_photo' => UploadedFile::fake()->image('avanza.jpg'),
    ]);

    $response->assertRedirect(route('transactions.index'));
    $transaction = CarTransaction::query()->firstOrFail();

    expect($transaction->vehicle_photo_path)->toStartWith('vehicle-photos/');
    Storage::disk('public')->assertExists($transaction->vehicle_photo_path);

    $this->getJson(route('api.spa.transactions.show', $transaction))
        ->assertOk()
        ->assertJsonPath('data.vehicle_photo_url', Storage::disk('public')->url($transaction->vehicle_photo_path))
        ->assertJsonPath('data.vehicle_photos.0.url', Storage::disk('public')->url($transaction->vehicle_photo_path));
});

test('it replaces a photo on update and retains the existing photo when no replacement is uploaded', function () {
    Storage::fake('public');

    $previousPhotoPath = 'vehicle-photos/previous.jpg';
    Storage::disk('public')->put($previousPhotoPath, 'previous photo');
    $transaction = CarTransaction::create(validVehiclePhotoTransactionData() + [
        'vehicle_photo_path' => $previousPhotoPath,
    ]);

    $this->put(route('transactions.update', $transaction), validVehiclePhotoTransactionData() + [
        'vehicle_photo' => UploadedFile::fake()->image('replacement.jpg'),
    ])->assertRedirect(route('transactions.index'));

    $transaction->refresh();
    expect($transaction->vehicle_photo_path)->not->toBe($previousPhotoPath);
    Storage::disk('public')->assertMissing($previousPhotoPath);
    Storage::disk('public')->assertExists($transaction->vehicle_photo_path);

    $replacementPath = $transaction->vehicle_photo_path;

    $this->put(route('transactions.update', $transaction), validVehiclePhotoTransactionData())
        ->assertRedirect(route('transactions.index'));

    expect($transaction->refresh()->vehicle_photo_path)->toBe($replacementPath);
    Storage::disk('public')->assertExists($replacementPath);
});

test('it deletes the stored vehicle photo when the transaction is deleted', function () {
    Storage::fake('public');

    $photoPath = 'vehicle-photos/avanza.jpg';
    Storage::disk('public')->put($photoPath, 'vehicle photo');
    $transaction = CarTransaction::create(validVehiclePhotoTransactionData() + [
        'vehicle_photo_path' => $photoPath,
    ]);

    $this->delete(route('transactions.destroy', $transaction))
        ->assertRedirect(route('transactions.index'));

    $this->assertDatabaseMissing('car_transactions', ['id' => $transaction->id]);
    Storage::disk('public')->assertMissing($photoPath);
});

test('it rejects unsupported files without creating a transaction', function () {
    Storage::fake('public');

    $response = $this->from(route('transactions.create'))
        ->post(route('transactions.store'), validVehiclePhotoTransactionData() + [
            'vehicle_photo' => UploadedFile::fake()->create('notes.txt', 1, 'text/plain'),
        ]);

    $response->assertRedirect(route('transactions.create'))
        ->assertSessionHasErrors('vehicle_photo');
    $this->assertDatabaseCount('car_transactions', 0);
});
