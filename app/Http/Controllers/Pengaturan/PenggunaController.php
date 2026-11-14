<?php

namespace App\Http\Controllers\Pengaturan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pengaturan\StoreUserRequest;
use App\Http\Requests\Pengaturan\UpdateUserRequest;
use App\Models\User;
use App\Services\Pengaturan\UserManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PenggunaController extends Controller
{
    public function __construct(
        private readonly UserManagementService $userManagementService,
    ) {}

    public function index(): View
    {
        return view('pengaturan.pengguna.index', [
            'page' => 'app',
            'users' => $this->userManagementService->getAll(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('pengaturan.pengguna.create', [
            'page' => 'app',
            'roles' => $this->userManagementService->getRoleOptions($request->user()),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->userManagementService->create($request->validated());

        return redirect()
            ->route('pengaturan.pengguna.index')
            ->with('success', 'Pengguna berhasil disimpan.');
    }

    public function edit(Request $request, User $user): View
    {
        if ($user->role?->is_system && ! (bool) $request->user()?->role?->is_system) {
            abort(403, 'Anda tidak memiliki hak untuk mengedit pengguna dengan role sistem.');
        }

        return view('pengaturan.pengguna.edit', [
            'page' => 'app',
            'user' => $user,
            'roles' => $this->userManagementService->getRoleOptions($request->user()),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        if ($user->role?->is_system && ! (bool) $request->user()?->role?->is_system) {
            abort(403, 'Anda tidak memiliki hak untuk mengubah pengguna dengan role sistem.');
        }

        $this->userManagementService->update($user, $request->validated());

        return redirect()
            ->route('pengaturan.pengguna.index')
            ->with('success', 'Pengguna berhasil diperbarui.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return redirect()
                ->route('pengaturan.pengguna.index')
                ->with('error', 'Anda tidak dapat menghapus akun yang sedang digunakan.');
        }

        if ($user->role?->is_system) {
            return redirect()
                ->route('pengaturan.pengguna.index')
                ->with('error', 'Pengguna dengan role sistem tidak dapat dihapus.');
        }

        if (! $this->userManagementService->delete($user, $request->user())) {
            return redirect()
                ->route('pengaturan.pengguna.index')
                ->with('error', 'Pengguna tidak dapat dihapus.');
        }

        return redirect()
            ->route('pengaturan.pengguna.index')
            ->with('success', 'Pengguna berhasil dihapus.');
    }
}
