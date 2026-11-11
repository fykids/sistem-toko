<?php

namespace App\Http\Resources;

use App\Models\CarTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class MarketingVehicleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var CarTransaction $vehicle */
        $vehicle = $this->resource;

        return [
            'id' => $vehicle->id,
            'plate_number' => $vehicle->plate_number,
            'brand' => $vehicle->brand,
            'model' => $vehicle->model,
            'year' => $vehicle->year,
            'transmission' => $vehicle->transmission,
            'color' => $vehicle->color,
            'opening_price' => $vehicle->opening_price,
            'vehicle_photo_url' => $vehicle->vehicle_photo_path
                ? Storage::disk('public')->url($vehicle->vehicle_photo_path)
                : null,
            'tax_status' => $vehicle->taxStatus(),
            'buy_date' => $vehicle->buy_date,
            'chassis_number' => $vehicle->chassis_number,
            'engine_number' => $vehicle->engine_number,
            'mileage_km' => $vehicle->mileage_km,
            'owner_name' => $vehicle->owner_name,
            'leasing_options' => array_values(array_filter(
                $vehicle->leasing_options ?? [],
                'is_array',
            )),
        ];
    }
}
