<?php

namespace App\Http\Requests\Pembelian;

use App\Models\Coa;
use App\Models\FakturPembelian;
use App\Models\Supplier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoicePembelianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return $this->invoiceRules([
            Rule::unique('faktur_pembelian', 'nomer_faktur'),
        ]);
    }

    public function after(): array
    {
        return [
            $this->validateCoaTypes(...),
            $this->validateActiveReferences(...),
        ];
    }

    protected function invoiceRules(array $uniqueRule): array
    {
        return [
            'nomer_faktur' => ['required', 'string', 'max:255', ...$uniqueRule],
            'tanggal_faktur' => ['required', 'date'],
            'tanggal_jatuh_tempo' => ['nullable', 'date'],
            'supplier_id' => ['required', 'integer', 'exists:supplier,id'],
            'akun_hutang_id' => ['required', 'integer', 'exists:coa,id'],
            'keterangan' => ['nullable', 'string', 'max:255'],
            'rincian' => ['required', 'array', 'min:1'],
            'rincian.*.id' => ['nullable', 'integer'],
            'rincian.*.coa_id' => ['required', 'integer', 'exists:coa,id'],
            'rincian.*.kuantitas' => ['required', 'numeric', 'gt:0'],
            'rincian.*.harga' => ['required', 'numeric', 'gt:0'],
            'rincian.*.catatan' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function validateCoaTypes($validator): void
    {
        $this->validateCoa($validator, (int) $this->input('akun_hutang_id'), 'hutang', 'akun_hutang_id');

        foreach ($this->input('rincian', []) as $index => $row) {
            $this->validateCoa($validator, (int) ($row['coa_id'] ?? 0), null, "rincian.$index.coa_id");
        }
    }

    private function validateCoa($validator, int $coaId, ?string $typeKeyword, string $attribute): void
    {
        if ($coaId === 0) {
            return;
        }

        $coa = Coa::query()->withCount('children')->find($coaId);
        if ($coa === null) {
            return;
        }

        $typeMatches = true;
        if ($typeKeyword !== null) {
            $lowerType = strtolower((string) $coa->tipe_coa);
            $typeMatches = str_contains($lowerType, 'hutang')
                || str_contains($lowerType, 'utang')
                || str_contains($lowerType, 'liabilitas');
        }

        if ((int) $coa->status_aktif !== 1
            || (int) $coa->children_count > 0
            || ! $typeMatches) {
            $validator->errors()->add($attribute, 'Akun yang dipilih tidak valid untuk invoice pembelian.');
        }
    }

    protected function validateActiveReferences($validator): void
    {
        $invoice = $this->route('fakturPembelian');
        $supplierId = (int) $this->input('supplier_id');
        $supplierAktif = Supplier::query()->where('status_aktif', true)->whereKey($supplierId)->exists();

        if (! $supplierAktif && ! ($invoice instanceof FakturPembelian && (int) $invoice->supplier_id === $supplierId)) {
            $validator->errors()->add('supplier_id', 'Supplier yang dipilih tidak aktif.');
        }
    }
}
