<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveSaleNoteRequest;
use App\Models\CarTransaction;
use App\Models\SaleNote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SaleNoteController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', SaleNote::class);

        $canViewSensitiveData = $request->user()->can('viewSensitiveData', CarTransaction::class);
        $notes = SaleNote::query()
            ->with([
                'carTransaction' => fn ($query) => $query->select($this->vehicleColumns($canViewSensitiveData)),
            ])
            ->orderByDesc('sell_date')
            ->orderByDesc('id')
            ->paginate(20);

        return view('sale_notes.index', compact('notes', 'canViewSensitiveData'));
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', SaleNote::class);

        $canViewSensitiveData = $request->user()->can('viewSensitiveData', CarTransaction::class);
        $availableVehicles = CarTransaction::query()
            ->whereDoesntHave('saleNote')
            ->orderBy('plate_number', 'asc')
            ->get($this->vehicleColumns($canViewSensitiveData));

        return view('sale_notes.create', [
            'saleNote' => null,
            'availableVehicles' => $availableVehicles,
            'selectedVehicle' => $availableVehicles->firstWhere('id', $request->integer('vehicle')),
            'canViewSensitiveData' => $canViewSensitiveData,
        ]);
    }

    public function store(SaveSaleNoteRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $vehicleId = $validated['car_transaction_id'];
        unset($validated['car_transaction_id']);

        $saleNote = DB::transaction(function () use ($request, $validated, $vehicleId): SaleNote {
            $vehicle = CarTransaction::query()
                ->whereKey($vehicleId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($vehicle->saleNote()->exists()) {
                throw ValidationException::withMessages([
                    'car_transaction_id' => 'Nota penjualan untuk kendaraan ini sudah tercatat.',
                ]);
            }

            return $vehicle->saleNote()->create([
                ...$validated,
                'created_by' => $request->user()->id,
            ]);
        });

        return redirect()->route('nota-penjualan.index')
            ->with('success', 'Nota penjualan berhasil disimpan.');
    }

    public function show(Request $request, SaleNote $saleNote): View
    {
        Gate::authorize('view', $saleNote);

        $canViewSensitiveData = $request->user()->can('viewSensitiveData', CarTransaction::class);
        $saleNote->load([
            'carTransaction' => fn ($query) => $query->select($this->vehicleColumns($canViewSensitiveData)),
            'creator:id,name',
        ]);

        return view('sale_notes.show', compact('saleNote', 'canViewSensitiveData'));
    }

    public function edit(Request $request, SaleNote $saleNote): View
    {
        Gate::authorize('update', $saleNote);

        $canViewSensitiveData = $request->user()->can('viewSensitiveData', CarTransaction::class);
        $saleNote->load([
            'carTransaction' => fn ($query) => $query->select($this->vehicleColumns($canViewSensitiveData)),
        ]);

        return view('sale_notes.create', [
            'saleNote' => $saleNote,
            'availableVehicles' => collect(),
            'selectedVehicle' => $saleNote->carTransaction,
            'canViewSensitiveData' => $canViewSensitiveData,
        ]);
    }

    public function update(SaveSaleNoteRequest $request, SaleNote $saleNote): RedirectResponse
    {
        $saleNote->update($request->validated());

        return redirect()->route('nota-penjualan.show', $saleNote)
            ->with('success', 'Nota penjualan berhasil diperbarui.');
    }

    /**
     * @return array<int, string>
     */
    private function vehicleColumns(bool $canViewSensitiveData): array
    {
        $columns = ['id', 'plate_number', 'brand', 'model', 'year', 'transmission', 'color'];

        if ($canViewSensitiveData) {
            $columns = [
                ...$columns,
                'buy_price',
                'total_capital',
                'setor_price',
                'leasing_ripan',
                'insurance_ripan',
                'booster_ripan',
            ];
        }

        return $columns;
    }
}
