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

    public function create(): View
    {
        return view('pengaturan.pengguna.create', [
            'page' => 'app',
            'roles' => $this->userManagementService->getRoleOptions(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->userManagementService->create($request->validated());

        return redirect()
            ->route('pengaturan.pengguna.index')
            ->with('success', 'Pengguna berhasil disimpan.');
    }

    public function edit(User $user): View
    {
        return view('pengaturan.pengguna.edit', [
            'page' => 'app',
            'user' => $user,
            'roles' => $this->userManagementService->getRoleOptions(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->userManagementService->update($user, $request->validated());

        return redirect()
            ->route('pengaturan.pengguna.index')
            ->with('success', 'Pengguna berhasil diperbarui.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if (! $this->userManagementService->delete($user, $request->user())) {
            return redirect()
                ->route('pengaturan.pengguna.index')
                ->with('error', 'Anda tidak dapat menghapus akun yang sedang digunakan.');
        }

        return redirect()
            ->route('pengaturan.pengguna.index')
            ->with('success', 'Pengguna berhasil dihapus.');
    }
}
