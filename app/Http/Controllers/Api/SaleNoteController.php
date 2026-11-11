<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SaleNoteResource;
use App\Models\CarTransaction;
use App\Models\SaleNote;
use App\Services\SellModuleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class SaleNoteController extends Controller
{
    public function index(Request $request, SellModuleService $service): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', SaleNote::class);
        $canViewSensitiveData = $request->user()->can('viewSensitiveData', CarTransaction::class);

        return SaleNoteResource::collection($service->listNotes($request->user()))
            ->additional(['can_view_sensitive_data' => $canViewSensitiveData]);
    }

    public function show(Request $request, SaleNote $saleNote, SellModuleService $service): SaleNoteResource
    {
        Gate::authorize('view', $saleNote);
        $saleNote->load([
            'carTransaction' => fn ($query) => $query->select(
                $service->vehicleColumns($request->user()->can('viewSensitiveData', CarTransaction::class)),
            ),
            'payments' => fn ($query) => $query->orderBy('payment_date')->orderBy('id'),
        ]);

        return new SaleNoteResource($saleNote);
    }

    public function update(
        SaveSaleNoteRequest $request,
        SaleNote $saleNote,
        SellModuleService $service,
    ): JsonResponse {
        $service->updateNote($saleNote, $request->validated());

        return response()->json(['message' => 'Nota penjualan berhasil diperbarui.']);
    }
}
