<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCarTransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Models\CarTransaction;
use App\Presenters\TransactionPresenter;
use App\Services\CarTransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;

class TransactionController extends Controller
{
    public function show(
        Request $request,
        CarTransaction $transaction,
        TransactionPresenter $presenter,
    ): TransactionResource {
        Gate::authorize('view', $transaction);
        $transaction->load('vehiclePhotos');

        $presentedTransaction = $presenter->transactionDetail($transaction, $request->user());
        $presentedTransaction->loadMissing('vehiclePhotos');

        return new TransactionResource($presentedTransaction);
    }

    public function store(
        StoreCarTransactionRequest $request,
        CarTransactionService $service,
    ): JsonResponse {
        $validated = $request->validated();
        $this->persist($request, $validated, $service);

        return response()->json([
            'message' => 'Data transaksi berhasil disimpan.',
        ], 201);
    }

    public function update(
        StoreCarTransactionRequest $request,
        CarTransaction $transaction,
        CarTransactionService $service,
    ): JsonResponse {
        $validated = $request->validated();
        $photos = $this->uploadedPhotos($request);
        $coverPhoto = $validated['foto_utama']
            ?? ($request->hasFile('vehicle_photo') ? 'new:'.count($request->file('foto_kendaraan', [])) : null);
        $removedPhotoIds = $validated['hapus_foto'] ?? [];
        $removeLegacyPhoto = (bool) ($validated['hapus_foto_legacy'] ?? false);

        if ($request->hasFile('vehicle_photo') && $request->file('foto_kendaraan') === null) {
            $removedPhotoIds = $transaction->vehiclePhotos()->pluck('id')->all();
            $removeLegacyPhoto = true;
        }

        unset(
            $validated['foto_kendaraan'],
            $validated['foto_utama'],
            $validated['hapus_foto'],
            $validated['hapus_foto_legacy'],
            $validated['vehicle_photo'],
        );
        $this->mapSellerDetails($validated);

        $service->update(
            $transaction,
            $validated,
            $photos,
            $coverPhoto,
            $removedPhotoIds,
            $removeLegacyPhoto,
            $request->user(),
        );

        return response()->json(['message' => 'Data transaksi berhasil diperbarui.']);
    }

    public function destroy(
        CarTransaction $transaction,
        CarTransactionService $service,
    ): JsonResponse {
        Gate::authorize('delete', $transaction);
        $service->delete($transaction);

        return response()->json(['message' => 'Data transaksi berhasil dihapus.']);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function persist(
        StoreCarTransactionRequest $request,
        array $validated,
        CarTransactionService $service,
    ): void {
        $photos = $this->uploadedPhotos($request);
        $coverPhoto = $validated['foto_utama']
            ?? ($request->hasFile('vehicle_photo') ? 'new:'.count($request->file('foto_kendaraan', [])) : null);

        unset(
            $validated['foto_kendaraan'],
            $validated['foto_utama'],
            $validated['hapus_foto'],
            $validated['hapus_foto_legacy'],
            $validated['vehicle_photo'],
        );
        $this->mapSellerDetails($validated);

        $service->create($validated, $photos, $coverPhoto, $request->user());
    }

    /**
     * @return array<int, UploadedFile>
     */
    private function uploadedPhotos(Request $request): array
    {
        $photos = $request->file('foto_kendaraan', []);
        $photos = is_array($photos) ? array_values($photos) : [];
        $legacyPhoto = $request->file('vehicle_photo');

        if ($legacyPhoto instanceof UploadedFile) {
            $photos[] = $legacyPhoto;
        }

        return $photos;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function mapSellerDetails(array &$attributes): void
    {
        foreach ([
            'nama_penjual' => 'seller_name',
            'nik_penjual' => 'seller_nik',
            'no_hp_penjual' => 'seller_phone',
            'alamat_penjual' => 'seller_address',
            'nama_broker' => 'broker_name',
        ] as $requestField => $column) {
            $attributes[$column] = $attributes[$requestField] ?? null;
            unset($attributes[$requestField]);
        }
    }
}
