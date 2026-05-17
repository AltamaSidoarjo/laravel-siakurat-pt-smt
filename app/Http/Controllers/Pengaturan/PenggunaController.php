<?php

namespace App\Http\Controllers\Pengaturan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pengaturan\StoreUserRequest;
use App\Http\Requests\Pengaturan\UpdateUserRequest;
use App\Models\User;
use App\Services\Pengaturan\UserManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PenggunaController extends Controller
{
    public function __construct(
        private readonly UserManagementService $userManagementService,
    ) {
    }

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
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->userManagementService->update($user, $request->validated());

        return redirect()
            ->route('pengaturan.pengguna.index')
            ->with('success', 'Pengguna berhasil diperbarui.');
    }
}
