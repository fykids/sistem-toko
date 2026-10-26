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
    public function create(array $attributes, ?UploadedFile $photo, User $user): void
    {
        if ($user->cannot('viewSensitiveData', CarTransaction::class)) {
            $attributes = array_replace($attributes, [
                'buy_price' => 0,
                'leasing_ripan' => 0,
                'insurance_ripan' => 0,
                'booster_ripan' => 0,
            ]);
        }

        $photoPath = null;

        try {
            DB::transaction(function () use ($attributes, $photo, &$photoPath): void {
                if ($photo instanceof UploadedFile) {
                    $photoPath = $this->storeVehiclePhoto($photo);
                    $attributes['vehicle_photo_path'] = $photoPath;
                }

                CarTransaction::create($attributes);
            });
        } catch (Throwable $exception) {
            if ($photoPath !== null) {
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
        ?UploadedFile $photo,
        User $user,
    ): void {
        $previousPhotoPath = $transaction->vehicle_photo_path;
        $newPhotoPath = null;

        try {
            DB::transaction(function () use (
                $transaction,
                $attributes,
                $user,
                $photo,
                &$newPhotoPath,
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

                if ($photo instanceof UploadedFile) {
                    $newPhotoPath = $this->storeVehiclePhoto($photo);
                    $attributes['vehicle_photo_path'] = $newPhotoPath;
                }

                $lockedTransaction->update($attributes);
            });
        } catch (Throwable $exception) {
            if ($newPhotoPath !== null) {
                $this->deleteVehiclePhotoIfExists($newPhotoPath, $transaction->id);
            }

            throw $exception;
        }

        if ($newPhotoPath !== null && $previousPhotoPath !== null) {
            $this->deleteVehiclePhotoIfExists($previousPhotoPath, $transaction->id);
        }
    }

    public function delete(CarTransaction $transaction): void
    {
        $photoPath = $transaction->vehicle_photo_path;

        DB::transaction(function () use ($transaction): void {
            CarTransaction::query()
                ->whereKey($transaction->id)
                ->lockForUpdate()
                ->firstOrFail()
                ->delete();
        });

        if ($photoPath !== null) {
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
