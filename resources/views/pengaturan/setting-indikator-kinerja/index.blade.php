@extends('layouts.app')

@section('title', 'Setting Indikator Kinerja')

@section('content')
<div class="d-flex flex-column gap-3">
    <!-- Header -->
    <div class="row">
        <div class="col">
            <div class="d-flex align-items-center gap-3 fs-3">
                <a href="{{ route('home') }}" class="text-dark">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <span class="fw-bold">Setting Indikator Kinerja</span>
            </div>
            <p class="text-muted small mb-0 mt-1">
                Kelola master indikator kinerja rumah sakit, mapping basis perhitungan, dan entri data operasional/manual bulanan.
            </p>
        </div>
    </div>

    @include('partials.flash-message')
    @include('partials.validation-errors')

    <!-- Navigation Tabs -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom-0 pb-0">
            <ul class="nav nav-tabs card-header-tabs" id="settingTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <a class="nav-link fw-semibold {{ $tab !== 'manual' ? 'active text-primary' : 'text-muted' }}" 
                       href="{{ route('setting-indikator-kinerja.index', ['tab' => 'indikator', 'tahun' => $tahun]) }}">
                        <i class="bi bi-speedometer2 me-1"></i> Manajemen Indikator
                        <span class="badge bg-secondary-subtle text-secondary rounded-pill ms-1">{{ $indikators->count() }}</span>
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link fw-semibold {{ $tab === 'manual' ? 'active text-primary' : 'text-muted' }}" 
                       href="{{ route('setting-indikator-kinerja.index', ['tab' => 'manual', 'tahun' => $tahun]) }}">
                        <i class="bi bi-table me-1"></i> Entri Data Manual
                        <span class="badge bg-secondary-subtle text-secondary rounded-pill ms-1">{{ $variabelManuals->count() }}</span>
                    </a>
                </li>
            </ul>
        </div>

        <div class="card-body p-4">
            @if ($tab !== 'manual')
                <!-- TAB 1: MANAJEMEN INDIKATOR -->
                <div class="tab-pane fade show active">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
                        <div>
                            <h5 class="fw-bold mb-1">Daftar Indikator Kinerja</h5>
                            <p class="text-muted small mb-0">Master daftar indikator, metode kalkulasi, dan ambang batas target RAG.</p>
                        </div>
                        <div>
                            @if (auth()->user()?->hasModuleAccess('pengaturan.setting-indikator-kinerja', 'create'))
                                <a href="{{ route('setting-indikator-kinerja.create') }}" class="btn btn-success fw-bold">
                                    <i class="bi bi-plus-circle me-1"></i> Tambah Indikator Baru
                                </a>
                            @endif
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover table-bordered align-middle text-sm mb-0">
                            <thead class="table-light">
                                <tr class="text-nowrap text-center">
                                    <th style="width: 50px;">Urutan</th>
                                    <th style="width: 140px;">Kode</th>
                                    <th>Nama Indikator</th>
                                    <th style="width: 90px;">Satuan</th>
                                    <th style="width: 140px;">Tipe Kalkulasi</th>
                                    <th>Basis / Variabel Sumber</th>
                                    <th style="width: 220px;">Target RAG</th>
                                    <th style="width: 90px;">Status</th>
                                    <th style="width: 110px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($indikators as $item)
                                    <tr>
                                        <td class="text-center fw-semibold">{{ $item->urutan }}</td>
                                        <td>
                                            <span class="badge bg-light text-dark border font-monospace">{{ $item->kode }}</span>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-dark">{{ $item->nama }}</div>
                                            @if ($item->definisi_operasional)
                                                <small class="text-muted text-truncate d-inline-block" style="max-width: 320px;" title="{{ $item->definisi_operasional }}">
                                                    {{ $item->definisi_operasional }}
                                                </small>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-secondary-subtle text-dark border">
                                                {{ $item->satuan?->value ?? ($item->satuan ?? '-') }}
                                            </span>
                                        </td>
                                        <td>
                                            @php
                                                $tipeVal = $item->tipe_kalkulasi?->value ?? $item->tipe_kalkulasi;
                                            @endphp
                                            @if ($tipeVal === 'jurnal')
                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                                    <i class="bi bi-journal-text me-1"></i> Jurnal Umum
                                                </span>
                                            @elseif ($tipeVal === 'variabel_manual')
                                                <span class="badge bg-warning-subtle text-dark border border-warning-subtle">
                                                    <i class="bi bi-pencil-square me-1"></i> Variabel Manual
                                                </span>
                                            @else
                                                <span class="badge bg-info-subtle text-dark border border-info-subtle">
                                                    <i class="bi bi-hospital me-1"></i> SIMRS
                                                </span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($tipeVal === 'jurnal')
                                                <div class="small">
                                                    <div><strong>Pembilang:</strong> {{ $item->basis_pembilang?->label() ?? '-' }}</div>
                                                    <div><strong>Penyebut:</strong> {{ $item->basis_penyebut?->label() ?? '-' }}</div>
                                                    <div class="text-muted"><i class="bi bi-link-45deg"></i> {{ $item->mappingCoa->count() }} COA dipetakan</div>
                                                </div>
                                            @elseif ($tipeVal === 'variabel_manual')
                                                <div class="small">
                                                    <strong>Variabel:</strong>
                                                    @if ($item->variabelManual)
                                                        <span class="badge bg-light text-dark border">{{ $item->variabelManual->nama }}</span>
                                                    @else
                                                        <span class="text-muted">- Belum dihubungkan -</span>
                                                    @endif
                                                </div>
                                            @else
                                                <div class="small text-muted">Bridging SIMRS Otomatis</div>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="d-flex flex-column gap-1 small">
                                                <div>
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-1 py-0">Hijau</span>:
                                                    <span class="font-monospace">{{ $item->target_hijau_min !== null ? (float)$item->target_hijau_min : '*' }} s/d {{ $item->target_hijau_max !== null ? (float)$item->target_hijau_max : '*' }}</span>
                                                </div>
                                                <div>
                                                    <span class="badge bg-warning-subtle text-dark border border-warning-subtle px-1 py-0">Kuning</span>:
                                                    <span class="font-monospace">{{ $item->target_kuning_min !== null ? (float)$item->target_kuning_min : '*' }} s/d {{ $item->target_kuning_max !== null ? (float)$item->target_kuning_max : '*' }}</span>
                                                </div>
                                                <div>
                                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-1 py-0">Merah</span>:
                                                    <span class="font-monospace">{{ $item->target_merah_min !== null ? (float)$item->target_merah_min : '*' }} s/d {{ $item->target_merah_max !== null ? (float)$item->target_merah_max : '*' }}</span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            @if ($item->status_aktif)
                                                <span class="badge bg-success">Aktif</span>
                                            @else
                                                <span class="badge bg-secondary">Nonaktif</span>
                                            @endif
                                        </td>
                                        <td class="text-center text-nowrap">
                                            @if (auth()->user()?->hasModuleAccess('pengaturan.setting-indikator-kinerja', 'update'))
                                                <a href="{{ route('setting-indikator-kinerja.edit', $item->id) }}" class="btn btn-sm btn-outline-primary py-0 px-2" title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                            @endif
                                            @if (auth()->user()?->hasModuleAccess('pengaturan.setting-indikator-kinerja', 'delete'))
                                                <form action="{{ route('setting-indikator-kinerja.destroy', $item->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus indikator ini?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2 ms-1" title="Hapus">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center py-4 text-muted">
                                            <i class="bi bi-inbox fs-2 d-block mb-1"></i>
                                            Belum ada data indikator kinerja.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @else
                <!-- TAB 2: ENTRI DATA MANUAL BULANAN -->
                <div class="tab-pane fade show active">
                    <!-- Toolbar Filter Tahun & Tombol Tambah Variabel -->
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 bg-light p-3 rounded border">
                        <form method="get" action="{{ route('setting-indikator-kinerja.index') }}" class="d-flex align-items-center gap-2">
                            <input type="hidden" name="tab" value="manual">
                            <label for="tahunSelect" class="form-label fw-bold mb-0 text-nowrap">
                                <i class="bi bi-calendar3 me-1"></i> Tahun Data:
                            </label>
                            <select name="tahun" id="tahunSelect" class="form-select form-select-sm" style="width: 120px;" onchange="this.form.submit()">
                                @for ($y = now()->year + 1; $y >= now()->year - 4; $y--)
                                    <option value="{{ $y }}" {{ $tahun === $y ? 'selected' : '' }}>{{ $y }}</option>
                                @endfor
                            </select>
                            <button type="submit" class="btn btn-sm btn-secondary">
                                <i class="bi bi-arrow-clockwise"></i> Muat
                            </button>
                        </form>

                        <div>
                            @if (auth()->user()?->hasModuleAccess('pengaturan.setting-indikator-kinerja', 'create'))
                                <button type="button" class="btn btn-success fw-bold" data-bs-toggle="modal" data-bs-target="#modalTambahVariabel">
                                    <i class="bi bi-plus-circle me-1"></i> Tambah Variabel
                                </button>
                            @endif
                        </div>
                    </div>

                    <!-- Spreadsheet Form Input Matriks Bulanan -->
                    <form method="post" action="{{ route('setting-indikator-kinerja.simpan-manual-data') }}">
                        @csrf
                        <input type="hidden" name="tahun" value="{{ $tahun }}">

                        <div class="table-responsive border rounded mb-3">
                            <table class="table table-sm table-bordered table-hover align-middle mb-0 text-sm">
                                <thead class="table-light text-center align-middle">
                                    <tr>
                                        <th rowspan="2" style="width: 40px;">No</th>
                                        <th rowspan="2" style="width: 140px;">Kode</th>
                                        <th rowspan="2" style="min-width: 200px;">Nama Variabel</th>
                                        <th rowspan="2" style="width: 80px;">Satuan</th>
                                        <th colspan="12" class="bg-primary text-white py-1">Bulan (Tahun {{ $tahun }})</th>
                                        <th rowspan="2" style="width: 110px;" class="bg-light">Rata-rata Tahunan</th>
                                    </tr>
                                    <tr class="small">
                                        <th style="min-width: 85px;">Jan</th>
                                        <th style="min-width: 85px;">Feb</th>
                                        <th style="min-width: 85px;">Mar</th>
                                        <th style="min-width: 85px;">Apr</th>
                                        <th style="min-width: 85px;">Mei</th>
                                        <th style="min-width: 85px;">Jun</th>
                                        <th style="min-width: 85px;">Jul</th>
                                        <th style="min-width: 85px;">Agu</th>
                                        <th style="min-width: 85px;">Sep</th>
                                        <th style="min-width: 85px;">Okt</th>
                                        <th style="min-width: 85px;">Nov</th>
                                        <th style="min-width: 85px;">Des</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($variabelManuals as $v)
                                        @php
                                            $dataMap = $v->manualData->keyBy('bulan');
                                            $sum = 0;
                                            $count = 0;
                                            for ($m = 1; $m <= 12; $m++) {
                                                $rec = $dataMap->get($m);
                                                if ($rec && $rec->nilai !== null) {
                                                    $sum += (float) $rec->nilai;
                                                    $count++;
                                                }
                                            }
                                            $initialAvg = $count > 0 ? ($sum / $count) : null;
                                        @endphp
                                        <tr data-var-id="{{ $v->id }}">
                                            <td class="text-center text-muted">{{ $loop->iteration }}</td>
                                            <td>
                                                <span class="badge bg-light text-dark border font-monospace">{{ $v->kode }}</span>
                                            </td>
                                            <td>
                                                <div class="fw-semibold text-dark">{{ $v->nama }}</div>
                                                @if ($v->deskripsi)
                                                    <small class="text-muted d-block text-truncate" style="max-width: 250px;">{{ $v->deskripsi }}</small>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-secondary-subtle text-dark border">
                                                    {{ $v->satuan?->value ?? ($v->satuan ?? '-') }}
                                                </span>
                                            </td>
                                            @for ($b = 1; $b <= 12; $b++)
                                                @php
                                                    $rec = $dataMap->get($b);
                                                    $val = $rec ? $rec->nilai : null;
                                                @endphp
                                                <td class="p-1">
                                                    <input type="number" 
                                                           step="any" 
                                                           name="data[{{ $v->id }}][{{ $b }}]" 
                                                           value="{{ $val !== null ? (float) $val : '' }}" 
                                                           class="form-control form-control-sm text-end matrix-input p-1" 
                                                           data-row="{{ $v->id }}"
                                                           placeholder="-">
                                                </td>
                                            @endfor
                                            <td class="text-end fw-bold font-monospace bg-light p-2" id="avg-{{ $v->id }}">
                                                {{ $initialAvg !== null ? number_format($initialAvg, 2, ',', '.') : '-' }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="17" class="text-center py-4 text-muted">
                                                <i class="bi bi-inbox fs-2 d-block mb-1"></i>
                                                Belum ada variabel manual yang didaftarkan. Klik tombol <strong>+ Tambah Variabel</strong> di atas untuk menambahkan.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        @if ($variabelManuals->isNotEmpty() && auth()->user()?->hasModuleAccess('pengaturan.setting-indikator-kinerja', 'update'))
                            <div class="d-flex justify-content-end gap-2">
                                <button type="submit" class="btn btn-primary fw-bold px-4">
                                    <i class="bi bi-save me-1"></i> Simpan Data Bulanan
                                </button>
                            </div>
                        @endif
                    </form>
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Modal Tambah Variabel Manual -->
<div class="modal fade" id="modalTambahVariabel" tabindex="-1" aria-labelledby="modalTambahVariabelLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="{{ route('setting-indikator-kinerja.store-variabel') }}">
                @csrf
                <input type="hidden" name="tahun" value="{{ $tahun }}">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalTambahVariabelLabel">
                        <i class="bi bi-plus-circle me-1 text-success"></i> Tambah Variabel Manual Baru
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="varKode" class="form-label fw-semibold">Kode Variabel <span class="text-danger">*</span></label>
                        <input type="text" name="kode" id="varKode" class="form-control" required placeholder="Contoh: JML_PASIEN_BPJS" value="{{ old('kode') }}">
                        <div class="form-text">Kode unik huruf kapital & underscore untuk identifikasi variabel.</div>
                    </div>
                    <div class="mb-3">
                        <label for="varNama" class="form-label fw-semibold">Nama Variabel <span class="text-danger">*</span></label>
                        <input type="text" name="nama" id="varNama" class="form-control" required placeholder="Contoh: Jumlah Pasien BPJS Rawat Inap" value="{{ old('nama') }}">
                    </div>
                    <div class="mb-3">
                        <label for="varSatuan" class="form-label fw-semibold">Satuan <span class="text-danger">*</span></label>
                        <select name="satuan" id="varSatuan" class="form-select" required>
                            <option value="">-- Pilih Satuan --</option>
                            @foreach ($satuanOptions as $satuan)
                                <option value="{{ $satuan->value }}" {{ old('satuan') === $satuan->value ? 'selected' : '' }}>
                                    {{ $satuan->label() }} ({{ $satuan->value }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="varDeskripsi" class="form-label fw-semibold">Deskripsi / Catatan Operasional</label>
                        <textarea name="deskripsi" id="varDeskripsi" rows="3" class="form-control" placeholder="Penjelasan singkat mengenai sumber atau definisi data...">{{ old('deskripsi') }}</textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success fw-bold">
                        <i class="bi bi-check-circle me-1"></i> Simpan Variabel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        function recalculateRowAverage(rowId) {
            const inputs = document.querySelectorAll('.matrix-input[data-row="' + rowId + '"]');
            let sum = 0;
            let count = 0;
            inputs.forEach(function (inp) {
                const val = inp.value.trim();
                if (val !== '' && !isNaN(val)) {
                    sum += parseFloat(val);
                    count++;
                }
            });

            const avgCell = document.getElementById('avg-' + rowId);
            if (avgCell) {
                if (count > 0) {
                    const avg = sum / count;
                    avgCell.textContent = avg.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                } else {
                    avgCell.textContent = '-';
                }
            }
        }

        document.querySelectorAll('.matrix-input').forEach(function (inp) {
            inp.addEventListener('input', function () {
                const rowId = this.getAttribute('data-row');
                recalculateRowAverage(rowId);
            });
        });
    });
</script>
@endpush
