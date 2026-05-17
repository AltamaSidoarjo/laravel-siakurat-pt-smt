<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogHapusImportPendapatan extends Model
{
    protected $table = 'log_hapus_import_pendapatan';

    protected $primaryKey = 'log_hapus_import_pendapatan_id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'nomer',
        'dihapus_oleh',
        'created_at',
        'sumber_transaksi',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
