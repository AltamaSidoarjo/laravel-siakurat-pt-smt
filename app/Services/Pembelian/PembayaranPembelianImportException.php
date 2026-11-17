<?php

namespace App\Services\Pembelian;

use RuntimeException;

class PembayaranPembelianImportException extends RuntimeException
{
    /**
     * @param  list<string>  $errors
     */
    public function __construct(private readonly array $errors)
    {
        parent::__construct($errors[0] ?? 'Import pembayaran pembelian gagal.');
    }

    /**
     * @return list<string>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
