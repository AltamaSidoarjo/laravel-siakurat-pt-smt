<?php

namespace App\Services\Kasbank;

use RuntimeException;

class KasbankPenerimaanImportException extends RuntimeException
{
    /**
     * @param  list<string>  $errors
     */
    public function __construct(private readonly array $errors)
    {
        parent::__construct($errors[0] ?? 'Import gagal.');
    }

    /**
     * @return list<string>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
