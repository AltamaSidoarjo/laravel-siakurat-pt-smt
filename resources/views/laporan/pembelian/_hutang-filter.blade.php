<div class="card border-light shadow-sm no-print">
    <div class="card-body">
        <form method="get" action="{{ $formAction }}" id="filterForm">
            <div class="row g-3 filter-fields-row">
                <div class="col-md-4 col-lg-3 filter-control-group">
                    <label for="reportDate" class="form-label">Tanggal Laporan</label>
                    <input
                        type="date"
                        name="reportDate"
                        id="reportDate"
                        class="form-control"
                        value="{{ $reportDate }}"
                        required
                    >
                </div>

                <div class="col-md-8 col-lg-6 filter-control-group">
                    <label for="supplierSelect" class="form-label">Supplier</label>
                    <select
                        name="supplierIds[]"
                        id="supplierSelect"
                        class="form-select select2"
                        multiple
                        data-placeholder="Semua supplier yang masih memiliki hutang"
                    >
                        @foreach ($supplierOptions as $supplier)
                            <option value="{{ $supplier->id }}" @selected(in_array((int) $supplier->id, $supplierIds, true))>
                                [{{ $supplier->kode_supplier }}] {{ $supplier->nama_supplier }}
                            </option>
                        @endforeach
                    </select>
                    <small class="text-muted d-block mt-1">
                        {{ $supplierIds === [] ? 'Kosong berarti semua supplier yang masih memiliki hutang.' : count($supplierIds).' supplier dipilih.' }}
                    </small>
                </div>

                <div class="col-lg-3 filter-primary-actions d-flex justify-content-lg-end gap-2 flex-wrap">
                    <a href="{{ $resetAction }}" class="btn btn-light">Reset</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-funnel me-1"></i>Tampilkan
                    </button>
                </div>
            </div>

            <div class="d-flex justify-content-center gap-2 flex-wrap mt-3">
                <button type="button" class="btn btn-outline-dark" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i>Print
                </button>
                <button type="submit" class="btn btn-outline-success" formaction="{{ $exportAction }}">
                    <i class="bi bi-filetype-csv me-1"></i>Export CSV
                </button>
            </div>
        </form>
    </div>
</div>

@once
    @push('styles')
        <style>
            .filter-fields-row {
                align-items: flex-start;
            }

            .filter-control-group .form-label {
                display: block;
                margin-bottom: .5rem;
            }

            .filter-primary-actions {
                align-items: center;
                min-height: calc(1.5em + .75rem + 2px);
                margin-top: 2rem;
            }

            #filterForm .select2-container--bootstrap-5 .select2-selection--multiple {
                display: flex;
                align-items: center;
                min-height: calc(1.5em + .75rem + 2px);
                padding-top: 0;
                padding-bottom: 0;
            }

            #filterForm .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__rendered {
                display: flex;
                align-items: center;
                flex: 1 1 auto;
                flex-wrap: wrap;
                gap: .25rem;
                margin: 0;
                padding-top: .25rem;
                padding-bottom: .25rem;
            }

            #filterForm .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice,
            #filterForm .select2-container--bootstrap-5 .select2-selection--multiple .select2-search {
                margin-bottom: 0;
                margin-top: 0;
            }

            @media (max-width: 991.98px) {
                .filter-primary-actions {
                    justify-content: flex-start;
                    margin-top: 0;
                }
            }
        </style>
    @endpush
@endonce
