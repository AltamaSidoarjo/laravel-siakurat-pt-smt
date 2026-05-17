<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FakturPenjualanRinci extends Model
{
    protected $table = 'faktur_penjualan_rinci';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $casts = [
        'faktur_penjualan_id' => 'integer',
        'kuantitas' => 'decimal:2',
        'diskon_persen' => 'decimal:2',
        'diskon_rupiah' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'harga' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function fakturPenjualan()
    {
        return $this->belongsTo(FakturPenjualan::class, 'faktur_penjualan_id');
    }
}
