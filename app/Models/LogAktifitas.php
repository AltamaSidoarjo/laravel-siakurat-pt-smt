<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogAktifitas extends Model
{
    protected $table = 'log_aktifitas';

    protected $fillable = [
        'nama_user',
        'modul',
        'tipe',
        'payload',
    ];
}
