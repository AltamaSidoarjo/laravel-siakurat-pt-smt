@php
    $isEdit = isset($coa);
    $isLocked = $hasChild ?? false;
@endphp

<div class="d-flex flex-column gap-3">
    @if ($isEdit && $isLocked)
        <div class="alert alert-info mb-0">
            <i class="bi bi-info-circle-fill"></i>
            Akun ini adalah <strong>Parent COA</strong> dan memiliki akun turunan.
            Beberapa field tidak dapat diubah.
        </div>
    @endif

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
                    <select id="parent_id" name="parent_id" class="form-select select2" {{ $isLocked ? 'disabled' : '' }}>
                        <option value="">(Tidak Ada Parent)</option>
                        @foreach ($parentOptions as $parentOption)
                            <option
                                value="{{ $parentOption->id }}"
                                @selected((string) old('parent_id', $coa->parent_coa ?? '') === (string) $parentOption->id)
                            >
                                {{ $parentOption->kode }} - {{ $parentOption->nama }}
                            </option>
                        @endforeach
                    </select>
                    @if ($isLocked)
                        <input type="hidden" name="parent_id" value="{{ old('parent_id', $coa->parent_coa ?? '') }}">
                    @endif
                </div>
            </div>

            <div class="row align-items-center mb-2">
                <div class="col-12 col-sm-2">
                    <label for="tipe_coa" class="fw-bold">Tipe Coa</label>
                    <span class="text-danger">*</span>
                </div>
                <div class="col">
                    <select id="tipe_coa" name="tipe_coa" class="form-select select2" {{ $isLocked ? 'disabled' : '' }}>
                        <option value="">Pilih opsi</option>
                        @foreach ($tipeOptions as $tipeOption)
                            <option value="{{ $tipeOption->nama }}" @selected(old('tipe_coa', $coa->tipe_coa ?? '') === $tipeOption->nama)>
                                {{ $tipeOption->nama }}
                            </option>
                        @endforeach
                    </select>
                    @if ($isLocked)
                        <input type="hidden" name="tipe_coa" value="{{ old('tipe_coa', $coa->tipe_coa ?? '') }}">
                    @endif
                </div>
            </div>

            <div class="row align-items-center mb-2">
                <div class="col-12 col-sm-2">
                    <label for="kode" class="fw-bold">Kode</label>
                    <span class="text-danger">*</span>
                </div>
                <div class="col">
                    <input id="kode" type="text" name="kode" class="form-control" value="{{ old('kode', $coa->kode ?? '') }}" {{ $isLocked ? 'readonly' : '' }}>
                </div>
            </div>

            <div class="row align-items-center mb-2">
                <div class="col-12 col-sm-2">
                    <label for="nama" class="fw-bold">Nama</label>
                    <span class="text-danger">*</span>
                </div>
                <div class="col">
                    <input id="nama" type="text" name="nama" class="form-control" value="{{ old('nama', $coa->nama ?? '') }}" {{ $isLocked ? 'readonly' : '' }}>
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
                    @if ($isLocked)
                        <button type="button" class="btn btn-light text-danger fw-bold me-auto" disabled>
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
