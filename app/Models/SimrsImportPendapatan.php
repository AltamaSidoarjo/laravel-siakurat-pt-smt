<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SimrsImportPendapatan extends Model
{
    protected $table = 'simrs_import_pendapatan';

    protected $primaryKey = 'id';

    public $timestamps = false;

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'nomer_billing',
        'tanggal_reg',
        'user_importer',
        'import_time',
        'dokter',
        'nama_pasien',
        'penjamin',
        'poli',
        'status_layanan',
        'total_tagihan',
        'alamat',
        'jam_reg',
        'kode_dokter',
        'kode_penjamin',
        'kode_poli',
        'nama_kabupaten',
        'nama_kecamatan',
        'nama_kelurahan',
        'no_rekam_medis',
        'diagnosa_penyakit',
        'kamar_inap',
        'import_ke',
    ];

    protected $casts = [
        'tanggal_reg' => 'date',
        'import_time' => 'datetime',
        'total_tagihan' => 'decimal:2',
    ];

    public function scopeBetweenDates(Builder $query, string $startDate, string $endDate): Builder
    {
        return $query->whereBetween('tanggal_reg', [$startDate, $endDate]);
    }
}
