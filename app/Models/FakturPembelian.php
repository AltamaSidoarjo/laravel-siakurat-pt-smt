<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class FakturPembelian extends Model
{
    protected $table = 'faktur_pembelian';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $casts = [
        'supplier_id' => 'integer',
        'tanggal_faktur' => 'date',
        'nilai_ppn' => 'decimal:2',
        'biaya_kirim' => 'decimal:2',
        'sudah_terbayar' => 'decimal:2',
        'grandtotal' => 'decimal:2',
        'tanggal_jatuh_tempo' => 'date',
        'tanggal_pesan' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function rincian()
    {
        return $this->hasMany(FakturPembelianRinci::class, 'faktur_pembelian_id');
    }

    public function scopeBetweenDates(Builder $query, string $startDate, string $endDate): Builder
    {
        return $query->whereBetween('tanggal_faktur', [$startDate, $endDate]);
    }
}
