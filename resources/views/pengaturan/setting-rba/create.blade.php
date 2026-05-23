@php
    $selectedItems = old('items', [['coa_id' => '', 'total_nominal' => '0', 'catatan' => '']]);
    $currentYear = (int) now()->format('Y');
@endphp

@extends('layouts.app')

@section('title', 'Create Setting RBA')

@section('content')
    <div class="row mb-3">
        <div class="col">
            <div class="d-flex align-items-center gap-3 fs-3">
                <a href="{{ route('pengaturan.setting-rba.index') }}" class="text-dark">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <span class="fw-bold">Create Setting RBA</span>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col">
            <div class="card border-muhammadiyah mb-2">
                <div class="card-body">
                    @include('partials.validation-errors')

                    <form method="post" action="{{ route('pengaturan.setting-rba.store') }}">
                        @csrf

                        <div class="d-flex flex-column gap-3">
                            <div class="card border-light shadow-sm">
                                <div class="card-header fw-bold bg-success-subtle text-success">
                                    RBA
                                </div>
                                <div class="card-body">
                                    <div class="card border-light shadow-sm mb-3">
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <label for="tahun" class="form-label">Tahun</label>
                                                    <input type="number" min="{{ $currentYear - 25 }}" max="{{ $currentYear + 10 }}" name="tahun" id="tahun" class="form-control" value="{{ old('tahun', $currentYear) }}" required>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="card border-light shadow-sm">
                                        <div class="card-body">
                                            <div class="table-responsive">
                                                <table id="table_rba_item" class="table table-sm table-bordered">
                                                    <thead>
                                                        <tr>
                                                            <th class="text-center align-middle" style="min-width: 220px;">COA<span class="text-danger">*</span></th>
                                                            <th class="text-center align-middle" style="min-width: 180px;">Nominal<span class="text-danger">*</span></th>
                                                            <th class="text-center align-middle" style="min-width: 220px;">Catatan</th>
                                                            <th class="text-center align-middle" style="width: 50px;">#</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach ($selectedItems as $index => $item)
                                                            <tr>
                                                                <td>
                                                                    <select name="items[{{ $index }}][coa_id]" class="form-select select2-coa select-coa" required>
                                                                        <option value="">Pilih opsi</option>
                                                                        @foreach ($coaOptions as $coa)
                                                                            <option value="{{ $coa->id }}" @selected((string) ($item['coa_id'] ?? '') === (string) $coa->id)>
                                                                                {{ $coa->kode }} - {{ $coa->nama }}
                                                                            </option>
                                                                        @endforeach
                                                                    </select>
                                                                </td>
                                                                <td>
                                                                    <input type="text" name="items[{{ $index }}][total_nominal]" value="{{ $item['total_nominal'] ?? '0' }}" class="form-control text-end input-nominal" required>
                                                                </td>
                                                                <td>
                                                                    <input type="text" name="items[{{ $index }}][catatan]" value="{{ $item['catatan'] ?? '' }}" class="form-control">
                                                                </td>
                                                                <td class="text-center align-middle">
                                                                    <button type="button" class="btn btn-light text-danger btn-remove">
                                                                        <i class="bi bi-trash-fill"></i>
                                                                    </button>
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>

                                            <div class="my-3">
                                                <button type="button" id="btn_add_item" class="btn btn-sm btn-light text-primary fw-bold">
                                                    <i class="bi bi-plus-circle-fill"></i> Add Item
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card border-light shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex justify-content-end gap-3">
                                        <a href="{{ route('pengaturan.setting-rba.index') }}" class="btn btn-light fw-bold">
                                            <i class="bi bi-x-circle-fill"></i> Batal
                                        </a>
                                        <button type="submit" class="btn btn-success fw-bold">
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

    <template id="detail-row-template">
        <tr>
            <td>
                <select name="items[__index__][coa_id]" class="form-select select2-coa select-coa" required>
                    <option value="">Pilih opsi</option>
                    @foreach ($coaOptions as $coa)
                        <option value="{{ $coa->id }}">{{ $coa->kode }} - {{ $coa->nama }}</option>
                    @endforeach
                </select>
            </td>
            <td>
                <input type="text" name="items[__index__][total_nominal]" value="0" class="form-control text-end input-nominal" required>
            </td>
            <td>
                <input type="text" name="items[__index__][catatan]" class="form-control">
            </td>
            <td class="text-center align-middle">
                <button type="button" class="btn btn-light text-danger btn-remove">
                    <i class="bi bi-trash-fill"></i>
                </button>
            </td>
        </tr>
    </template>
@endsection

@push('scripts')
    <script>
        $(document).ready(function () {
            const tableBody = document.querySelector('#table_rba_item tbody');
            const addButton = document.getElementById('btn_add_item');
            const template = document.getElementById('detail-row-template');

            if (!tableBody) {
                return;
            }

            function initSelect2(scope = document) {
                if (!(window.jQuery && window.jQuery.fn.select2)) {
                    return;
                }

                window.jQuery(scope).find('.select2-coa').each(function () {
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

            function parseAmount(value) {
                if (value === null || value === undefined || value === '') {
                    return 0;
                }

                if (typeof value === 'number') {
                    return Number.isFinite(value) ? value : 0;
                }

                let normalized = value.toString().trim().replace(/\s/g, '');
                const lastComma = normalized.lastIndexOf(',');
                const lastDot = normalized.lastIndexOf('.');

                if (lastComma !== -1 && lastDot !== -1) {
                    normalized = lastComma > lastDot
                        ? normalized.replace(/\./g, '').replace(',', '.')
                        : normalized.replace(/,/g, '');
                } else if (lastComma !== -1) {
                    normalized = normalized.replace(/\./g, '').replace(',', '.');
                } else {
                    normalized = normalized.replace(/,/g, '');
                }

                const parsed = Number(normalized.replace(/[^\d.-]/g, ''));

                return Number.isFinite(parsed) ? parsed : 0;
            }

            function formatAmount(value) {
                return parseAmount(value).toLocaleString('id-ID', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            }

            function reindexRows() {
                tableBody.querySelectorAll('tr').forEach((row, index) => {
                    row.querySelectorAll('[name]').forEach((input) => {
                        input.name = input.name.replace(/items\[\d+\]/, `items[${index}]`);
                    });
                });
            }

            function refreshCoaOptions() {
                const selectedValues = Array.from(tableBody.querySelectorAll('.select-coa'))
                    .map((select) => select.value)
                    .filter(Boolean);

                tableBody.querySelectorAll('.select-coa').forEach((select) => {
                    const current = select.value;

                    Array.from(select.options).forEach((option) => {
                        if (!option.value) {
                            return;
                        }

                        option.disabled = selectedValues.includes(option.value) && option.value !== current;
                    });
                });
            }

            function toggleRemoveButtons() {
                const rows = tableBody.querySelectorAll('tr');
                rows.forEach((row, index) => {
                    const button = row.querySelector('.btn-remove');
                    if (button) {
                        button.disabled = rows.length === 1 && index === 0;
                    }
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
                    refreshCoaOptions();
                    toggleRemoveButtons();
                });

                tableBody.addEventListener('click', function (event) {
                    const button = event.target.closest('.btn-remove');

                    if (!button) {
                        return;
                    }

                    button.closest('tr')?.remove();
                    reindexRows();
                    refreshCoaOptions();
                    toggleRemoveButtons();
                });

                tableBody.addEventListener('change', function (event) {
                    if (event.target.classList.contains('select-coa')) {
                        refreshCoaOptions();
                    }
                });

                tableBody.addEventListener('focusout', function (event) {
                    if (event.target.classList.contains('input-nominal')) {
                        event.target.value = formatAmount(event.target.value);
                    }
                });
            }

            document.querySelectorAll('.input-nominal').forEach((input) => {
                input.value = formatAmount(input.value);
            });

            document.querySelectorAll('form').forEach((form) => {
                form.addEventListener('submit', function () {
                    reindexRows();
                    tableBody.querySelectorAll('.input-nominal').forEach((input) => {
                        input.value = parseAmount(input.value).toString();
                    });
                });
            });

            initSelect2(document);
            refreshCoaOptions();
            toggleRemoveButtons();
        });
    </script>
@endpush
