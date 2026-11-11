<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', User::class);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
        ]);
        $search = trim($filters['q'] ?? '');
        $users = User::query()
            ->when($search !== '', function ($query) use ($search): void {
                $searchPattern = '%'.$search.'%';
                $query->where(function ($query) use ($searchPattern): void {
                    $query->where('name', 'like', $searchPattern)
                        ->orWhere('username', 'like', $searchPattern)
                        ->orWhere('role', 'like', $searchPattern);
                });
            })
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(10)
            ->withQueryString();

        return UserResource::collection($users)
            ->additional(['summary' => [
                'search' => $search,
                'master_count' => User::query()->where('role', 'master')->count(),
            ]]);
    }

    public function store(StoreUserRequest $request, UserService $userService): JsonResponse
    {
        $user = $userService->createUser($request->validated());

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    public function update(
        UpdateUserRequest $request,
        User $user,
        UserService $userService,
    ): JsonResponse {
        return (new UserResource($userService->updateUser($user, $request->validated())))->response();
    }

    public function destroy(User $user, UserService $userService): Response
    {
        Gate::authorize('delete', $user);

        if (! $userService->deleteUser($user)) {
            abort(422, 'Akun tidak dapat dihapus karena merupakan akun aktif Anda atau Master terakhir.');
        }

        return response()->noContent();
    }
}
