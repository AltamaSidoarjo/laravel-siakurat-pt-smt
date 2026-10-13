@php
    $isActive = old('status_aktif', isset($informasi) ? $informasi->status_aktif : true);
    $isPopup = old('tampilkan_popup', isset($informasi) ? $informasi->tampilkan_popup : false);
    $selectedTipe = old('tipe', $informasi->tipe ?? 'info');
@endphp

<div class="card border-light shadow-sm">
    <div class="card-body">
        <div class="row align-items-center mb-3">
            <label for="judul" class="col-12 col-sm-3 col-form-label fw-bold">Judul Informasi <span class="text-danger">*</span></label>
            <div class="col">
                <input type="text" id="judul" name="judul" class="form-control" maxlength="255"
                       placeholder="Contoh: Pemeliharaan Server SIMRS, Jadwal Cuti Bersama"
                       value="{{ old('judul', $informasi->judul ?? '') }}" required>
                <div class="form-text">Judul ringkas informasi yang tampil di dashboard.</div>
            </div>
        </div>

        <div class="row align-items-center mb-3">
            <label for="tipe" class="col-12 col-sm-3 col-form-label fw-bold">Tipe Informasi <span class="text-danger">*</span></label>
            <div class="col-12 col-sm-6">
                <select id="tipe" name="tipe" class="form-select" required>
                    <option value="info" @selected($selectedTipe === 'info')>Informasi Umum (Biru)</option>
                    <option value="success" @selected($selectedTipe === 'success')>Sukses / Pemberitahuan Baik (Hijau)</option>
                    <option value="warning" @selected($selectedTipe === 'warning')>Peringatan / Hati-hati (Kuning)</option>
                    <option value="danger" @selected($selectedTipe === 'danger')>Penting / Kritis / Bahaya (Merah)</option>
                </select>
                <div class="form-text">Menentukan warna tema badge banner dan popup informasi.</div>
            </div>
        </div>

        <div class="row align-items-start mb-3">
            <label for="isi" class="col-12 col-sm-3 col-form-label fw-bold">Isi Informasi <span class="text-danger">*</span></label>
            <div class="col">
                <textarea id="isi" name="isi" class="form-control" rows="5"
                          placeholder="Tuliskan detail informasi lengkap di sini..." required>{{ old('isi', $informasi->isi ?? '') }}</textarea>
                <div class="form-text">Isi lengkap pesan yang dapat dibaca pada modal popup detail.</div>
            </div>
        </div>

        <div class="row align-items-center mb-3">
            <label for="tanggal_mulai" class="col-12 col-sm-3 col-form-label fw-bold">Periode Penayangan</label>
            <div class="col-12 col-sm-9">
                <div class="row g-2">
                    <div class="col-sm-6">
                        <label for="tanggal_mulai" class="form-label small text-muted">Tanggal Mulai</label>
                        <input type="date" id="tanggal_mulai" name="tanggal_mulai" class="form-control"
                               value="{{ old('tanggal_mulai', isset($informasi?->tanggal_mulai) ? $informasi->tanggal_mulai->format('Y-m-d') : '') }}">
                    </div>
                    <div class="col-sm-6">
                        <label for="tanggal_selesai" class="form-label small text-muted">Tanggal Selesai</label>
                        <input type="date" id="tanggal_selesai" name="tanggal_selesai" class="form-control"
                               value="{{ old('tanggal_selesai', isset($informasi?->tanggal_selesai) ? $informasi->tanggal_selesai->format('Y-m-d') : '') }}">
                    </div>
                </div>
                <div class="form-text">Kosongkan jika ingin informasi tayang terus-menerus tanpa batas tanggal.</div>
            </div>
        </div>

        <div class="row align-items-center mb-3">
            <label for="urutan" class="col-12 col-sm-3 col-form-label fw-bold">Urutan Prioritas</label>
            <div class="col-12 col-sm-3">
                <input type="number" id="urutan" name="urutan" class="form-control" min="0"
                       value="{{ old('urutan', $informasi->urutan ?? 0) }}">
                <div class="form-text">Urutan tampilan (angka lebih kecil tampil lebih awal).</div>
            </div>
        </div>

        <div class="row align-items-center mb-3">
            <div class="offset-sm-3 col">
                <input type="hidden" name="tampilkan_popup" value="0">
                <div class="form-check form-switch">
                    <input type="checkbox" id="tampilkan_popup" name="tampilkan_popup" value="1" class="form-check-input" @checked($isPopup)>
                    <label for="tampilkan_popup" class="form-check-label fw-bold">Tampilkan Popup Modal di Dashboard</label>
                </div>
                <div class="form-text">Jika aktif, dialog modal informasi akan otomatis muncul saat pengguna membuka halaman Dashboard.</div>
            </div>
        </div>

        <div class="row align-items-center">
            <div class="offset-sm-3 col">
                <input type="hidden" name="status_aktif" value="0">
                <div class="form-check form-switch">
                    <input type="checkbox" id="status_aktif" name="status_aktif" value="1" class="form-check-input" @checked($isActive)>
                    <label for="status_aktif" class="form-check-label fw-bold">Status Aktif</label>
                </div>
                <div class="form-text">Informasi hanya akan ditayangkan di dashboard jika status aktif.</div>
            </div>
        </div>
    </div>
</div>

<div class="card border-light shadow-sm">
    <div class="card-body d-flex justify-content-between align-items-center">
        <a href="{{ route('pengaturan.layanan-informasi.index') }}" class="btn btn-light">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
        <button type="submit" class="btn btn-primary fw-bold">
            <i class="bi bi-save"></i> Simpan
        </button>
    </div>
</div>
