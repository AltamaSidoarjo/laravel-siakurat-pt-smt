@extends('layouts.app')

@section('title', 'Edit COA')

@section('content')
    <div class="row mb-3">
        <div class="col">
            <div class="d-flex align-items-center gap-3 fs-3">
                <a href="{{ route('bukubesar.coa.index') }}" class="text-dark">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <span class="fw-bold">Edit COA</span>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col">
            <div class="card border-muhammadiyah mb-2">
                <div class="card-body">
                    @include('partials.flash-message')
                    @include('partials.validation-errors')

                    <form action="{{ route('bukubesar.coa.update', $coa) }}" method="post">
                        @csrf
                        @method('put')
                        @include('bukubesar.coa._form')
                    </form>
                </div>
            </div>
        </div>
    </div>

    @if (! $hasChild)
        <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Konfirmasi Hapus</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        Apakah Anda yakin ingin menghapus COA ini?
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                        <form action="{{ route('bukubesar.coa.destroy', $coa) }}" method="post">
                            @csrf
                            @method('delete')
                            <button type="submit" class="btn btn-danger">Hapus</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    <script>
        window.jQuery(function () {
            if (window.jQuery && window.jQuery.fn.select2) {
                window.jQuery('#status_aktif').select2({
                    theme: 'bootstrap-5',
                    width: '100%',
                    minimumResultsForSearch: Infinity
                });
                window.jQuery('#parent_id, #tipe_coa, #arus_kas_aktivitas').select2({
                    theme: 'bootstrap-5',
                    width: '100%'
                });
            }

            const toggleArusKas = () => window.jQuery('.arus-kas-field').toggle(window.jQuery('#tipe_coa').val() !== 'Kasbank');
            window.jQuery('#tipe_coa').on('change', toggleArusKas);
            toggleArusKas();
        });
    </script>
@endpush
