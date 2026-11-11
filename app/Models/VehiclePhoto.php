<?php

namespace App\Models;

use Database\Factories\VehiclePhotoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehiclePhoto extends Model
{
    /** @use HasFactory<VehiclePhotoFactory> */
    use HasFactory;

    protected $fillable = [
        'car_transaction_id',
        'path',
        'is_cover',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'is_cover' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function carTransaction(): BelongsTo
    {
        return $this->belongsTo(CarTransaction::class);
    }
}
