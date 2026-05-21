<?php

namespace App\Http\Requests\Pendapatan;

use App\Models\PenerimaanPenjualan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePenerimaanPendapatanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var PenerimaanPenjualan|int|string $penerimaanPenjualan */
        $penerimaanPenjualan = $this->route('penerimaanPenjualan');
        $id = $penerimaanPenjualan instanceof PenerimaanPenjualan
            ? $penerimaanPenjualan->id
            : (int) $penerimaanPenjualan;

        return [
            'pelanggan_id' => ['required', 'integer', 'exists:pelanggan,id'],
            'akun_bank_id' => ['required', 'integer', 'exists:coa,id'],
            'akun_piutang_id' => ['required', 'integer', 'exists:coa,id'],
            'akun_selisih_tarif_id' => ['nullable', 'integer', 'exists:coa,id'],
            'nomer' => ['required', 'string', 'max:50', Rule::unique('penerimaan_penjualan', 'nomer')->ignore($id)],
            'tanggal' => ['required', 'date'],
            'jumlah_pembayaran' => ['required', 'numeric', 'min:0'],
            'selisih_tarif' => ['nullable', 'numeric'],
            'keterangan' => ['nullable', 'string', 'max:255'],
            'rincian' => ['required', 'array', 'min:1'],
            'rincian.*.faktur_penjualan_id' => ['required', 'integer', 'exists:faktur_penjualan,id'],
            'rincian.*.nominal_bayar' => ['required', 'numeric', 'min:0'],
            'rincian.*.check' => ['nullable'],
            'submit_action' => ['nullable', 'string'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                $checkedRows = collect($this->input('rincian', []))
                    ->filter(fn ($row) => filter_var($row['check'] ?? false, FILTER_VALIDATE_BOOLEAN));

                if ($checkedRows->isEmpty()) {
                    $validator->errors()->add('rincian', 'Minimal satu faktur harus dicentang.');
                    return;
                }

                $totalRincian = (float) $checkedRows->sum(fn ($row) => (float) ($row['nominal_bayar'] ?? 0));
                $selisihTarif = (float) $this->input('selisih_tarif', 0);
                $jumlahPembayaran = (float) $this->input('jumlah_pembayaran', 0);

                if (abs($jumlahPembayaran - ($totalRincian + $selisihTarif)) > 0.00001) {
                    $validator->errors()->add('jumlah_pembayaran', 'Jumlah pembayaran harus sama dengan total rincian terpilih ditambah selisih tarif.');
                }

                if ($selisihTarif != 0 && empty($this->input('akun_selisih_tarif_id'))) {
                    $validator->errors()->add('akun_selisih_tarif_id', 'Akun selisih tarif wajib dipilih jika selisih tarif diisi.');
                }
            },
        ];
    }
}
