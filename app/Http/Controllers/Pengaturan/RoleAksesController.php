<?php

namespace App\Http\Controllers\Pengaturan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pengaturan\StoreRoleAccessRequest;
use App\Http\Requests\Pengaturan\UpdateRoleAccessRequest;
use App\Models\Role;
use App\Services\Pengaturan\RoleAccessManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RoleAksesController extends Controller
{
    public function __construct(
        private readonly RoleAccessManagementService $roleAccessManagementService,
    ) {
    }

    public function index(): View
    {
        return view('pengaturan.role-akses.index', [
            'page' => 'app',
            'roles' => $this->roleAccessManagementService->getAll(),
        ]);
    }

    public function create(): View
    {
        return view('pengaturan.role-akses.create', [
            'page' => 'app',
            'role' => null,
            'moduleGroups' => $this->roleAccessManagementService->getModulesGrouped(),
            'permissionMatrix' => [],
        ]);
    }

    public function store(StoreRoleAccessRequest $request): RedirectResponse
    {
        $this->roleAccessManagementService->create($request->validated());

        return redirect()
            ->route('pengaturan.role-akses.index')
            ->with('success', 'Role akses berhasil disimpan.');
    }

    public function edit(Role $role): View
    {
        return view('pengaturan.role-akses.edit', [
            'page' => 'app',
            'role' => $role,
            'moduleGroups' => $this->roleAccessManagementService->getModulesGrouped(),
            'permissionMatrix' => $this->roleAccessManagementService->getPermissionMatrix($role),
        ]);
    }

    public function update(UpdateRoleAccessRequest $request, Role $role): RedirectResponse
    {
        if ($role->is_system) {
            return redirect()
                ->route('pengaturan.role-akses.index')
                ->with('error', 'Role sistem tidak dapat diubah.');
        }

        $this->roleAccessManagementService->update($role, $request->validated());

        return redirect()
            ->route('pengaturan.role-akses.index')
            ->with('success', 'Role akses berhasil diperbarui.');
    }
}
