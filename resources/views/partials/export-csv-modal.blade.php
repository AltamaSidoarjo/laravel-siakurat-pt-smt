<div class="modal fade" id="exportCsvModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <form method="get" action="{{ $exportRoute }}">
            <div class="modal-header"><h5 class="modal-title">Export CSV {{ $exportTitle }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body"><div class="row g-3">
                <div class="col-md-6"><label class="form-label">Dari tanggal</label><input type="date" name="startDate" class="form-control" value="{{ $startDate }}" required></div>
                <div class="col-md-6"><label class="form-label">Sampai tanggal</label><input type="date" name="endDate" class="form-control" value="{{ $endDate }}" required></div>
            </div></div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-success"><i class="bi bi-download me-1"></i> Download CSV</button></div>
        </form>
    </div></div>
</div>
