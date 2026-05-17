<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $table = 'supplier';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $casts = [
        'status_aktif' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function fakturPembelians()
    {
        return $this->hasMany(FakturPembelian::class, 'supplier_id');
    }

    public function pembayaranPembelians()
    {
        return $this->hasMany(PembayaranPembelian::class, 'supplier_id');
    }
}
