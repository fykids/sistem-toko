<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCarTransactionRequest;
use App\Models\CarTransaction;
use App\Models\User;
use App\Presenters\TransactionPresenter;
use App\Services\CarTransactionService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CarTransactionController extends Controller
{
    public function index(Request $request, TransactionPresenter $presenter): View
    {
        $filters = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'q' => ['nullable', 'string', 'max:255'],
            'view' => ['nullable', 'in:dashboard,inventory,sales,reports'],
        ]);

        return view(
            'transactions.index',
            $presenter->dashboard($this->authenticatedUser($request), $filters),
        );
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', CarTransaction::class);

        return view('transactions.create', [
            'transaction' => null,
            'canViewSensitiveData' => $this->authenticatedUser($request)
                ->can('viewSensitiveData', CarTransaction::class),
        ]);
    }

    public function edit(CarTransaction $transaction, Request $request): View
    {
        Gate::authorize('update', $transaction);

        return view('transactions.create', [
            'transaction' => $transaction,
            'canViewSensitiveData' => $this->authenticatedUser($request)
                ->can('viewSensitiveData', CarTransaction::class),
        ]);
    }

    public function store(
        StoreCarTransactionRequest $request,
        CarTransactionService $carTransactionService,
    ): RedirectResponse {
        $validated = $request->validated();
        $user = $this->authenticatedUser($request);
        $photo = $request->file('vehicle_photo');
        unset($validated['vehicle_photo']);

        $carTransactionService->create($validated, $photo, $user);

        return redirect()->route('transactions.index')->with('success', 'Data transaksi berhasil disimpan!');
    }

    public function update(
        StoreCarTransactionRequest $request,
        CarTransaction $transaction,
        CarTransactionService $carTransactionService,
    ): RedirectResponse {
        $validated = $request->validated();
        $user = $this->authenticatedUser($request);
        $photo = $request->file('vehicle_photo');
        unset($validated['vehicle_photo']);

        $carTransactionService->update($transaction, $validated, $photo, $user);

        return redirect()->route('transactions.index')->with('success', 'Data transaksi berhasil diperbarui!');
    }

    public function destroy(
        CarTransaction $transaction,
        CarTransactionService $carTransactionService,
    ): RedirectResponse {
        Gate::authorize('delete', $transaction);

        $carTransactionService->delete($transaction);

        return redirect()->route('transactions.index')->with('success', 'Data transaksi berhasil dihapus!');
    }

    public function show(
        CarTransaction $transaction,
        Request $request,
        TransactionPresenter $presenter,
    ): View {
        Gate::authorize('view', $transaction);

        return view('transactions.show', [
            'transaction' => $presenter->transactionDetail(
                $transaction,
                $this->authenticatedUser($request),
            ),
        ]);
    }

    private function authenticatedUser(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return $user;
    }
}
