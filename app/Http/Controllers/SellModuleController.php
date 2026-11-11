<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSellRequest;
use App\Models\SaleNote;
use App\Services\SellModuleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SellModuleController extends Controller
{
    public function __construct(private SellModuleService $sellModuleService) {}

    public function index(Request $request): RedirectResponse
    {
        Gate::authorize('viewAny', SaleNote::class);
        $filters = $request->validate([
            'status' => ['nullable', 'in:all,unpaid,leasing,paid'],
        ]);

        $query = ($filters['status'] ?? 'all') === 'all'
            ? ''
            : '?status='.urlencode($filters['status']);

        return redirect('/app/jual'.$query);
    }

    public function create(Request $request): RedirectResponse
    {
        Gate::authorize('create', SaleNote::class);

        return redirect('/app/jual/create'.($request->getQueryString() ? '?'.$request->getQueryString() : ''));
    }

    public function store(StoreSellRequest $request): RedirectResponse
    {
        $saleNote = $this->sellModuleService->createNote($request->validated(), $request->user());

        return redirect()->route('jual.show', $saleNote)
            ->with('success', 'Data Jual berhasil disimpan.');
    }

    public function show(SaleNote $buyer): RedirectResponse
    {
        Gate::authorize('view', $buyer);

        return redirect("/app/jual/{$buyer->getKey()}");
    }

    public function edit(SaleNote $buyer): RedirectResponse
    {
        Gate::authorize('update', $buyer);

        return redirect("/app/jual/{$buyer->getKey()}/edit");
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
