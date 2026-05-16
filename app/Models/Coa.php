<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Coa extends Model
{
    protected $table = 'coa';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'status_aktif',
        'parent_coa',
        'tipe_coa',
        'kode',
        'nama',
        'deskripsi',
        'is_postable',
    ];

    protected $casts = [
        'status_aktif' => 'integer',
        'parent_coa' => 'integer',
        'is_postable' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_coa');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_coa');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status_aktif', 1);
    }
}
