@php($isActive = old('status_aktif', isset($supplier) ? $supplier->status_aktif : true))

<div class="card border-light shadow-sm">
    <div class="card-body">
        <div class="row align-items-center mb-3">
            <label for="kode_supplier" class="col-12 col-sm-3 col-form-label fw-bold">Kode Supplier <span class="text-danger">*</span></label>
            <div class="col">
                <input type="text" id="kode_supplier" name="kode_supplier" class="form-control" maxlength="100"
                       value="{{ old('kode_supplier', $supplier->kode_supplier ?? '') }}" required>
                <div class="form-text">Kode unik identifikasi supplier (misal: SUP-001).</div>
            </div>
        </div>

        <div class="row align-items-center mb-3">
            <label for="nama_supplier" class="col-12 col-sm-3 col-form-label fw-bold">Nama Supplier <span class="text-danger">*</span></label>
            <div class="col">
                <input type="text" id="nama_supplier" name="nama_supplier" class="form-control" maxlength="200"
                       value="{{ old('nama_supplier', $supplier->nama_supplier ?? '') }}" required>
            </div>
        </div>

        <div class="row align-items-center mb-3">
            <label for="kategori_supplier" class="col-12 col-sm-3 col-form-label fw-bold">Kategori Supplier</label>
            <div class="col">
                <input type="text" id="kategori_supplier" name="kategori_supplier" class="form-control" maxlength="200"
                       placeholder="Contoh: Farmasi, Alat Kesehatan, Umum"
                       value="{{ old('kategori_supplier', $supplier->kategori_supplier ?? '') }}">
            </div>
        </div>

        <div class="row align-items-center mb-3">
            <label for="email_supplier" class="col-12 col-sm-3 col-form-label fw-bold">Email Supplier</label>
            <div class="col">
                <input type="email" id="email_supplier" name="email_supplier" class="form-control" maxlength="255"
                       placeholder="supplier@example.com"
                       value="{{ old('email_supplier', $supplier->email_supplier ?? '') }}">
            </div>
        </div>

        <div class="row align-items-start mb-3">
            <label for="alamat_supplier" class="col-12 col-sm-3 col-form-label fw-bold">Alamat Supplier</label>
            <div class="col">
                <textarea id="alamat_supplier" name="alamat_supplier" class="form-control" rows="3"
                          placeholder="Alamat lengkap supplier">{{ old('alamat_supplier', $supplier->alamat_supplier ?? '') }}</textarea>
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
        <a href="{{ route('pengaturan.supplier.index') }}" class="btn btn-light fw-bold">Kembali</a>
        <button type="submit" class="btn btn-success fw-bold">Simpan</button>
    </div>
</div>
