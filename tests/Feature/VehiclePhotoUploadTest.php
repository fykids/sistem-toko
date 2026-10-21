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
    ];
}

test('it places the photo section beside leasing and previews an existing photo', function () {
    Storage::fake('public');

    $photoPath = 'vehicle-photos/avanza.jpg';
    Storage::disk('public')->put($photoPath, 'existing photo');
    $transaction = CarTransaction::create(validVehiclePhotoTransactionData() + [
        'vehicle_photo_path' => $photoPath,
    ]);

    $response = $this->get(route('transactions.edit', $transaction));

    $response->assertOk()
        ->assertSee('class="leasing-photo-layout"', false)
        ->assertSee('<span class="section-number">05</span>', false)
        ->assertSee('<span class="section-number">06</span>', false)
        ->assertSee('enctype="multipart/form-data"', false)
        ->assertSee('id="vehicle-photo-input"', false)
        ->assertSee(Storage::disk('public')->url($photoPath), false);
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

    $this->get(route('transactions.show', $transaction))
        ->assertOk()
        ->assertSee(Storage::disk('public')->url($transaction->vehicle_photo_path), false);
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
