@php
    $isEdit = isset($saldoAwal);
    $isLocked = $isEdit && $saldoAwal->isLocked();

    $rincian = old('rincian', $isEdit ? $saldoAwal->rincian->map(fn ($item) => [
        'coa_id' => $item->coa_id,
        'debit' => (float) $item->debit,
        'kredit' => (float) $item->kredit,
        'catatan' => $item->catatan,
    ])->toArray() : [[
        'coa_id' => '',
        'debit' => 0,
        'kredit' => 0,
        'catatan' => '',
    ]]);

    $tanggalCutoffValue = old('tanggal_cutoff', $isEdit ? optional($saldoAwal->tanggal_cutoff)->format('Y-m-d') : ($defaultCutoff ?? ''));
    $nomerValue = old('nomer', $isEdit ? $saldoAwal->nomer : ($suggestedNomer ?? ''));
    $keteranganValue = old('keterangan', $isEdit ? $saldoAwal->keterangan : 'Saldo Awal Periode');
@endphp

<div class="d-flex flex-column gap-3">
    @if ($isLocked)
        <div class="alert alert-info d-flex align-items-center justify-content-between mb-0 shadow-sm" role="alert">
            <div class="d-flex align-items-center">
                <i class="bi bi-shield-lock-fill fs-3 me-3 text-primary"></i>
                <div>
                    <h6 class="alert-heading mb-1 fw-bold">Saldo Awal Terkunci & Telah Diposting</h6>
                    <div class="small">
                        Data ini telah dibukukan ke Buku Besar pada tanggal cut-off <strong>{{ optional($saldoAwal->tanggal_cutoff)->format('d F Y') }}</strong>. Mode saat ini adalah <em>Read-Only</em>.
                    </div>
                </div>
            </div>
            <div>
                <a href="{{ route('bukubesar.saldo-awal.index') }}" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar
                </a>
            </div>
        </div>
    @endif

    {{-- Card Header --}}
    <div class="card border-light shadow-sm">
        <div class="card-header fw-bold bg-success-subtle text-success d-flex justify-content-between align-items-center">
            <span><i class="bi bi-info-circle me-1"></i> Informasi Dasar Saldo Awal</span>
            @if ($isEdit)
                @if ($isLocked)
                    <span class="badge bg-success"><i class="bi bi-lock-fill me-1"></i> Terkunci</span>
                @else
                    <span class="badge bg-warning text-dark"><i class="bi bi-pencil-fill me-1"></i> Draft</span>
                @endif
            @endif
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label for="input_tanggal_cutoff" class="form-label fw-bold">
                        Tanggal Cut-Off<span class="text-danger">*</span>
                    </label>
                    <input type="date" name="tanggal_cutoff" id="input_tanggal_cutoff" class="form-control" value="{{ $tanggalCutoffValue }}" {{ $isLocked ? 'disabled' : 'required' }}>
                    <div class="form-text small">Posisi per tanggal tutup buku (misal: 31-12-2024).</div>
                </div>

                <div class="col-md-3">
                    <label for="input_nomor" class="form-label fw-bold">
                        Nomor Dokumen
                    </label>
                    <input type="text" name="nomer" id="input_nomor" class="form-control" value="{{ $nomerValue }}" placeholder="Otomatis jika dikosongkan" {{ $isLocked ? 'disabled' : '' }}>
                    <div class="form-text small">Nomor referensi saldo awal.</div>
                </div>

                <div class="col-md-6">
                    <label for="input_keterangan" class="form-label fw-bold">
                        Keterangan
                    </label>
                    <input type="text" name="keterangan" id="input_keterangan" class="form-control" value="{{ $keteranganValue }}" placeholder="Contoh: Saldo Awal Pembukuan 2025" {{ $isLocked ? 'disabled' : '' }}>
                </div>
            </div>
        </div>
    </div>

    {{-- Card Rincian Akun --}}
    <div class="card border-light shadow-sm">
        <div class="card-header fw-bold bg-success-subtle text-success d-flex justify-content-between align-items-center">
            <span><i class="bi bi-card-checklist me-1"></i> Daftar Akun & Saldo Awal</span>
            @if (! $isLocked)
                <button type="button" class="btn btn-sm btn-success" id="btn-add-row">
                    <i class="bi bi-plus-circle me-1"></i> Tambah Akun
                </button>
            @endif
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="table_saldo_awal_detail" class="table table-sm table-bordered align-middle">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center" style="width: 40px;">No</th>
                            <th style="min-width: 320px;">Akun (COA)<span class="text-danger">*</span></th>
                            <th class="text-center" style="width: 140px;">Tipe Akun</th>
                            <th class="text-end" style="min-width: 170px;">Saldo Debit (Rp)<span class="text-danger">*</span></th>
                            <th class="text-end" style="min-width: 170px;">Saldo Kredit (Rp)<span class="text-danger">*</span></th>
                            <th style="min-width: 200px;">Catatan</th>
                            @if (! $isLocked)
                                <th class="text-center" style="width: 50px;">#</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rincian as $index => $item)
                            <tr>
                                <td class="text-center row-number">{{ $index + 1 }}</td>
                                <td>
                                    <select name="rincian[{{ $index }}][coa_id]" class="form-select select2-coa" {{ $isLocked ? 'disabled' : 'required' }}>
                                        <option value="">-- Pilih Akun COA --</option>
                                        @foreach ($coaOptions as $coa)
                                            <option value="{{ $coa->id }}"
                                                data-tipe="{{ $coa->tipe_coa }}"
                                                @selected((string) ($item['coa_id'] ?? '') === (string) $coa->id)>
                                                {{ $coa->kode }} - {{ $coa->nama }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="text-center text-muted small col-tipe-coa">
                                    -
                                </td>
                                <td>
                                    <input type="text" name="rincian[{{ $index }}][debit]"
                                        value="{{ number_format((float) ($item['debit'] ?? 0), 0, ',', '.') }}"
                                        class="form-control text-end input-debit" {{ $isLocked ? 'disabled' : 'required' }}>
                                </td>
                                <td>
                                    <input type="text" name="rincian[{{ $index }}][kredit]"
                                        value="{{ number_format((float) ($item['kredit'] ?? 0), 0, ',', '.') }}"
                                        class="form-control text-end input-kredit" {{ $isLocked ? 'disabled' : 'required' }}>
                                </td>
                                <td>
                                    <input type="text" name="rincian[{{ $index }}][catatan]"
                                        value="{{ $item['catatan'] ?? '' }}"
                                        class="form-control" placeholder="Opsional" {{ $isLocked ? 'disabled' : '' }}>
                                </td>
                                @if (! $isLocked)
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-outline-danger btn-delete-row" title="Hapus Baris">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="3" class="text-end align-middle">TOTAL:</td>
                            <td>
                                <input type="text" id="input_total_debit" class="form-control text-end fw-bold border-0 bg-transparent text-success fs-6" value="0" readonly>
                            </td>
                            <td>
                                <input type="text" id="input_total_kredit" class="form-control text-end fw-bold border-0 bg-transparent text-primary fs-6" value="0" readonly>
                            </td>
                            <td colspan="{{ $isLocked ? 1 : 2 }}"></td>
                        </tr>
                        <tr>
                            <td colspan="3" class="text-end align-middle">SELISIH (DEBIT - KREDIT):</td>
                            <td colspan="2">
                                <input type="text" id="input_selisih" class="form-control text-center fw-bold border-0 bg-transparent fs-6" value="0" readonly>
                            </td>
                            <td colspan="{{ $isLocked ? 1 : 2 }}" class="align-middle">
                                <div id="balance-status" class="fw-bold">
                                    Checking...
                                </div>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            @if (! $isLocked)
                <div class="d-flex justify-content-between align-items-center mt-2">
                    <button type="button" class="btn btn-outline-success btn-sm" id="btn-add-row-bottom">
                        <i class="bi bi-plus-lg me-1"></i> Tambah Baris Akun
                    </button>
                    <div class="text-muted small">
                        * Minimal 1 akun dengan nominal terisi. Untuk mengunci dan memposting, total selisih wajib Rp 0.
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Tombol Aksi --}}
    @if (! $isLocked)
        <div class="card border-light shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <a href="{{ route('bukubesar.saldo-awal.index') }}" class="btn btn-light">
                        <i class="bi bi-x-circle me-1"></i> Batal
                    </a>
                    <div class="d-flex gap-2">
                        <button type="submit" name="action" value="save_draft" class="btn btn-outline-primary fw-semibold">
                            <i class="bi bi-save me-1"></i> Simpan sebagai Draft
                        </button>
                        <button type="submit" name="action" value="save_lock" id="btn-submit-lock" class="btn btn-success fw-bold">
                            <i class="bi bi-lock-fill me-1"></i> Simpan & Kunci (Posting ke Buku Besar)
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

