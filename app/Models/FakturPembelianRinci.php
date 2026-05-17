<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FakturPembelianRinci extends Model
{
    protected $table = 'faktur_pembelian_rinci';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $casts = [
        'faktur_pembelian_id' => 'integer',
        'kuantitas' => 'decimal:2',
        'diskon_rupiah' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'harga_barang' => 'decimal:2',
        'total' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function fakturPembelian()
    {
        return $this->belongsTo(FakturPembelian::class, 'faktur_pembelian_id');
    }
}
