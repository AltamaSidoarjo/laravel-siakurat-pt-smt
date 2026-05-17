<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PenerimaanPenjualanRinci extends Model
{
    protected $table = 'penerimaan_penjualan_rinci';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'penerimaan_penjualan_id',
        'faktur_penjualan_id',
        'nominal_bayar',
    ];

    protected $casts = [
        'penerimaan_penjualan_id' => 'integer',
        'faktur_penjualan_id' => 'integer',
        'nominal_bayar' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function penerimaanPenjualan()
    {
        return $this->belongsTo(PenerimaanPenjualan::class, 'penerimaan_penjualan_id');
    }

    public function fakturPenjualan()
    {
        return $this->belongsTo(FakturPenjualan::class, 'faktur_penjualan_id');
    }
}
