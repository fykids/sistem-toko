<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCarTransactionRequest;
use App\Models\CarTransaction;
use App\Models\User;
use App\Services\CarTransactionService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;

class CarTransactionController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        return redirect('/app/dashboard'.($request->getQueryString() ? '?'.$request->getQueryString() : ''));
    }

    public function create(Request $request): RedirectResponse
    {
        Gate::authorize('create', CarTransaction::class);

        return redirect('/app/transactions/create'.($request->getQueryString() ? '?'.$request->getQueryString() : ''));
    }

    public function edit(CarTransaction $transaction): RedirectResponse
    {
        Gate::authorize('update', $transaction);

        return redirect("/app/transactions/{$transaction->getKey()}/edit");
    }

    public function store(
        StoreCarTransactionRequest $request,
        CarTransactionService $carTransactionService,
    ): RedirectResponse {
        $validated = $request->validated();
        $user = $this->authenticatedUser($request);
        $photos = $this->uploadedVehiclePhotos($request);
        $coverPhoto = $validated['foto_utama']
            ?? ($request->hasFile('vehicle_photo') ? 'new:'.count($request->file('foto_kendaraan', [])) : null);
        unset(
            $validated['foto_kendaraan'],
            $validated['foto_utama'],
            $validated['hapus_foto'],
            $validated['hapus_foto_legacy'],
            $validated['vehicle_photo'],
        );
        $validated = $this->mapSellerDetails($validated);

        $carTransactionService->create($validated, $photos, $coverPhoto, $user);

        return redirect()->route('transactions.index')->with('success', 'Data transaksi berhasil disimpan!');
    }

    public function update(
        StoreCarTransactionRequest $request,
        CarTransaction $transaction,
        CarTransactionService $carTransactionService,
    ): RedirectResponse {
        $validated = $request->validated();
        $user = $this->authenticatedUser($request);
        $photos = $this->uploadedVehiclePhotos($request);
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
        $validated = $this->mapSellerDetails($validated);

        $carTransactionService->update(
            $transaction,
            $validated,
            $photos,
            $coverPhoto,
            $removedPhotoIds,
            $removeLegacyPhoto,
            $user,
        );

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
    ): RedirectResponse {
        Gate::authorize('view', $transaction);

        return redirect("/app/transactions/{$transaction->getKey()}");
    }

    private function authenticatedUser(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return $user;
    }

    /**
     * @return array<int, UploadedFile>
     */
    private function uploadedVehiclePhotos(Request $request): array
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
     * @return array<string, mixed>
     */
    private function mapSellerDetails(array $attributes): array
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

        return $attributes;
    }
}
