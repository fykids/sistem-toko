<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveSaleNoteRequest;
use App\Models\SaleNote;
use App\Services\SellModuleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class SaleNoteController extends Controller
{
    public function index(): RedirectResponse
    {
        Gate::authorize('viewAny', SaleNote::class);

        return redirect('/app/nota-penjualan');
    }

    public function show(
        SaleNote $saleNote,
    ): RedirectResponse {
        Gate::authorize('view', $saleNote);

        return redirect("/app/nota-penjualan/{$saleNote->getKey()}");
    }

    public function edit(
        SaleNote $saleNote,
    ): RedirectResponse {
        Gate::authorize('update', $saleNote);

        return redirect("/app/nota-penjualan/{$saleNote->getKey()}/edit");
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
