<?php

namespace App\Http\Resources;

use App\Models\CarTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class TransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var CarTransaction $transaction */
        $transaction = $this->resource;

        $attributes = $transaction->attributesToArray();
        $attributes['tax_status'] = $transaction->taxStatus();
        $attributes['showroom_age_status'] = $transaction->showroomAgeStatus();
        $attributes['can_view_sensitive_data'] = $request->user()
            ? $request->user()->can('viewSensitiveData', CarTransaction::class)
            : false;
        $attributes['vehicle_photos'] = $transaction->relationLoaded('vehiclePhotos')
            ? $transaction->vehiclePhotos->map(fn ($photo): array => [
                'id' => $photo->id,
                'url' => Storage::disk('public')->url($photo->path),
                'is_cover' => (bool) $photo->is_cover,
                'position' => $photo->position,
            ])->values()
            : [];
        $attributes['vehicle_photo_url'] = $transaction->vehicle_photo_path
            ? Storage::disk('public')->url($transaction->vehicle_photo_path)
            : null;
        $attributes['legacy_photo_url'] = $transaction->vehicle_photo_path
            ? Storage::disk('public')->url($transaction->vehicle_photo_path)
            : null;

        return $attributes;
    }
}
