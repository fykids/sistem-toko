<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSalePaymentRequest;
use App\Models\SaleNote;
use App\Services\SellModuleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SalePaymentController extends Controller
{
    public function create(SaleNote $buyer): View|RedirectResponse
    {
        Gate::authorize('recordPayment', $buyer);
        $buyer->load([
            'carTransaction',
            'payments' => fn ($query) => $query->orderBy('payment_date')->orderBy('id'),
        ]);

        if ((float) $buyer->carTransaction->remaining_payment <= 0) {
            return redirect()->route('jual.show', $buyer)
                ->with('info', 'Transaksi ini sudah lunas.');
        }

        return view('jual.payments.create', [
            'saleNote' => $buyer,
        ]);
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
