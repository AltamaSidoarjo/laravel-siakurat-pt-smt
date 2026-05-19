<div class="d-flex flex-column gap-3">
    <div class="card border-light shadow-sm">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="nama" class="form-label">Nama Role</label>
                    <input type="text" name="nama" id="nama" class="form-control" value="{{ old('nama', $role->nama ?? '') }}" required>
                </div>
                <div class="col-md-6">
                    <label for="kode" class="form-label">Kode Role</label>
                    <input type="text" name="kode" id="kode" class="form-control" value="{{ old('kode', $role->kode ?? '') }}" {{ ($role->is_system ?? false) ? 'readonly' : '' }} required>
                </div>
                <div class="col-12">
                    <label for="deskripsi" class="form-label">Deskripsi</label>
                    <textarea name="deskripsi" id="deskripsi" class="form-control" rows="3">{{ old('deskripsi', $role->deskripsi ?? '') }}</textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-light shadow-sm">
        <div class="card-body">
            <h5 class="fw-bold mb-3">Hak Akses Modul</h5>
            <div class="d-flex flex-column gap-3">
                @foreach ($moduleGroups as $groupName => $modules)
                    <div class="border rounded p-3">
                        <div class="fw-bold mb-2">{{ $groupName }}</div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Modul</th>
                                        <th class="text-center">View</th>
                                        <th class="text-center">Create</th>
                                        <th class="text-center">Update</th>
                                        <th class="text-center">Delete</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($modules as $module)
                                        @php
                                            $selected = old("permissions.{$module->id}", $permissionMatrix[$module->id] ?? []);
                                        @endphp
                                        <tr>
                                            <td>{{ $module->nama }}</td>
                                            <td class="text-center">
                                                <input type="checkbox" name="permissions[{{ $module->id }}][can_view]" value="1" @checked((bool) ($selected['can_view'] ?? false))>
                                            </td>
                                            <td class="text-center">
                                                <input type="checkbox" name="permissions[{{ $module->id }}][can_create]" value="1" @checked((bool) ($selected['can_create'] ?? false))>
                                            </td>
                                            <td class="text-center">
                                                <input type="checkbox" name="permissions[{{ $module->id }}][can_update]" value="1" @checked((bool) ($selected['can_update'] ?? false))>
                                            </td>
                                            <td class="text-center">
                                                <input type="checkbox" name="permissions[{{ $module->id }}][can_delete]" value="1" @checked((bool) ($selected['can_delete'] ?? false))>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="card border-light shadow-sm">
        <div class="card-body">
            <div class="d-flex justify-content-end gap-3">
                <a href="{{ route('pengaturan.role-akses.index') }}" class="btn btn-light fw-bold">
                    <i class="bi bi-x-circle-fill"></i> Batal
                </a>
                <button type="submit" class="btn btn-success fw-bold">
                    <i class="bi bi-check-circle-fill"></i> Simpan
                </button>
            </div>
        </div>
    </div>
</div>
