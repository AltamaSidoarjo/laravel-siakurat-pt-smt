<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $table = 'supplier';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'status_aktif',
        'kode_supplier',
        'nama_supplier',
        'email_supplier',
        'alamat_supplier',
        'kategori_supplier',
    ];

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

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status_aktif', true);
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        $search = trim((string) $search);

        return $query->when($search !== '', function (Builder $query) use ($search): void {
            $query->where(function (Builder $query) use ($search): void {
                $query->where('kode_supplier', 'like', '%'.$search.'%')
                    ->orWhere('nama_supplier', 'like', '%'.$search.'%')
                    ->orWhere('kategori_supplier', 'like', '%'.$search.'%')
                    ->orWhere('email_supplier', 'like', '%'.$search.'%');
            });
        });
    }
}
