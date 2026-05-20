<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PembayaranPembelian extends Model
{
    protected $table = 'pembayaran_pembelian';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'supplier_id',
        'akun_bank_id',
        'akun_hutang_id',
        'akun_potongan_admin_id',
        'nomer_pembayaran',
        'tanggal',
        'total_bayar',
        'potongan_admin',
        'keterangan',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'supplier_id' => 'integer',
        'akun_bank_id' => 'integer',
        'akun_hutang_id' => 'integer',
        'akun_potongan_admin_id' => 'integer',
        'tanggal' => 'date',
        'total_bayar' => 'decimal:2',
        'potongan_admin' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function akunBank()
    {
        return $this->belongsTo(Coa::class, 'akun_bank_id');
    }

    public function akunHutang()
    {
        return $this->belongsTo(Coa::class, 'akun_hutang_id');
    }

    public function akunPotonganAdmin()
    {
        return $this->belongsTo(Coa::class, 'akun_potongan_admin_id');
    }

    public function rincian()
    {
        return $this->hasMany(PembayaranPembelianRinci::class, 'pembayaran_pembelian_id');
    }

    public function scopeBetweenDates(Builder $query, string $startDate, string $endDate): Builder
    {
        return $query->whereBetween('tanggal', [$startDate, $endDate]);
    }
}
