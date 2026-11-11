<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSalePaymentRequest;
use App\Models\SaleNote;
use App\Services\SellModuleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class SalePaymentController extends Controller
{
    public function create(SaleNote $buyer): RedirectResponse
    {
        Gate::authorize('recordPayment', $buyer);

        return redirect("/app/jual/{$buyer->getKey()}/pelunasan");
    }

    public function store(
        StoreSalePaymentRequest $request,
        SaleNote $buyer,
        SellModuleService $sellModuleService,
    ): RedirectResponse {
        $sellModuleService->recordPayment($buyer, $request->validated(), $request->user());

        return redirect()->route('jual.show', $buyer)
            ->with('success', 'Pelunasan berhasil dicatat.');
    }
}
