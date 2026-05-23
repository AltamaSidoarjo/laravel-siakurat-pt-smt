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
              <div class="d-flex justify-content-between align-items-center mb-3">
                  <h5 class="fw-bold mb-0">Hak Akses Modul</h5>
                  <div class="form-check">
                      <input type="checkbox" class="form-check-input" id="checkAll">
                      <label class="form-check-label fw-semibold" for="checkAll">Centang Semua</label>
                  </div>
              </div>
              <div class="table-responsive">
                  <table class="table table-sm table-bordered align-middle mb-0" id="permissionTable">
                      <thead class="table-light">
                          <tr>
                              <th style="min-width: 200px;">Modul</th>
                              <th class="text-center" style="width: 60px;">All</th>
                              <th class="text-center" style="width: 60px;">
                                  <div>View</div>
                                  <input type="checkbox" class="form-check-input col-check" data-col="view">
                              </th>
                              <th class="text-center" style="width: 60px;">
                                  <div>Create</div>
                                  <input type="checkbox" class="form-check-input col-check" data-col="create">
                              </th>
                              <th class="text-center" style="width: 60px;">
                                  <div>Update</div>
                                  <input type="checkbox" class="form-check-input col-check" data-col="update">
                              </th>
                              <th class="text-center" style="width: 60px;">
                                  <div>Delete</div>
                                  <input type="checkbox" class="form-check-input col-check" data-col="delete">
                              </th>
                          </tr>
                      </thead>
                      <tbody>
                          @foreach ($moduleGroups as $groupName => $modules)
                              <tr class="table-secondary">
                                  <td colspan="6" class="fw-bold small text-uppercase py-1">{{ $groupName }}</td>
                              </tr>
                              @foreach ($modules as $module)
                                  @php
                                      $selected = old("permissions.{$module->id}", $permissionMatrix[$module->id] ?? []);
                                  @endphp
                                  <tr>
                                      <td>{{ $module->nama }}</td>
                                      <td class="text-center">
                                          <input type="checkbox" class="form-check-input row-check">
                                      </td>
                                      <td class="text-center">
  <input type="checkbox" class="form-check-input perm-check" data-col="view" name="permissions[{{ $module->id }}][can_view]" value="1" @checked((bool) ($selected['can_view'] ?? false))>
                                      </td>
                                      <td class="text-center">
  <input type="checkbox" class="form-check-input perm-check" data-col="create" name="permissions[{{ $module->id }}][can_create]" value="1" @checked((bool) ($selected['can_create'] ??
  false))>
                                      </td>
                                      <td class="text-center">
  <input type="checkbox" class="form-check-input perm-check" data-col="update" name="permissions[{{ $module->id }}][can_update]" value="1" @checked((bool) ($selected['can_update'] ??
  false))>
                                      </td>
                                      <td class="text-center">
  <input type="checkbox" class="form-check-input perm-check" data-col="delete" name="permissions[{{ $module->id }}][can_delete]" value="1" @checked((bool) ($selected['can_delete'] ??
  false))>
                                      </td>
                                  </tr>
                              @endforeach
                          @endforeach
                      </tbody>
                  </table>
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

  @push('scripts')
  <script>
  document.addEventListener('DOMContentLoaded', function () {
      const table = document.getElementById('permissionTable');
      const checkAll = document.getElementById('checkAll');
      const colChecks = table.querySelectorAll('.col-check');
      const rowChecks = table.querySelectorAll('.row-check');
      const permChecks = table.querySelectorAll('.perm-check');

      checkAll.addEventListener('change', function () {
          permChecks.forEach(cb => cb.checked = this.checked);
          colChecks.forEach(cb => cb.checked = this.checked);
          rowChecks.forEach(cb => cb.checked = this.checked);
      });

      colChecks.forEach(colCb => {
          colCb.addEventListener('change', function () {
              const col = this.dataset.col;
              table.querySelectorAll('.perm-check[data-col="' + col + '"]').forEach(cb => cb.checked = this.checked);
              syncRowChecks();
              syncCheckAll();
          });
      });

      rowChecks.forEach(rowCb => {
          rowCb.addEventListener('change', function () {
              const tr = this.closest('tr');
              tr.querySelectorAll('.perm-check').forEach(cb => cb.checked = this.checked);
              syncColChecks();
              syncCheckAll();
          });
      });

      permChecks.forEach(cb => {
          cb.addEventListener('change', function () {
              syncRowCheck(this.closest('tr'));
              syncColChecks();
              syncCheckAll();
          });
      });

      function syncRowCheck(tr) {
          const perms = tr.querySelectorAll('.perm-check');
          const rowCb = tr.querySelector('.row-check');
          if (rowCb) rowCb.checked = Array.from(perms).every(cb => cb.checked);
      }

      function syncRowChecks() {
          rowChecks.forEach(rowCb => syncRowCheck(rowCb.closest('tr')));
      }

      function syncColChecks() {
          colChecks.forEach(colCb => {
              const col = colCb.dataset.col;
              const colPerms = table.querySelectorAll('.perm-check[data-col="' + col + '"]');
              colCb.checked = Array.from(colPerms).every(cb => cb.checked);
          });
      }

      function syncCheckAll() {
          checkAll.checked = Array.from(permChecks).every(cb => cb.checked);
      }

      syncRowChecks();
      syncColChecks();
      syncCheckAll();
  });
  </script>
  @endpush