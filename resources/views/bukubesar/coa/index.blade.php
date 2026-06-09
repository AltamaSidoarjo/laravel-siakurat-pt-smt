@extends('layouts.app')

@section('title', 'COA')

@section('content')
    @php
        $exportRows = collect($rows)->map(function ($coa) {
            $level = max(0, (int) ($coa->level ?? 0));
            $indent = str_repeat('    ', $level);

            return [
                'id' => (int) $coa->id,
                'parent_coa' => filled($coa->parent_coa) ? (int) $coa->parent_coa : null,
                'level' => $level,
                'kode' => (string) $coa->kode,
                'nama' => (string) $coa->nama,
                'tipe_coa' => (string) $coa->tipe_coa,
                'has_children' => (int) ($coa->has_children ?? $coa->is_parent ?? 0) === 1,
                'kode_display' => $indent.$coa->kode,
                'nama_display' => $indent.$coa->nama,
            ];
        })->values();
    @endphp

    <div class="row mb-3">
        <div class="col">
            <div class="d-flex align-items-center gap-3 fs-3">
                <a href="{{ route('home') }}" class="text-dark">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <span class="fw-bold">COA</span>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col">
            <div class="card border-muhammadiyah mb-2">
                <div class="card-body">
                    <div class="d-flex flex-column gap-3">
                        @include('partials.flash-message')
                        @include('partials.validation-errors')

                        <div class="card border-light shadow-sm">
                            <div class="card-body">
                                <div class="d-flex flex-wrap justify-content-end gap-2 align-items-center">
                                    <button type="button" class="btn btn-outline-primary fw-bold" id="expandAllButton">
                                        <i class="bi bi-arrows-expand"></i> Expand All
                                    </button>
                                    <button type="button" class="btn btn-outline-dark fw-bold" id="collapseAllButton">
                                        <i class="bi bi-arrows-collapse"></i> Collapse All
                                    </button>
                                    <button type="button" class="btn btn-outline-success fw-bold" id="exportExcelButton">
                                        <i class="bi bi-file-earmark-excel"></i> Export Excel
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary fw-bold" id="exportCsvButton">
                                        <i class="bi bi-filetype-csv"></i> Export CSV
                                    </button>
                                    <a href="{{ route('bukubesar.coa.create') }}" class="btn btn-success fw-bold">
                                        <i class="bi bi-plus-circle-fill"></i> Tambah
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="card border-light shadow-sm">
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped table-bordered table-hover align-middle" id="coaTreeTable">
                                        <thead class="table-light">
                                            <tr class="fs-6">
                                                <th class="text-start" style="width: 20%;">Nomer</th>
                                                <th>Nama</th>
                                                <th style="width: 15%;">Tipe</th>
                                                <th style="width: 70px" class="text-center">#</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($rows as $coa)
                                                @php
                                                    $level = (int) ($coa->level ?? 0);
                                                    $indentPx = max(0, $level) * 16;
                                                    $hasChildren = (int) ($coa->has_children ?? $coa->is_parent ?? 0) === 1;
                                                    $isVisible = $level <= 1;
                                                    $parentId = filled($coa->parent_coa) ? (int) $coa->parent_coa : null;
                                                    $isExpanded = $hasChildren && $level === 0;
                                                @endphp
                                                <tr
                                                    class="coa-row"
                                                    data-id="{{ $coa->id }}"
                                                    data-parent-id="{{ $parentId }}"
                                                    data-level="{{ $level }}"
                                                    data-has-children="{{ $hasChildren ? 1 : 0 }}"
                                                    data-expanded="{{ $isExpanded ? 1 : 0 }}"
                                                    @unless ($isVisible) style="display: none;" @endunless
                                                >
                                                    <td class="text-start">
                                                        <div class="d-flex align-items-center" style="padding-left: {{ $indentPx }}px;">
                                                            @if ($hasChildren)
                                                                <button
                                                                    type="button"
                                                                    class="btn btn-sm btn-link text-decoration-none text-dark p-0 me-2 coa-toggle"
                                                                    data-id="{{ $coa->id }}"
                                                                    aria-expanded="{{ $isExpanded ? 'true' : 'false' }}"
                                                                    aria-label="{{ $isExpanded ? 'Collapse' : 'Expand' }} {{ $coa->nama }}"
                                                                >
                                                                    <i class="bi {{ $isExpanded ? 'bi-caret-down-fill' : 'bi-caret-right-fill' }}"></i>
                                                                </button>
                                                            @else
                                                                <span class="d-inline-block me-2 coa-toggle-spacer"></span>
                                                            @endif
                                                            <span class="{{ $hasChildren ? 'fw-bold' : '' }}">
                                                                {{ $coa->kode }}
                                                            </span>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span style="display:inline-block; padding-left: {{ $indentPx + 24 }}px;" class="{{ $hasChildren ? 'fw-bold' : '' }}">
                                                            {{ $coa->nama }}
                                                        </span>
                                                    </td>
                                                    <td>{{ $coa->tipe_coa }}</td>
                                                    <td class="text-center">
                                                        <a href="{{ route('bukubesar.coa.edit', $coa->id) }}" class="btn btn-sm btn-outline-primary">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const treeRows = Array.from(document.querySelectorAll('.coa-row'));
            const childrenByParentId = new Map();
            const exportRows = @json($exportRows);

            treeRows.forEach(function (row) {
                const parentId = row.dataset.parentId;
                if (!parentId) {
                    return;
                }

                if (!childrenByParentId.has(parentId)) {
                    childrenByParentId.set(parentId, []);
                }

                childrenByParentId.get(parentId).push(row);
            });

            function setToggleState(row, expanded) {
                row.dataset.expanded = expanded ? '1' : '0';

                const toggle = row.querySelector('.coa-toggle');
                if (!toggle) {
                    return;
                }

                toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
                toggle.setAttribute('aria-label', (expanded ? 'Collapse ' : 'Expand ') + (row.querySelector('td:nth-child(2)')?.textContent.trim() || 'COA'));

                const icon = toggle.querySelector('i');
                if (icon) {
                    icon.className = expanded ? 'bi bi-caret-down-fill' : 'bi bi-caret-right-fill';
                }
            }

            function collapseRow(row) {
                const children = childrenByParentId.get(String(row.dataset.id)) || [];

                children.forEach(function (childRow) {
                    childRow.style.display = 'none';
                    setToggleState(childRow, false);
                    collapseRow(childRow);
                });

                setToggleState(row, false);
            }

            function expandRow(row) {
                const children = childrenByParentId.get(String(row.dataset.id)) || [];

                children.forEach(function (childRow) {
                    childRow.style.display = '';
                });

                setToggleState(row, true);
            }

            function expandAll() {
                treeRows.forEach(function (row) {
                    row.style.display = '';

                    if (row.dataset.hasChildren === '1') {
                        setToggleState(row, true);
                    }
                });
            }

            function collapseAll() {
                treeRows.forEach(function (row) {
                    const level = Number(row.dataset.level || 0);
                    row.style.display = level <= 1 ? '' : 'none';

                    if (row.dataset.hasChildren === '1') {
                        setToggleState(row, level === 0);
                    }
                });
            }

            document.querySelectorAll('.coa-toggle').forEach(function (toggleButton) {
                toggleButton.addEventListener('click', function () {
                    const row = this.closest('.coa-row');
                    if (!row) {
                        return;
                    }

                    const rowId = row.dataset.id;
                    const isExpanded = row.dataset.expanded === '1';

                    if (isExpanded) {
                        collapseRow(row);
                        return;
                    }

                    expandRow(row);
                });
            });

            document.getElementById('expandAllButton')?.addEventListener('click', function () {
                expandAll();
            });

            document.getElementById('collapseAllButton')?.addEventListener('click', function () {
                collapseAll();
            });

            collapseAll();

            function buildExportData() {
                return [
                    ['Nomer', 'Nama', 'Tipe'],
                    ...exportRows.map(function (row) {
                        return [row.kode_display, row.nama_display, row.tipe_coa];
                    }),
                ];
            }

            document.getElementById('exportExcelButton')?.addEventListener('click', function () {
                if (typeof XLSX === 'undefined') {
                    return;
                }

                const workbook = XLSX.utils.book_new();
                const worksheet = XLSX.utils.aoa_to_sheet(buildExportData());
                XLSX.utils.book_append_sheet(workbook, worksheet, 'COA');
                XLSX.writeFile(workbook, 'coa-tree.xlsx');
            });

            document.getElementById('exportCsvButton')?.addEventListener('click', function () {
                const csvRows = buildExportData().map(function (row) {
                    return row.map(function (value) {
                        const escaped = String(value ?? '').replace(/"/g, '""');
                        return `"${escaped}"`;
                    }).join(',');
                });

                const blob = new Blob([csvRows.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = 'coa-tree.csv';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(link.href);
            });
        });
    </script>
@endpush

@push('styles')
    <style>
        .coa-toggle {
            width: 18px;
            line-height: 1;
        }

        .coa-toggle-spacer {
            width: 18px;
        }

        #coaTreeTable td,
        #coaTreeTable th {
            vertical-align: middle;
        }
    </style>
@endpush
