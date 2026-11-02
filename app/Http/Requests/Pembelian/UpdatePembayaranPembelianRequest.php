<?php

namespace App\Http\Requests\Pembelian;

use App\Models\FakturPembelian;
use App\Models\PembayaranPembelian;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePembayaranPembelianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var PembayaranPembelian $pembayaranPembelian */
        $pembayaranPembelian = $this->route('pembayaranPembelian');

        return [
            'supplier_id' => ['required', 'integer', 'exists:supplier,id'],
            'akun_bank_id' => ['required', 'integer', 'exists:coa,id'],
            'akun_hutang_id' => ['required', 'integer', 'exists:coa,id'],
            'akun_potongan_admin_id' => ['nullable', 'integer', 'exists:coa,id'],
            'nomer_pembayaran' => ['required', 'string', 'max:50', Rule::unique('pembayaran_pembelian', 'nomer_pembayaran')->ignore($pembayaranPembelian->id)],
            'tanggal' => ['required', 'date'],
            'total_bayar' => ['required', 'numeric', 'min:0'],
            'potongan_admin' => ['nullable', 'numeric', 'min:0'],
            'keterangan' => ['nullable', 'string', 'max:255'],
            'rincian' => ['required', 'array', 'min:1'],
            'rincian.*.faktur_pembelian_id' => ['required', 'integer', 'exists:faktur_pembelian,id'],
            'rincian.*.nominal_bayar' => ['required', 'numeric', 'min:0'],
            'rincian.*.check' => ['nullable'],
            'submit_action' => ['nullable', 'string'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                $rawRincian = $this->input('rincian', []);
                $checkedRows = collect($rawRincian)
                    ->filter(fn ($row) => filter_var($row['check'] ?? false, FILTER_VALIDATE_BOOLEAN));

                if ($checkedRows->isEmpty()) {
                    $validator->errors()->add('rincian', 'Minimal satu faktur harus dicentang.');
                    return;
                }

                $supplierId = (int) $this->input('supplier_id');
                $invoiceIds = $checkedRows->pluck('faktur_pembelian_id')->filter()->unique()->all();
                $invoices = FakturPembelian::query()->whereIn('id', $invoiceIds)->get()->keyBy('id');

                /** @var PembayaranPembelian|int|string $pembayaranPembelian */
                $pembayaranPembelian = $this->route('pembayaranPembelian');
                $currentModel = $pembayaranPembelian instanceof PembayaranPembelian
                    ? $pembayaranPembelian
                    : PembayaranPembelian::query()->find($pembayaranPembelian);

                $oldPaymentsByInvoice = $currentModel
                    ? $currentModel->rincian()->pluck('nominal_bayar', 'faktur_pembelian_id')->map(fn ($val) => (float) $val)->all()
                    : [];

                foreach ($rawRincian as $index => $row) {
                    if (! filter_var($row['check'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                        continue;
                    }

                    $invoiceId = (int) ($row['faktur_pembelian_id'] ?? 0);
                    /** @var FakturPembelian|null $faktur */
                    $faktur = $invoices->get($invoiceId);

                    if (! $faktur) {
                        continue;
                    }

                    if ((int) $faktur->supplier_id !== $supplierId) {
                        $validator->errors()->add(
                            "rincian.{$index}.faktur_pembelian_id",
                            "Faktur [{$faktur->nomer_faktur}] bukan milik supplier yang dipilih."
                        );
                    }

                    $oldPaid = (float) ($oldPaymentsByInvoice[$invoiceId] ?? 0);
                    $sisaTagihan = max(0, ((float) $faktur->grandtotal - (float) $faktur->sudah_terbayar) + $oldPaid);
                    $nominalBayar = (float) ($row['nominal_bayar'] ?? 0);

                    if ($nominalBayar - $sisaTagihan > 0.0001) {
                        $validator->errors()->add(
                            "rincian.{$index}.nominal_bayar",
                            "Nominal bayar faktur [{$faktur->nomer_faktur}] melebihi sisa tagihan (Rp " . number_format($sisaTagihan, 2, ',', '.') . ")."
                        );
                    }
                }

                $calculated = (float) $checkedRows->sum(fn ($row) => (float) ($row['nominal_bayar'] ?? 0));
                $total = (float) $this->input('total_bayar', 0);
                $potonganAdmin = (float) $this->input('potongan_admin', 0);
                $akunPotonganAdminId = $this->input('akun_potongan_admin_id');

                if (abs($calculated - $total) > 0.00001) {
                    $validator->errors()->add('total_bayar', 'Jumlah pembayaran harus sama dengan total rincian terpilih.');
                }

                if ($potonganAdmin > 0 && blank($akunPotonganAdminId)) {
                    $validator->errors()->add('akun_potongan_admin_id', 'Akun potongan admin wajib dipilih saat nominal potongan admin diisi.');
                }

                if ($potonganAdmin - $total > 0.00001) {
                    $validator->errors()->add('potongan_admin', 'Potongan admin tidak boleh lebih besar dari total bayar.');
                }
            },
        ];
    }
}
