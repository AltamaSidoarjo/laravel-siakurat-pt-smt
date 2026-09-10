<?php

namespace App\Http\Requests\Pendapatan;

use App\Models\Coa;
use App\Models\FakturPenjualan;
use App\Models\Pelanggan;
use App\Models\Pelaksana;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoicePendapatanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return $this->invoiceRules([
            Rule::unique('faktur_penjualan', 'nomor_faktur'),
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
            'nomor_faktur' => ['required', 'string', 'max:255', ...$uniqueRule],
            'tanggal_faktur' => ['required', 'date'],
            'pelanggan_id' => ['required', 'integer', 'exists:pelanggan,id'],
            'akun_piutang_id' => ['required', 'integer', 'exists:coa,id'],
            'nama_pasien' => ['required', 'string', 'max:255'],
            'nomer_rekam_medis' => ['nullable', 'string', 'max:255'],
            'nama_dokter' => ['nullable', 'string', 'max:255'],
            'nama_poli' => ['nullable', 'string', 'max:255'],
            'keterangan' => ['nullable', 'string', 'max:255'],
            'nomer_rawat' => ['prohibited'],
            'kode_dokter' => ['prohibited'],
            'kode_poli' => ['prohibited'],
            'kode_penjamin' => ['prohibited'],
            'nama_penjamin' => ['prohibited'],
            'tanggal_registrasi' => ['prohibited'],
            'rincian' => ['required', 'array', 'min:1'],
            'rincian.*.id' => ['nullable', 'integer'],
            'rincian.*.coa_id' => ['required', 'integer', 'exists:coa,id'],
            'rincian.*.pelaksana_id' => ['nullable', 'integer', 'exists:pelaksana,id'],
            'rincian.*.kode_proyek' => ['nullable', 'string', 'max:255'],
            'rincian.*.kuantitas' => ['required', 'numeric', 'gt:0'],
            'rincian.*.harga' => ['required', 'numeric', 'gt:0'],
            'rincian.*.catatan' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function validateCoaTypes($validator): void
    {
        $this->validateCoa($validator, (int) $this->input('akun_piutang_id'), 'akun piutang', 'akun_piutang_id', true);

        foreach ($this->input('rincian', []) as $index => $row) {
            $this->validateCoa($validator, (int) ($row['coa_id'] ?? 0), 'pendapatan', "rincian.$index.coa_id", true);
        }
    }

    private function validateCoa($validator, int $coaId, string $type, string $attribute, bool $exactType = false): void
    {
        if ($coaId === 0) {
            return;
        }

        $coa = Coa::query()->withCount('children')->find($coaId);
        if ($coa === null) {
            return;
        }

        $matchesType = $exactType
            ? strtolower((string) $coa->tipe_coa) === $type
            : str_contains(strtolower((string) $coa->tipe_coa), $type);

        if ((int) $coa->status_aktif !== 1
            || ! (bool) $coa->is_postable
            || (int) $coa->children_count > 0
            || ! $matchesType) {
            $validator->errors()->add($attribute, 'Akun yang dipilih tidak valid untuk invoice pendapatan.');
        }
    }

    protected function validateActiveReferences($validator): void
    {
        $invoice = $this->route('fakturPenjualan');
        $pelangganId = (int) $this->input('pelanggan_id');
        $pelangganAktif = Pelanggan::query()->active()->whereKey($pelangganId)->exists();

        if (! $pelangganAktif && ! ($invoice instanceof FakturPenjualan && (int) $invoice->pelanggan_id === $pelangganId)) {
            $validator->errors()->add('pelanggan_id', 'Penjamin yang dipilih tidak aktif.');
        }

        $existingDetails = $invoice instanceof FakturPenjualan
            ? $invoice->rincian()->get(['id', 'pelaksana_id'])->keyBy('id')
            : collect();

        foreach ($this->input('rincian', []) as $index => $row) {
            $pelaksanaId = (int) ($row['pelaksana_id'] ?? 0);
            if ($pelaksanaId === 0 || Pelaksana::query()->active()->whereKey($pelaksanaId)->exists()) {
                continue;
            }

            $existing = $existingDetails->get((int) ($row['id'] ?? 0));
            if ($existing !== null && (int) $existing->pelaksana_id === $pelaksanaId) {
                continue;
            }

            $validator->errors()->add("rincian.$index.pelaksana_id", 'Pelaksana yang dipilih tidak aktif.');
        }
    }
}
