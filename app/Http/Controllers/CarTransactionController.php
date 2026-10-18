<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCarTransactionRequest;
use App\Models\CarTransaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

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
        $validated = $request->validated();
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
        $photoPath = $transaction->vehicle_photo_path;
        $transaction->delete();

        if ($photoPath !== null) {
            $this->deleteVehiclePhotoIfExists($photoPath, $transaction->id);
        }

        return redirect()->route('transactions.index')->with('success', 'Data transaksi berhasil dihapus!');
    }

    public function show(CarTransaction $transaction): View
    {
        return view('transactions.show', compact('transaction'));
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
