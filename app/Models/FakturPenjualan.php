<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class FakturPenjualan extends Model
{
    protected $table = 'faktur_penjualan';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $casts = [
        'pelanggan_id' => 'integer',
        'ppn_id' => 'integer',
        'akun_piutang_id' => 'integer',
        'tanggal_faktur' => 'date',
        'ppn_persen' => 'decimal:2',
        'ppn_rupiah' => 'decimal:2',
        'diskon_persen' => 'decimal:2',
        'diskon_rupiah' => 'decimal:2',
        'grandtotal' => 'decimal:2',
        'sudah_terbayar' => 'decimal:2',
        'status_proses' => 'integer',
        'tanggal_registrasi' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function rincian()
    {
        return $this->hasMany(FakturPenjualanRinci::class, 'faktur_penjualan_id');
    }

    public function scopeBetweenDates(Builder $query, string $startDate, string $endDate): Builder
    {
        return $query->whereBetween('tanggal_faktur', [$startDate, $endDate]);
    }
}
