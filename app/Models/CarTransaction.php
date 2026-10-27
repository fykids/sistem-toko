<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CarTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'plate_number',
        'manager_name',
        'chassis_number',
        'engine_number',
        'mileage_km',
        'brand',
        'model',
        'year',
        'transmission',
        'color',
        'owner_name',
        'buy_date',
        'sell_date',
        'buy_price',
        'purchase_operational',
        'other_costs',
        'selling_price',
        'down_payment',
        'down_payment_date',
        'settlement_amount',
        'settlement_date',
        'leasing_disbursement',
        'leasing_disbursement_date',
        'planned_payment_date',
        'setor_price',
        'opening_price',
        'leasing_ripan',
        'insurance_ripan',
        'booster_ripan',
        'additional_repairs',
        'tax_items',
        'intertine_expenses',
        'leasing_options',
        'vehicle_photo_path',
    ];

    protected function casts(): array
    {
        return [
            'additional_repairs' => 'array',
            'tax_items' => 'array',
            'intertine_expenses' => 'array',
            'leasing_options' => 'array',
        ];
    }

    public function saleNote(): HasOne
    {
        return $this->hasOne(SaleNote::class, 'car_transaction_id');
    }

    /** @return array{label: string, badgeClass: string} */
    public function taxStatus(): array
    {
        $dueDate = $this->taxDueDate();

        if ($dueDate === null) {
            return [
                'label' => 'Mati',
                'badgeClass' => 'status-badge--danger',
            ];
        }

        $isActive = $dueDate->greaterThanOrEqualTo(CarbonImmutable::today());

        return [
            'label' => $isActive ? 'Hidup' : 'Mati',
            'badgeClass' => $isActive ? 'status-badge--success' : 'status-badge--danger',
        ];
    }

    /** @return array{days: int|null, badgeClass: string} */
    public function showroomAgeStatus(): array
    {
        if (! is_string($this->buy_date) || trim($this->buy_date) === '') {
            return [
                'days' => null,
                'badgeClass' => 'status-badge--neutral',
            ];
        }

        $buyDate = CarbonImmutable::parse($this->buy_date)->startOfDay();
        $today = CarbonImmutable::today();
        $days = $buyDate->greaterThan($today)
            ? 0
            : (int) $buyDate->diffInDays($today);

        return match (true) {
            $days <= 25 => ['days' => $days, 'badgeClass' => 'status-badge--success'],
            $days <= 45 => [
                'days' => $days,
                'badgeClass' => 'status-badge--warning',
            ],
            default => [
                'days' => $days,
                'badgeClass' => 'status-badge--danger',
            ],
        };
    }

    public function calculatedSaleProfit(): float
    {
        return round((float) $this->selling_price - (float) $this->total_capital, 2);
    }

    public function calculatedTotalProfit(): float
    {
        return round($this->calculatedSaleProfit()
            + (float) $this->leasing_ripan
            + (float) $this->insurance_ripan
            + (float) $this->booster_ripan, 2);
    }

    protected static function booted(): void
    {
        static::saving(static function (CarTransaction $transaction): void {
            $transaction->calculateFinancials();
        });
    }

    private function taxDueDate(): ?CarbonImmutable
    {
        $taxItems = array_values(array_filter($this->tax_items ?? [], 'is_array'));
        $primaryTaxItem = null;

        foreach ($taxItems as $taxItem) {
            if (mb_strtolower(trim((string) ($taxItem['name'] ?? ''))) === 'pajak') {
                $primaryTaxItem = $taxItem;
                break;
            }
        }

        $primaryTaxItem ??= $taxItems[0] ?? null;
        $date = $primaryTaxItem['date'] ?? null;

        return is_string($date) && trim($date) !== ''
            ? CarbonImmutable::parse($date)->startOfDay()
            : null;
    }

    private function calculateFinancials(): void
    {
        $this->total_repair_cost = round($this->sumItemCosts($this->additional_repairs), 2);
        $this->total_capital = round((float) ($this->buy_price ?? 0)
            + (float) ($this->purchase_operational ?? 0)
            + (float) ($this->other_costs ?? 0)
            + $this->sumItemCosts($this->tax_items)
            + (float) $this->total_repair_cost, 2);

        $this->remaining_payment = round(max(
            (float) ($this->selling_price ?? 0)
                - (float) ($this->down_payment ?? 0)
                - (float) ($this->settlement_amount ?? 0)
                - (float) ($this->leasing_disbursement ?? 0),
            0,
        ), 2);

        $this->gross_profit = round((float) ($this->selling_price ?? 0) - (float) $this->total_capital, 2);
        $this->showroom_fee = round((float) $this->gross_profit * 0.10, 2);
        $this->total_intertine_expense = round(
            (float) $this->showroom_fee + $this->sumItemCosts($this->intertine_expenses),
            2,
        );
        $this->net_profit = round((float) $this->gross_profit - (float) $this->total_intertine_expense, 2);
    }

    private function sumItemCosts(?array $items): float
    {
        return array_sum(array_map(
            static fn (array $item): float => (float) ($item['cost'] ?? 0),
            array_filter($items ?? [], 'is_array'),
        ));
    }
}
