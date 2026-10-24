<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSellRequest;
use App\Models\CarTransaction;
use App\Models\SaleNote;
use App\Services\SellModuleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SellModuleController extends Controller
{
    public function __construct(private SellModuleService $sellModuleService) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', SaleNote::class);
        $canViewSensitiveData = $request->user()->can('viewSensitiveData', CarTransaction::class);

        return view('jual.index', [
            'notes' => $this->sellModuleService->listNotes($canViewSensitiveData),
            'canViewSensitiveData' => $canViewSensitiveData,
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', SaleNote::class);

        $canViewSensitiveData = $request->user()->can('viewSensitiveData', CarTransaction::class);
        $availableVehicles = $this->sellModuleService->availableVehicles($canViewSensitiveData);

        return view('jual.create', [
            'availableVehicles' => $availableVehicles,
            'selectedVehicle' => $availableVehicles->firstWhere('id', $request->integer('vehicle')),
            'saleNote' => new SaleNote,
            'isEditing' => false,
            'canViewSensitiveData' => $canViewSensitiveData,
        ]);
    }

    public function store(StoreSellRequest $request): RedirectResponse
    {
        $saleNote = $this->sellModuleService->createNote($request->validated(), $request->user());

        return redirect()->route('jual.show', $saleNote)
            ->with('success', 'Data Jual berhasil disimpan.');
    }

    public function show(Request $request, SaleNote $buyer): View
    {
        Gate::authorize('view', $buyer);

        $canViewSensitiveData = $request->user()->can('viewSensitiveData', CarTransaction::class);
        $buyer->load([
            'carTransaction' => fn ($query) => $query->select(
                $this->sellModuleService->vehicleColumns($canViewSensitiveData),
            ),
        ]);

        return view('jual.show', [
            'saleNote' => $buyer,
            'canViewSensitiveData' => $canViewSensitiveData,
        ]);
    }

    public function edit(Request $request, SaleNote $buyer): View
    {
        Gate::authorize('update', $buyer);

        $canViewSensitiveData = $request->user()->can('viewSensitiveData', CarTransaction::class);
        $selectedVehicle = CarTransaction::query()
            ->select($this->sellModuleService->vehicleColumns($canViewSensitiveData))
            ->findOrFail($buyer->car_transaction_id);

        return view('jual.create', [
            'availableVehicles' => collect(),
            'selectedVehicle' => $selectedVehicle,
            'saleNote' => $buyer,
            'isEditing' => true,
            'canViewSensitiveData' => $canViewSensitiveData,
        ]);
    }

    public function update(StoreSellRequest $request, SaleNote $buyer): RedirectResponse
    {
        $saleNote = $this->sellModuleService->updateNote($buyer, $request->validated());

        return redirect()->route('jual.show', $saleNote)
            ->with('success', 'Data Jual berhasil diperbarui.');
    }

    public function destroy(SaleNote $buyer): RedirectResponse
    {
        Gate::authorize('delete', $buyer);
        $this->sellModuleService->deleteNote($buyer);

        return redirect()->route('jual.index')
            ->with('success', 'Data Jual berhasil dihapus.');
    }
}
