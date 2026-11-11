<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MarketingCatalogRequest;
use App\Http\Resources\MarketingVehicleResource;
use App\Services\MarketingCatalogService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MarketingCatalogController extends Controller
{
    public function index(
        MarketingCatalogRequest $request,
        MarketingCatalogService $catalogService,
    ): AnonymousResourceCollection {
        $filters = $request->validated();
        $vehicles = $catalogService->stock($filters);

        return MarketingVehicleResource::collection($vehicles)
            ->additional([
                'filters' => $filters,
                'summary' => ['available_count' => $vehicles->total()],
            ]);
    }

    public function show(int $transaction, MarketingCatalogService $catalogService): MarketingVehicleResource
    {
        return new MarketingVehicleResource($catalogService->availableVehicle($transaction));
    }
}
