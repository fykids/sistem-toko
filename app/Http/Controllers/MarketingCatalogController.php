<?php

namespace App\Http\Controllers;

use App\Http\Requests\MarketingCatalogRequest;
use App\Services\MarketingCatalogService;
use Illuminate\View\View;

class MarketingCatalogController extends Controller
{
    public function index(
        MarketingCatalogRequest $request,
        MarketingCatalogService $catalogService,
    ): View {
        $filters = $request->validated();

        return view('marketing.stock.index', [
            'vehicles' => $catalogService->stock($filters),
            'filters' => $filters,
        ]);
    }

    public function show(int $transaction, MarketingCatalogService $catalogService): View
    {
        return view('marketing.stock.show', [
            'vehicle' => $catalogService->availableVehicle($transaction),
        ]);
    }
}
