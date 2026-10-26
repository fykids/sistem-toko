<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveSaleNoteRequest;
use App\Models\CarTransaction;
use App\Models\SaleNote;
use App\Services\SellModuleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SaleNoteController extends Controller
{
    public function index(Request $request, SellModuleService $sellModuleService): View
    {
        Gate::authorize('viewAny', SaleNote::class);

        $canViewSensitiveData = $request->user()->can('viewSensitiveData', CarTransaction::class);
        $notes = $sellModuleService->listNotes($request->user());

        return view('sale_notes.index', compact('notes', 'canViewSensitiveData'));
    }

    public function create(Request $request, SellModuleService $sellModuleService): View
    {
        Gate::authorize('create', SaleNote::class);

        $canViewSensitiveData = $request->user()->can('viewSensitiveData', CarTransaction::class);
        $availableVehicles = $sellModuleService->availableVehicles($canViewSensitiveData);
        $selectedVehicle = $sellModuleService->findAvailableVehicle(
            $request->integer('vehicle'),
            $canViewSensitiveData,
        );

        return view('sale_notes.create', [
            'saleNote' => null,
            'availableVehicles' => $availableVehicles,
            'hasAvailableVehicles' => $sellModuleService->hasAvailableVehicles(),
            'selectedVehicle' => $selectedVehicle,
            'canViewSensitiveData' => $canViewSensitiveData,
        ]);
    }

    public function store(SaveSaleNoteRequest $request, SellModuleService $sellModuleService): RedirectResponse
    {
        $saleNote = $sellModuleService->createNote($request->validated(), $request->user());

        return redirect()->route('nota-penjualan.index')
            ->with('success', 'Nota penjualan berhasil disimpan.');
    }

    public function show(
        Request $request,
        SaleNote $saleNote,
        SellModuleService $sellModuleService,
    ): View {
        Gate::authorize('view', $saleNote);

        $canViewSensitiveData = $request->user()->can('viewSensitiveData', CarTransaction::class);
        $saleNote->load([
            'carTransaction' => fn ($query) => $query->select(
                $sellModuleService->vehicleColumns($canViewSensitiveData),
            ),
            'creator:id,name',
        ]);

        return view('sale_notes.show', compact('saleNote', 'canViewSensitiveData'));
    }

    public function edit(
        Request $request,
        SaleNote $saleNote,
        SellModuleService $sellModuleService,
    ): View {
        Gate::authorize('update', $saleNote);

        $canViewSensitiveData = $request->user()->can('viewSensitiveData', CarTransaction::class);
        $saleNote->load([
            'carTransaction' => fn ($query) => $query->select(
                $sellModuleService->vehicleColumns($canViewSensitiveData),
            ),
        ]);

        return view('sale_notes.create', [
            'saleNote' => $saleNote,
            'availableVehicles' => collect(),
            'selectedVehicle' => $saleNote->carTransaction,
            'canViewSensitiveData' => $canViewSensitiveData,
        ]);
    }

    public function update(
        SaveSaleNoteRequest $request,
        SaleNote $saleNote,
        SellModuleService $sellModuleService,
    ): RedirectResponse {
        $sellModuleService->updateNote($saleNote, $request->validated());

        return redirect()->route('nota-penjualan.show', $saleNote)
            ->with('success', 'Nota penjualan berhasil diperbarui.');
    }
}
