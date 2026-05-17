<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SimrsImportPendapatanJualObat extends Model
{
    protected $table = 'simrs_import_pendapatan_jual_obat';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'tanggal',
        'nama_pelanggan',
        'keterangan',
        'jenis_jual',
        'ongkir',
        'ppn',
        'kode_gudang',
        'kode_rekening',
        'nama_rekening',
        'nomer_transaksi',
        'grandtotal',
        'import_ke',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'ongkir' => 'decimal:2',
        'ppn' => 'decimal:2',
        'grandtotal' => 'decimal:2',
    ];

    public function scopeBetweenDates(Builder $query, string $startDate, string $endDate): Builder
    {
        return $query->whereBetween('tanggal', [$startDate, $endDate]);
    }
}
