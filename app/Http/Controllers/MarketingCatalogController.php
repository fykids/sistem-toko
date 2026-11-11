<?php

namespace App\Http\Controllers;

use App\Http\Requests\MarketingCatalogRequest;
use App\Services\MarketingCatalogService;
use Illuminate\Http\RedirectResponse;

class MarketingCatalogController extends Controller
{
    public function index(MarketingCatalogRequest $request): RedirectResponse
    {
        $request->validated();

        return redirect()->to('/app/marketing'.($request->getQueryString() ? '?'.$request->getQueryString() : ''));
    }

    public function show(int $transaction, MarketingCatalogService $catalogService): RedirectResponse
    {
        $catalogService->availableVehicle($transaction);

        return redirect('/app/marketing/'.$transaction);
    }
}
