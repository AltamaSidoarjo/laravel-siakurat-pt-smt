<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PenerimaanPenjualan extends Model
{
    protected $table = 'penerimaan_penjualan';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'pelanggan_id',
        'akun_bank_id',
        'akun_piutang_id',
        'nomer',
        'tanggal',
        'jumlah_pembayaran',
        'keterangan',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'pelanggan_id' => 'integer',
        'akun_bank_id' => 'integer',
        'akun_piutang_id' => 'integer',
        'tanggal' => 'date',
        'jumlah_pembayaran' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function pelanggan()
    {
        return $this->belongsTo(Pelanggan::class, 'pelanggan_id');
    }

    public function akunBank()
    {
        return $this->belongsTo(Coa::class, 'akun_bank_id');
    }

    public function akunPiutang()
    {
        return $this->belongsTo(Coa::class, 'akun_piutang_id');
    }

    public function rincian()
    {
        return $this->hasMany(PenerimaanPenjualanRinci::class, 'penerimaan_penjualan_id');
    }

    public function scopeBetweenDates(Builder $query, string $startDate, string $endDate): Builder
    {
        return $query->whereBetween('tanggal', [$startDate, $endDate]);
    }
}
