@php
    $isKamar = $selectedTypeKey === 'kamar';
    $selectedRows = old('rincian');

    if ($selectedRows === null) {
        $selectedRows = $tindakanOptions->isNotEmpty()
            ? [['tindakan_key' => '', 'coa_id' => '']]
            : [];
    }
@endphp

@extends('layouts.app')

@section('title', 'Create Mapping Pendapatan')

@section('content')
    <div class="row mb-3">
        <div class="col">
            <div class="d-flex align-items-center gap-3 fs-3">
                <a href="{{ route('pengaturan.mapping-pendapatan.index', ['jenisTindakan' => $selectedTypeKey]) }}" class="text-dark">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <span class="fw-bold">Create Mapping Pendapatan - {{ $selectedType['label'] }}</span>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col">
            <div class="card border-muhammadiyah mb-2">
                <div class="card-body">
                    @include('partials.flash-message')
                    @include('partials.validation-errors')

                    <form method="post" action="{{ route('pengaturan.mapping-pendapatan.store') }}">
                        @csrf
                        <input type="hidden" name="jenis_tindakan" value="{{ $selectedTypeKey }}">

                        <div class="d-flex flex-column gap-3">
                            <div class="card border-light shadow-sm">
                                <div class="card-header fw-bold bg-success-subtle text-success">
                                    Header
                                </div>
                                <div class="card-body">
                                    @if ($tindakanOptions->isEmpty())
                                        <div class="alert alert-info mb-0">
                                            Semua {{ $isKamar ? 'kamar' : 'tindakan' }} untuk kategori {{ $selectedType['label'] }} sudah termapping.
                                        </div>
                                    @else
                                        <div class="table-responsive">
                                            <table id="table_data_detail" class="table table-sm table-bordered align-middle">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th style="min-width: 420px;">{{ $isKamar ? 'Kamar' : 'Tindakan' }}</th>
                                                        <th style="min-width: 320px;">Akun (COA)</th>
                                                        <th class="text-center" style="width: 60px;">#</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($selectedRows as $index => $row)
                                                        <tr>
                                                            <td>
                                                                <select name="rincian[{{ $index }}][tindakan_key]" class="form-select select2-tindakan" required>
                                                                    <option value="">{{ $isKamar ? '-- Pilih Kamar (Kode | Nama) --' : '-- Pilih Tindakan (Kode | Nama | Penjamin) --' }}</option>
                                                                    @foreach ($tindakanOptions as $tindakan)
                                                                        <option value="{{ $tindakan['selection_key'] }}" @selected(($row['tindakan_key'] ?? '') === $tindakan['selection_key'])>
                                                                            {{ $tindakan['kd_jenis_prw'] }} | {{ $tindakan['nm_perawatan'] }}@unless($isKamar) | {{ $tindakan['png_jawab'] }}@endunless
                                                                        </option>
                                                                    @endforeach
                                                                </select>
                                                            </td>
                                                            <td>
                                                                <select name="rincian[{{ $index }}][coa_id]" class="form-select select2-coa" required>
                                                                    <option value="">-- Pilih Akun --</option>
                                                                    @foreach ($coaOptions as $coa)
                                                                        <option value="{{ $coa->id }}" @selected((string) ($row['coa_id'] ?? '') === (string) $coa->id)>
                                                                            {{ $coa->kode }} | {{ $coa->nama }}
                                                                        </option>
                                                                    @endforeach
                                                                </select>
                                                            </td>
                                                            <td class="text-center">
                                                                <button type="button" class="btn btn-sm btn-light text-danger btn-delete-detail">
                                                                    <i class="bi bi-trash3-fill"></i>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>

                                        <div class="mt-3">
                                            <button type="button" id="btn_add_detail" class="btn btn-sm btn-light text-primary fw-bold">
                                                <i class="bi bi-plus-circle-fill"></i> Add Detail
                                            </button>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div class="card border-light shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex justify-content-end gap-3">
                                        <a href="{{ route('pengaturan.mapping-pendapatan.index', ['jenisTindakan' => $selectedTypeKey]) }}" class="btn btn-light fw-bold">
                                            <i class="bi bi-x-circle-fill"></i> Batal
                                        </a>
                                        <button type="submit" class="btn btn-success fw-bold" @disabled($tindakanOptions->isEmpty())>
                                            <i class="bi bi-check-circle-fill"></i> Simpan
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @if ($tindakanOptions->isNotEmpty())
        <template id="detail-row-template">
            <tr>
                <td>
                    <select name="rincian[__index__][tindakan_key]" class="form-select select2-tindakan" required>
                        <option value="">{{ $isKamar ? '-- Pilih Kamar (Kode | Nama) --' : '-- Pilih Tindakan (Kode | Nama | Penjamin) --' }}</option>
                        @foreach ($tindakanOptions as $tindakan)
                            <option value="{{ $tindakan['selection_key'] }}">
                                {{ $tindakan['kd_jenis_prw'] }} | {{ $tindakan['nm_perawatan'] }}@unless($isKamar) | {{ $tindakan['png_jawab'] }}@endunless
                            </option>
                        @endforeach
                    </select>
                </td>
                <td>
                    <select name="rincian[__index__][coa_id]" class="form-select select2-coa" required>
                        <option value="">-- Pilih Akun --</option>
                        @foreach ($coaOptions as $coa)
                            <option value="{{ $coa->id }}">{{ $coa->kode }} | {{ $coa->nama }}</option>
                        @endforeach
                    </select>
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-light text-danger btn-delete-detail">
                        <i class="bi bi-trash3-fill"></i>
                    </button>
                </td>
            </tr>
        </template>
    @endif
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const table = document.getElementById('table_data_detail');
            const tableBody = table?.querySelector('tbody');
            const addButton = document.getElementById('btn_add_detail');
            const template = document.getElementById('detail-row-template');

            function initSelect2(scope = document) {
                if (window.initSelect2Fields) {
                    window.initSelect2Fields(scope, '.select2-tindakan, .select2-coa');
                    return;
                }

                if (!(window.jQuery && window.jQuery.fn.select2)) {
                    return;
                }

                window.jQuery(scope).find('.select2-tindakan, .select2-coa').each(function () {
                    const $select = window.jQuery(this);
                    if ($select.data('select2')) {
                        return;
                    }

                    const placeholder = $select.find('option[value=""]').first().text().trim() || undefined;

                    $select.select2({
                        theme: 'bootstrap-5',
                        width: '100%',
                        dropdownParent: window.jQuery(document.body),
                        placeholder: placeholder,
                        allowClear: !$select.prop('required'),
                    });
                });
            }

            function reindexRows() {
                if (!tableBody) {
                    return;
                }

                tableBody.querySelectorAll('tr').forEach((row, index) => {
                    row.querySelectorAll('[name]').forEach((input) => {
                        input.name = input.name.replace(/rincian\[\d+\]/, `rincian[${index}]`);
                    });
                });
            }

            if (addButton && template && tableBody) {
                addButton.addEventListener('click', function () {
                    const clone = template.content.cloneNode(true);

                    clone.querySelectorAll('[name]').forEach((input) => {
                        input.name = input.name.replace('__index__', tableBody.querySelectorAll('tr').length);
                    });

                    tableBody.appendChild(clone);
                    initSelect2(tableBody.lastElementChild);
                });

                tableBody.addEventListener('click', function (event) {
                    const button = event.target.closest('.btn-delete-detail');

                    if (!button) {
                        return;
                    }

                    button.closest('tr')?.remove();
                    reindexRows();
                });
            }

            document.querySelectorAll('form').forEach((form) => {
                form.addEventListener('submit', function () {
                    reindexRows();
                });
            });

            initSelect2(document);
        });
    </script>
@endpush
