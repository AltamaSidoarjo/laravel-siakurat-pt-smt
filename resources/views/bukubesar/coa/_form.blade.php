@php
    $isEdit = isset($coa);
    $hasChild = $hasChild ?? false;
@endphp

<div class="d-flex flex-column gap-3">
    <div class="card border-light shadow-sm">
        <div class="card-body">
            <div class="row align-items-center mb-2">
                <div class="col-12 col-sm-2">
                    <label for="status_aktif" class="fw-bold">Status Aktif</label>
                </div>
                <div class="col">
                    <select id="status_aktif" name="status_aktif" class="form-select">
                        <option value="1" @selected(old('status_aktif', $coa->status_aktif ?? 1) == 1)>Aktif</option>
                        <option value="0" @selected(old('status_aktif', $coa->status_aktif ?? 1) == 0)>Tidak Aktif</option>
                    </select>
                </div>
            </div>

            <div class="row align-items-center mb-2">
                <div class="col-12 col-sm-2">
                    <label for="parent_id" class="fw-bold">Parent Coa</label>
                </div>
                <div class="col">
                    <select id="parent_id" name="parent_id" class="form-select select2">
                        <option value="">(Tidak Ada Parent)</option>
                        @foreach ($parentOptions as $parentOption)
                            <option
                                value="{{ $parentOption->id }}"
                                data-aktivitas="{{ $parentOption->arus_kas_aktivitas ?? '' }}"
                                data-kelompok="{{ $parentOption->arus_kas_kelompok ?? '' }}"
                                @selected((string) old('parent_id', $coa->parent_coa ?? '') === (string) $parentOption->id)
                            >
                                {{ $parentOption->kode }} - {{ $parentOption->nama }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="row align-items-center mb-2">
                <div class="col-12 col-sm-2">
                    <label for="tipe_coa" class="fw-bold">Tipe Coa</label>
                    <span class="text-danger">*</span>
                </div>
                <div class="col">
                    <select id="tipe_coa" name="tipe_coa" class="form-select select2">
                        <option value="">Pilih opsi</option>
                        @foreach ($tipeOptions as $tipeOption)
                            <option value="{{ $tipeOption->nama }}" @selected(old('tipe_coa', $coa->tipe_coa ?? '') === $tipeOption->nama)>
                                {{ $tipeOption->nama }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="row align-items-center mb-2">
                <div class="col-12 col-sm-2">
                    <label for="kode" class="fw-bold">Kode</label>
                    <span class="text-danger">*</span>
                </div>
                <div class="col">
                    <input id="kode" type="text" name="kode" class="form-control" value="{{ old('kode', $coa->kode ?? '') }}">
                </div>
            </div>

            <div class="row align-items-center mb-2 arus-kas-field">
                <div class="col-12 col-sm-2">
                    <label for="arus_kas_aktivitas" class="fw-bold">Aktivitas Arus Kas</label>
                </div>
                <div class="col">
                    <select id="arus_kas_aktivitas" name="arus_kas_aktivitas" class="form-select">
                        <option value="">Belum dipetakan</option>
                        @foreach (['operasi' => 'Operasi', 'investasi' => 'Investasi', 'pendanaan' => 'Pendanaan'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('arus_kas_aktivitas', $coa->arus_kas_aktivitas ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="row align-items-center mb-2 arus-kas-field">
                <div class="col-12 col-sm-2">
                    <label for="arus_kas_kelompok" class="fw-bold">Kelompok Arus Kas</label>
                </div>
                <div class="col">
                    @php
                        $selectedKelompok = old('arus_kas_kelompok', $coa->arus_kas_kelompok ?? '');
                        $referensiKelompokMap = isset($referensiKelompok)
                            ? collect($referensiKelompok)->map(function ($items) {
                                return collect($items)->pluck('nama')->values();
                            })
                            : collect();
                    @endphp
                    <select id="arus_kas_kelompok" name="arus_kas_kelompok" class="form-select" data-referensi="{{ json_encode($referensiKelompokMap) }}">
                        <option value="">Pilih atau ketik baru...</option>
                        @if ($selectedKelompok)
                            <option value="{{ $selectedKelompok }}" selected>{{ $selectedKelompok }}</option>
                        @endif
                    </select>
                </div>
            </div>

            @if ($isEdit && $hasChild)
                <div id="cascade_wrapper" class="row align-items-center mb-2">
                    <div class="col-12 col-sm-2"></div>
                    <div class="col">
                        <div class="form-check">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                id="cascade_to_children"
                                name="cascade_to_children"
                                value="1"
                                @checked(old('cascade_to_children'))
                            >
                            <label class="form-check-label fw-semibold" for="cascade_to_children">
                                Terapkan klasifikasi arus kas ini ke seluruh sub-akun turunan
                            </label>
                        </div>
                        <small class="text-muted d-block mt-1">
                            Hanya berlaku untuk sub-akun non-Kasbank di bawah akun ini.
                        </small>
                        <input type="hidden" id="cascade_mode" name="cascade_mode" value="{{ old('cascade_mode', '') }}">
                    </div>
                </div>
            @endif

            <div class="row align-items-center mb-2">
                <div class="col-12 col-sm-2">
                    <label for="nama" class="fw-bold">Nama</label>
                    <span class="text-danger">*</span>
                </div>
                <div class="col">
                    <input id="nama" type="text" name="nama" class="form-control" value="{{ old('nama', $coa->nama ?? '') }}">
                </div>
            </div>

            <div class="row align-items-center mb-2">
                <div class="col-12 col-sm-2">
                    <label for="deskripsi" class="fw-bold">Deskripsi</label>
                </div>
                <div class="col">
                    <textarea id="deskripsi" name="deskripsi" class="form-control" rows="3">{{ old('deskripsi', $coa->deskripsi ?? '') }}</textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-light shadow-sm">
        <div class="card-body">
            <div class="d-flex gap-3">
                @if ($isEdit)
                    @if (! ($canDelete['allowed'] ?? (! $hasChild)))
                        <button type="button" class="btn btn-light text-secondary fw-bold me-auto" disabled title="{{ $canDelete['message'] ?? 'Akun tidak dapat dihapus.' }}">
                            <i class="bi bi-trash-fill"></i> Hapus
                        </button>
                    @else
                        <button type="button" class="btn btn-light text-danger fw-bold me-auto" data-bs-toggle="modal" data-bs-target="#deleteModal">
                            <i class="bi bi-trash-fill"></i> Hapus
                        </button>
                    @endif
                @endif

                <a href="{{ route('bukubesar.coa.index') }}" class="btn btn-light fw-bold {{ $isEdit ? '' : 'ms-auto' }}">
                    <i class="bi bi-x-circle-fill"></i> Batal
                </a>

                <button type="submit" class="btn btn-success fw-bold ms-2">
                    <i class="bi bi-check-circle-fill"></i> Simpan
                </button>
            </div>
        </div>
    </div>
</div>

@if ($isEdit && $hasChild)
    <div class="modal fade" id="cascadeConfirmModal" tabindex="-1" aria-labelledby="cascadeConfirmModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="cascadeConfirmModalLabel">
                        Konfirmasi Penerapan Arus Kas ke Sub-Akun
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">
                        Akun ini memiliki <strong id="modal_total_descendants">0</strong> sub-akun non-Kasbank:
                    </p>
                    <ul class="mb-3">
                        <li><strong id="modal_empty_count">0</strong> sub-akun belum memiliki klasifikasi arus kas</li>
                        <li><strong id="modal_existing_count">0</strong> sub-akun sudah memiliki klasifikasi arus kas</li>
                    </ul>
                    <div class="mb-2">
                        <div class="small fw-semibold text-muted mb-1">Contoh sub-akun yang sudah memiliki klasifikasi:</div>
                        <ul id="modal_existing_list" class="list-group list-group-flush small border rounded"></ul>
                    </div>
                    <p class="mb-0 small text-muted">
                        Pilih apakah ingin mengisi sub-akun yang masih kosong saja, atau menimpa seluruh sub-akun turunan.
                    </p>
                </div>
                <div class="modal-footer d-flex justify-content-between align-items-center">
                    <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Batal</button>
                    <div class="d-flex flex-wrap gap-2 justify-content-end">
                        <button type="button" id="btnCascadeFillEmpty" class="btn btn-sm btn-outline-primary">
                            Hanya Isi yang Kosong
                        </button>
                        <button type="button" id="btnCascadeOverwriteAll" class="btn btn-sm btn-warning fw-semibold">
                            Timpa Semua Sub-Akun
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
