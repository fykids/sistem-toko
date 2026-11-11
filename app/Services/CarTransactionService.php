<?php

namespace App\Services;

use App\Models\CarTransaction;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class CarTransactionService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    /**
     * @param  array<int, UploadedFile>  $photos
     */
    public function create(
        array $attributes,
        array $photos,
        ?string $coverPhotoToken,
        User $user,
    ): void {
        if ($user->cannot('viewSensitiveData', CarTransaction::class)) {
            $attributes = array_replace($attributes, [
                'buy_price' => 0,
                'leasing_ripan' => 0,
                'insurance_ripan' => 0,
                'booster_ripan' => 0,
            ]);
        }

        $storedPhotoPaths = [];

        try {
            DB::transaction(function () use (
                $attributes,
                $photos,
                $coverPhotoToken,
                &$storedPhotoPaths,
            ): void {
                $transaction = CarTransaction::create($attributes);
                $this->synchronizeVehiclePhotos(
                    $transaction,
                    $photos,
                    $coverPhotoToken,
                    [],
                    false,
                    $storedPhotoPaths,
                );
            });
        } catch (Throwable $exception) {
            foreach ($storedPhotoPaths as $photoPath) {
                $this->deleteVehiclePhotoIfExists($photoPath, null);
            }

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(
        CarTransaction $transaction,
        array $attributes,
        array $photos,
        ?string $coverPhotoToken,
        array $removedPhotoIds,
        bool $removeLegacyPhoto,
        User $user,
    ): void {
        $storedPhotoPaths = [];
        $deletedPhotoPaths = [];

        try {
            DB::transaction(function () use (
                $transaction,
                $attributes,
                $user,
                $photos,
                $coverPhotoToken,
                $removedPhotoIds,
                $removeLegacyPhoto,
                &$storedPhotoPaths,
                &$deletedPhotoPaths,
            ): void {
                $lockedTransaction = CarTransaction::query()
                    ->whereKey($transaction->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $saleNote = $lockedTransaction->saleNote()->lockForUpdate()->first();

                if ($saleNote !== null) {
                    $attributes['sell_date'] = $lockedTransaction->sell_date;
                    $attributes['selling_price'] = $lockedTransaction->selling_price;
                }

                if ($user->cannot('viewSensitiveData', CarTransaction::class)) {
                    $attributes = array_replace($attributes, [
                        'buy_price' => $lockedTransaction->buy_price,
                        'leasing_ripan' => $lockedTransaction->leasing_ripan,
                        'insurance_ripan' => $lockedTransaction->insurance_ripan,
                        'booster_ripan' => $lockedTransaction->booster_ripan,
                    ]);
                }

                $lockedTransaction->update($attributes);
                $deletedPhotoPaths = $this->synchronizeVehiclePhotos(
                    $lockedTransaction,
                    $photos,
                    $coverPhotoToken,
                    $removedPhotoIds,
                    $removeLegacyPhoto,
                    $storedPhotoPaths,
                );
            });
        } catch (Throwable $exception) {
            foreach ($storedPhotoPaths as $photoPath) {
                $this->deleteVehiclePhotoIfExists($photoPath, $transaction->id);
            }

            throw $exception;
        }

        foreach (array_unique($deletedPhotoPaths) as $photoPath) {
            $this->deleteVehiclePhotoIfExists($photoPath, $transaction->id);
        }
    }

    public function delete(CarTransaction $transaction): void
    {
        $photoPaths = $transaction->vehiclePhotos()->pluck('path')->all();
        if ($transaction->vehicle_photo_path !== null) {
            $photoPaths[] = $transaction->vehicle_photo_path;
        }

        DB::transaction(function () use ($transaction): void {
            CarTransaction::query()
                ->whereKey($transaction->id)
                ->lockForUpdate()
                ->firstOrFail()
                ->delete();
        });

        foreach (array_unique($photoPaths) as $photoPath) {
            $this->deleteVehiclePhotoIfExists($photoPath, $transaction->id);
        }
    }

    private function storeVehiclePhoto(UploadedFile $photo): string
    {
        $path = $photo->store('vehicle-photos', 'public');

        if (! is_string($path)) {
            throw new RuntimeException('The vehicle photo could not be stored.');
        }

        return $path;
    }

    /**
     * @param  array<int, UploadedFile>  $photos
     * @param  array<int, int|string>  $removedPhotoIds
     * @param  array<int, string>  $storedPhotoPaths
     * @return array<int, string>
     */
    private function synchronizeVehiclePhotos(
        CarTransaction $transaction,
        array $photos,
        ?string $coverPhotoToken,
        array $removedPhotoIds,
        bool $removeLegacyPhoto,
        array &$storedPhotoPaths,
    ): array {
        $existingPhotos = $transaction->vehiclePhotos()->get();

        if (
            $transaction->vehicle_photo_path !== null
            && ! $existingPhotos->contains('path', $transaction->vehicle_photo_path)
        ) {
            $existingPhotos->push($transaction->vehiclePhotos()->create([
                'path' => $transaction->vehicle_photo_path,
                'is_cover' => true,
                'position' => 0,
            ]));
        }

        $deletedPhotoPaths = [];
        $photosToRemove = $existingPhotos->filter(
            static fn ($photo): bool => in_array((string) $photo->id, array_map('strval', $removedPhotoIds), true)
                || ($removeLegacyPhoto && $photo->path === $transaction->vehicle_photo_path),
        );

        foreach ($photosToRemove as $photo) {
            $deletedPhotoPaths[] = $photo->path;
            $photo->delete();
        }

        $availablePhotos = $existingPhotos->reject(
            static fn ($photo): bool => $photosToRemove->contains('id', $photo->id),
        )->values();
        $newPhotosByToken = [];
        $nextPosition = (int) ($availablePhotos->max('position') ?? -1) + 1;

        foreach (array_values($photos) as $index => $uploadedPhoto) {
            if (! $uploadedPhoto instanceof UploadedFile) {
                continue;
            }

            $path = $this->storeVehiclePhoto($uploadedPhoto);
            $storedPhotoPaths[] = $path;
            $newPhoto = $transaction->vehiclePhotos()->create([
                'path' => $path,
                'is_cover' => false,
                'position' => $nextPosition++,
            ]);
            $newPhotosByToken['new:'.$index] = $newPhoto;
        }

        $selectedCover = match (true) {
            isset($newPhotosByToken[$coverPhotoToken ?? '']) => $newPhotosByToken[$coverPhotoToken],
            preg_match('/^existing:(\d+)$/D', $coverPhotoToken ?? '', $matches) === 1 => $availablePhotos->firstWhere('id', (int) $matches[1]),
            $coverPhotoToken === 'existing:legacy' => $availablePhotos->firstWhere('path', $transaction->vehicle_photo_path),
            default => null,
        };

        $selectedCover ??= $availablePhotos->firstWhere('is_cover', true)
            ?? $availablePhotos->first()
            ?? collect($newPhotosByToken)->first();

        $allPhotos = $availablePhotos->concat(array_values($newPhotosByToken));

        foreach ($allPhotos as $photo) {
            $photo->forceFill(['is_cover' => $selectedCover?->is($photo) ?? false])->save();
        }

        $transaction->forceFill([
            'vehicle_photo_path' => $selectedCover?->path,
        ])->save();

        return $deletedPhotoPaths;
    }

    private function deleteVehiclePhotoIfExists(string $path, ?int $transactionId): void
    {
        $disk = Storage::disk('public');

        if ($disk->exists($path) && ! $disk->delete($path)) {
            Log::error('A vehicle photo could not be deleted.', [
                'path' => $path,
                'transaction_id' => $transactionId,
            ]);
        }
    }
}
