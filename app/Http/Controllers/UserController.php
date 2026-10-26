<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly UserService $userService) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
        ]);
        $search = trim($filters['q'] ?? '');

        return view('users.index', [
            'users' => User::query()
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
                ->withQueryString(),
            'search' => $search,
            'masterCount' => User::query()->where('role', 'master')->count(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->userService->createUser($request->validated());

        return redirect()->route('users.index')->with('success', 'Akun berhasil dibuat.');
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->userService->updateUser($user, $request->validated());

        return redirect()->route('users.index')->with('success', 'Akun berhasil diperbarui.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        if (! $this->userService->deleteUser($user)) {
            return redirect()->route('users.index')->withErrors([
                'user' => 'Akun tidak dapat dihapus karena merupakan akun aktif Anda atau Master terakhir.',
            ]);
        }

        return redirect()->route('users.index')->with('success', 'Akun berhasil dihapus.');
    }
}
