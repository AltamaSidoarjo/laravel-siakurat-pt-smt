<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PembayaranPembelianRinci extends Model
{
    protected $table = 'pembayaran_pembelian_rinci';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'pembayaran_pembelian_id',
        'faktur_pembelian_id',
        'nominal_bayar',
    ];

    protected $casts = [
        'pembayaran_pembelian_id' => 'integer',
        'faktur_pembelian_id' => 'integer',
        'nominal_bayar' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function pembayaranPembelian()
    {
        return $this->belongsTo(PembayaranPembelian::class, 'pembayaran_pembelian_id');
    }

    public function fakturPembelian()
    {
        return $this->belongsTo(FakturPembelian::class, 'faktur_pembelian_id');
    }
}
