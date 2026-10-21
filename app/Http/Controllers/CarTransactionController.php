<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCarTransactionRequest;
use App\Models\CarTransaction;
use App\Models\User;
use App\Presenters\TransactionPresenter;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class CarTransactionController extends Controller
{
    public function index(Request $request, TransactionPresenter $presenter): View
    {
        return view(
            'transactions.index',
            $presenter->dashboard($this->authenticatedUser($request)),
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

    public function store(StoreCarTransactionRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = $this->authenticatedUser($request);

        if ($user->cannot('viewSensitiveData', CarTransaction::class)) {
            $validated = array_replace($validated, [
                'buy_price' => 0,
                'leasing_ripan' => 0,
                'insurance_ripan' => 0,
                'booster_ripan' => 0,
            ]);
        }

        $photo = $request->file('vehicle_photo');
        unset($validated['vehicle_photo']);

        $photoPath = null;

        try {
            DB::transaction(function () use ($validated, $photo, &$photoPath): void {
                if ($photo instanceof UploadedFile) {
                    $photoPath = $this->storeVehiclePhoto($photo);
                    $validated['vehicle_photo_path'] = $photoPath;
                }

                CarTransaction::create($validated);
            });
        } catch (Throwable $exception) {
            if ($photoPath !== null) {
                $this->deleteVehiclePhotoIfExists($photoPath, null);
            }

            throw $exception;
        }

        return redirect()->route('transactions.index')->with('success', 'Data transaksi berhasil disimpan!');
    }

    public function update(StoreCarTransactionRequest $request, CarTransaction $transaction): RedirectResponse
    {
        $validated = $request->validated();
        $photo = $request->file('vehicle_photo');
        $previousPhotoPath = $transaction->vehicle_photo_path;
        unset($validated['vehicle_photo']);

        $newPhotoPath = null;

        try {
            DB::transaction(function () use ($transaction, $validated, $photo, &$newPhotoPath): void {
                if ($photo instanceof UploadedFile) {
                    $newPhotoPath = $this->storeVehiclePhoto($photo);
                    $validated['vehicle_photo_path'] = $newPhotoPath;
                }

                $transaction->update($validated);
            });
        } catch (Throwable $exception) {
            if ($newPhotoPath !== null) {
                $this->deleteVehiclePhotoIfExists($newPhotoPath, $transaction->id);
            }

            throw $exception;
        }

        if ($newPhotoPath !== null && $previousPhotoPath !== null) {
            $this->deleteVehiclePhotoIfExists($previousPhotoPath, $transaction->id);
        }

        return redirect()->route('transactions.index')->with('success', 'Data transaksi berhasil diperbarui!');
    }

    public function destroy(CarTransaction $transaction): RedirectResponse
    {
        Gate::authorize('delete', $transaction);

        $photoPath = $transaction->vehicle_photo_path;
        $transaction->delete();

        if ($photoPath !== null) {
            $this->deleteVehiclePhotoIfExists($photoPath, $transaction->id);
        }

        return redirect()->route('transactions.index')->with('success', 'Data transaksi berhasil dihapus!');
    }

    public function show(
        CarTransaction $transaction,
        Request $request,
        TransactionPresenter $presenter,
    ): View {
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

    private function storeVehiclePhoto(UploadedFile $photo): string
    {
        $path = $photo->store('vehicle-photos', 'public');

        if (! is_string($path)) {
            throw new \RuntimeException('The vehicle photo could not be stored.');
        }

        return $path;
    }

    private function deleteVehiclePhotoIfExists(string $path, ?int $transactionId): void
    {
        $disk = Storage::disk('public');

        if ($disk->exists($path) && ! $disk->delete($path)) {
            Log::error('A vehicle photo could not be deleted.', [
                'path' => $path,
                'transaction_id' => $transactionId,
            ]);
        }
    }
}
