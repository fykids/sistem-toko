<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCarTransactionRequest;
use App\Models\CarTransaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CarTransactionController extends Controller
{
    public function index(): View
    {
        $transactions = CarTransaction::latest()->get();
        $stockCount = $transactions->whereNull('sell_date')->count();
        $soldTransactions = $transactions->whereNotNull('sell_date');
        $soldCount = $soldTransactions->count();
        $recordedCapital = $transactions->sum('total_capital');
        $soldRevenue = $soldTransactions->sum('selling_price');
        $soldCapital = $soldTransactions->sum('total_capital');
        $soldNetProfit = $soldTransactions->sum('net_profit');

        return view('transactions.index', compact(
            'transactions',
            'stockCount',
            'soldCount',
            'recordedCapital',
            'soldRevenue',
            'soldCapital',
            'soldNetProfit',
        ));
    }

    public function create(): View
    {
        return view('transactions.create');
    }

    public function edit(CarTransaction $transaction): View
    {
        return view('transactions.create', compact('transaction'));
    }

    public function store(StoreCarTransactionRequest $request): RedirectResponse
    {
        CarTransaction::create($request->validated());

        return redirect()->route('transactions.index')->with('success', 'Data transaksi berhasil disimpan!');
    }

    public function update(StoreCarTransactionRequest $request, CarTransaction $transaction): RedirectResponse
    {
        $transaction->update($request->validated());

        return redirect()->route('transactions.index')->with('success', 'Data transaksi berhasil diperbarui!');
    }

    public function destroy(CarTransaction $transaction): RedirectResponse
    {
        $transaction->delete();

        return redirect()->route('transactions.index')->with('success', 'Data transaksi berhasil dihapus!');
    }

    public function show(CarTransaction $transaction): View
    {
        return view('transactions.show', compact('transaction'));
    }
}
