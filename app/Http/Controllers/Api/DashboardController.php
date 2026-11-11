<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DashboardTransactionResource;
use App\Models\User;
use App\Presenters\TransactionPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DashboardController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        Request $request,
        TransactionPresenter $presenter,
    ): AnonymousResourceCollection {
        $filters = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'q' => ['nullable', 'string', 'max:255'],
            'view' => ['nullable', 'in:dashboard,inventory,sales,reports'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        $dashboard = $presenter->dashboard($user, $filters);

        return DashboardTransactionResource::collection($dashboard['transactions'])
            ->additional([
                'filters' => [
                    'view' => $dashboard['view'],
                    'search' => $dashboard['search'],
                    'start_date' => $dashboard['startDate'],
                    'end_date' => $dashboard['endDate'],
                ],
                'permissions' => [
                    'can_view_sensitive_data' => $dashboard['canViewSensitiveData'],
                ],
            ]);
    }
}
