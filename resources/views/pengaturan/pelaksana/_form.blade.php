@php($isActive = old('status_aktif', isset($pelaksana) ? $pelaksana->status_aktif : true))

<div class="card border-light shadow-sm">
    <div class="card-body">
        <div class="row align-items-center mb-3">
            <label for="no_proyek" class="col-12 col-sm-3 col-form-label fw-bold">No. Proyek</label>
            <div class="col">
                <input type="text" id="no_proyek" name="no_proyek" class="form-control" maxlength="255"
                       value="{{ old('no_proyek', $pelaksana->no_proyek ?? '') }}" required>
                <div class="form-text">Kode harus sama persis dengan field job dari Billing API.</div>
            </div>
        </div>
        <div class="row align-items-center mb-3">
            <label for="nama_pelaksana" class="col-12 col-sm-3 col-form-label fw-bold">Nama Pelaksana</label>
            <div class="col">
                <input type="text" id="nama_pelaksana" name="nama_pelaksana" class="form-control" maxlength="255"
                       value="{{ old('nama_pelaksana', $pelaksana->nama_pelaksana ?? '') }}" required>
            </div>
        </div>
        <div class="row align-items-center">
            <div class="offset-sm-3 col">
                <input type="hidden" name="status_aktif" value="0">
                <div class="form-check form-switch">
                    <input type="checkbox" id="status_aktif" name="status_aktif" value="1" class="form-check-input" @checked($isActive)>
                    <label for="status_aktif" class="form-check-label">Aktif</label>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card border-light shadow-sm">
    <div class="card-body d-flex justify-content-end gap-2">
        <a href="{{ route('pengaturan.pelaksana.index') }}" class="btn btn-light fw-bold">Kembali</a>
        <button type="submit" class="btn btn-success fw-bold">Simpan</button>
    </div>
</div>
