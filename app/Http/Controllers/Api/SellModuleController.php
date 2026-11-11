<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSalePaymentRequest;
use App\Http\Requests\StoreSellRequest;
use App\Http\Resources\SaleNoteResource;
use App\Models\CarTransaction;
use App\Models\SaleNote;
use App\Services\SellModuleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SellModuleController extends Controller
{
    public function index(Request $request, SellModuleService $service): JsonResponse
    {
        Gate::authorize('viewAny', SaleNote::class);
        $filters = $request->validate([
            'status' => ['nullable', 'in:all,unpaid,leasing,paid'],
        ]);
        $notes = $service->listNotes($request->user(), $filters['status'] ?? 'all');

        return SaleNoteResource::collection($notes)
            ->additional([
                'can_view_sensitive_data' => $request->user()->can('viewSensitiveData', CarTransaction::class),
                'status' => $filters['status'] ?? 'all',
            ])
            ->response();
    }

    public function create(Request $request, SellModuleService $service): JsonResponse
    {
        Gate::authorize('create', SaleNote::class);
        $canViewSensitiveData = $request->user()->can('viewSensitiveData', CarTransaction::class);
        $vehicles = $service->availableVehicles()
            ->map(fn (CarTransaction $vehicle): array => $vehicle->attributesToArray())
            ->values();

        return response()->json([
            'data' => [
                'vehicles' => $vehicles,
                'selected_vehicle' => $service->findAvailableVehicle(
                    $request->integer('vehicle'),
                )?->attributesToArray(),
                'has_available_vehicles' => $service->hasAvailableVehicles(),
                'can_view_sensitive_data' => $canViewSensitiveData,
            ],
        ]);
    }

    public function store(StoreSellRequest $request, SellModuleService $service): JsonResponse
    {
        $saleNote = $service->createNote($request->validated(), $request->user());

        return response()->json([
            'message' => 'Data Jual berhasil disimpan.',
            'data' => ['id' => $saleNote->id],
        ], 201);
    }

    public function show(
        Request $request,
        SaleNote $buyer,
        SellModuleService $service,
    ): SaleNoteResource {
        Gate::authorize('view', $buyer);
        $canViewSensitiveData = $request->user()->can('viewSensitiveData', CarTransaction::class);
        $buyer->load([
            'carTransaction' => fn ($query) => $query->select(
                $service->vehicleColumns($canViewSensitiveData),
            ),
            'payments' => fn ($query) => $query->orderBy('payment_date')->orderBy('id'),
        ]);

        return new SaleNoteResource($buyer);
    }

    public function update(StoreSellRequest $request, SaleNote $buyer, SellModuleService $service): JsonResponse
    {
        $saleNote = $service->updateNote($buyer, $request->validated());

        return response()->json([
            'message' => 'Data Jual berhasil diperbarui.',
            'data' => ['id' => $saleNote->id],
        ]);
    }

    public function destroy(SaleNote $buyer, SellModuleService $service): JsonResponse
    {
        Gate::authorize('delete', $buyer);
        $service->deleteNote($buyer);

        return response()->json(['message' => 'Data Jual berhasil dihapus.']);
    }

    public function storePayment(
        StoreSalePaymentRequest $request,
        SaleNote $buyer,
        SellModuleService $service,
    ): JsonResponse {
        $service->recordPayment($buyer, $request->validated(), $request->user());

        return response()->json(['message' => 'Pelunasan berhasil dicatat.'], 201);
    }
}
