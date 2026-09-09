<?php

namespace App\Services\Bridging;

use App\Exceptions\BillingApiException;
use App\Models\BukuBesar;
use App\Models\Coa;
use App\Models\FakturPenjualan;
use App\Models\FakturPenjualanRinci;
use App\Models\Pelanggan;
use App\Models\SimrsImportPendapatan;
use App\Services\Bukubesar\BukuBesarService;
use App\Services\LogAktifitasService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class BillingPendapatanInvoiceImportService
{
    private const IMPORT_INVOICE_PENDAPATAN = 'Invoice Pendapatan';

    private const COA_PIUTANG_UMUM = 'Piutang Pasien Umum';

    private const COA_PIUTANG_BPJS = 'Piutang Pasien BPJS Kesehatan';

    private const COA_PIUTANG_ASURANSI = 'Piutang Asuransi (Non BPJS Kesehatan)';

    public function __construct(
        private readonly BillingPendapatanApiService $billingPendapatanApiService,
        private readonly BillingApiClient $billingApiClient,
        private readonly BukuBesarService $bukuBesarService,
        private readonly LogAktifitasService $logService,
    ) {}

    public function imporBanyak(
        array $selectedExternalIds,
        string $jenisLayanan,
        string $startDate,
        string $endDate,
        ?string $spesialisId,
        ?string $dokterId,
        string $actor,
    ): array {
        $kandidat = $this->billingPendapatanApiService->getKandidatUntukImpor(
            $jenisLayanan,
            $startDate,
            $endDate,
            $spesialisId,
            $dokterId,
        )->groupBy('external_id');

        $hasil = [];

        foreach (array_values(array_unique($selectedExternalIds)) as $externalId) {
            $externalId = trim((string) $externalId);
            $candidateRows = $kandidat->get($externalId, collect());

            if ($candidateRows->count() !== 1) {
                $hasil[] = [
                    'no_rawat' => $externalId,
                    'berhasil' => false,
                    'alasan_gagal' => $candidateRows->isEmpty()
                        ? 'Data terpilih tidak ditemukan pada hasil Billing API untuk filter ini.'
                        : 'ID data Billing API tidak unik.',
                ];

                continue;
            }

            $billing = $candidateRows->first();

            try {
                $hasil[] = $this->imporSatu($billing, $actor);
            } catch (BillingApiException|RuntimeException $exception) {
                $hasil[] = $this->failedResult($billing, $exception->getMessage());
            } catch (Throwable $exception) {
                Log::error('Impor invoice pendapatan Billing API gagal.', [
                    'exception_class' => $exception::class,
                ]);

                $hasil[] = $this->failedResult(
                    $billing,
                    'Impor gagal diproses. Silakan coba kembali.',
                );
            }
        }

        return $hasil;
    }

    private function imporSatu(array $billing, string $actor): array
    {
        $noRawat = trim((string) ($billing['no_rawat'] ?? ''));

        if ($noRawat === '') {
            throw new RuntimeException('Nomor rawat dari Billing API tidak tersedia.');
        }

        $this->pastikanBelumDiimpor($noRawat);

        $tanggalRegistrasi = $this->normalisasiTanggal($billing['tanggal_registrasi'] ?? null);
        $rincianInvoice = $this->siapkanRincianInvoice(
            $this->billingApiClient->getAkun((string) $billing['external_id']),
        );
        $grandtotal = round((float) collect($rincianInvoice)->sum('subtotal'), 2);

        if ($grandtotal <= 0) {
            throw new RuntimeException('Total invoice harus lebih besar dari nol.');
        }

        $akunPiutang = $this->tentukanAkunPiutang((string) ($billing['penjamin'] ?? ''));

        DB::transaction(function () use (
            $billing,
            $noRawat,
            $tanggalRegistrasi,
            $rincianInvoice,
            $grandtotal,
            $akunPiutang,
            $actor,
        ): void {
            $this->pastikanBelumDiimpor($noRawat);

            $pelanggan = $this->cariAtauBuatPelanggan(
                $noRawat,
                trim((string) ($billing['nama_pasien'] ?? '')),
            );

            $invoice = new FakturPenjualan;
            $invoice->pelanggan_id = (int) $pelanggan->id;
            $invoice->akun_piutang_id = (int) $akunPiutang->id;
            $invoice->nomor_faktur = $noRawat;
            $invoice->tanggal_faktur = $tanggalRegistrasi;
            $invoice->keterangan = $this->buatNarasi($billing);
            $invoice->grandtotal = $grandtotal;
            $invoice->sudah_terbayar = 0;
            $invoice->status_proses = 0;
            $invoice->created_by = $actor;
            $invoice->updated_by = $actor;
            $invoice->nama_poli = (string) ($billing['nama_poli'] ?? '');
            $invoice->nama_dokter = (string) ($billing['nama_dokter'] ?? '');
            $invoice->nama_pasien = (string) ($billing['nama_pasien'] ?? '');
            $invoice->nomer_rawat = $noRawat;
            $invoice->tanggal_registrasi = $tanggalRegistrasi;
            $invoice->kode_penjamin = (string) ($billing['penjamin'] ?? '');
            $invoice->nama_penjamin = (string) ($billing['penjamin'] ?? '');
            $invoice->save();

            foreach ($rincianInvoice as $rincian) {
                $detail = new FakturPenjualanRinci;
                $detail->faktur_penjualan_id = (int) $invoice->id;
                $detail->harga = $rincian['harga'];
                $detail->kuantitas = $rincian['kuantitas'];
                $detail->subtotal = $rincian['subtotal'];
                $detail->catatan = $rincian['catatan'];
                $detail->save();
            }

            $this->sinkronkanBukuBesar(
                $invoice,
                $akunPiutang,
                $rincianInvoice,
                $grandtotal,
            );

            SimrsImportPendapatan::query()->create([
                'nomer_billing' => $noRawat,
                'tanggal_reg' => $tanggalRegistrasi,
                'user_importer' => $actor,
                'import_time' => now(),
                'dokter' => (string) ($billing['nama_dokter'] ?? ''),
                'nama_pasien' => (string) ($billing['nama_pasien'] ?? ''),
                'penjamin' => (string) ($billing['penjamin'] ?? ''),
                'poli' => (string) ($billing['nama_poli'] ?? ''),
                'status_layanan' => (string) ($billing['status_lanjut'] ?? ''),
                'total_tagihan' => $grandtotal,
                'kode_penjamin' => (string) ($billing['penjamin'] ?? ''),
                'import_ke' => self::IMPORT_INVOICE_PENDAPATAN,
            ]);

            $this->logService->log('Bridging Pendapatan', 'create', null, [
                'no_rawat' => $noRawat,
                'jenis_proses' => self::IMPORT_INVOICE_PENDAPATAN,
                'total_biaya' => $grandtotal,
            ]);
        });

        return [
            'no_rawat' => $noRawat,
            'berhasil' => true,
            'alasan_gagal' => null,
        ];
    }

    private function siapkanRincianInvoice(array $rincianApi): array
    {
        if ($rincianApi === []) {
            throw new RuntimeException('Rincian akun Billing API tidak ditemukan.');
        }

        $barisValid = [];

        foreach ($rincianApi as $index => $row) {
            if (! is_array($row)) {
                throw new RuntimeException(sprintf('Rincian akun baris %d tidak valid.', $index + 1));
            }

            $kodeAkun = trim((string) ($row['akun'] ?? ''));
            $biaya = $row['biaya'] ?? null;
            $jumlah = $row['jml'] ?? null;

            if ($kodeAkun === '') {
                throw new RuntimeException(sprintf('Kode akun baris %d tidak tersedia.', $index + 1));
            }

            if (! is_numeric($biaya) || ! is_numeric($jumlah)) {
                throw new RuntimeException(sprintf('Nominal akun %s tidak valid.', $kodeAkun));
            }

            $subtotal = round((float) $biaya * (float) $jumlah, 2);
            if (! is_finite($subtotal)) {
                throw new RuntimeException(sprintf('Nominal akun %s tidak valid.', $kodeAkun));
            }

            if ($subtotal == 0.0) {
                continue;
            }

            $barisValid[] = [
                'kode_akun' => $kodeAkun,
                'harga' => (float) $biaya,
                'kuantitas' => (float) $jumlah,
                'subtotal' => $subtotal,
                'job' => filled($row['job'] ?? null) ? trim((string) $row['job']) : null,
            ];
        }

        if ($barisValid === []) {
            throw new RuntimeException('Rincian akun Billing API tidak memiliki nominal yang dapat diinvoice.');
        }

        $coaLookup = $this->muatCoaPendapatan(array_column($barisValid, 'kode_akun'));

        return collect($barisValid)
            ->map(function (array $row) use ($coaLookup): array {
                /** @var Coa $coa */
                $coa = $coaLookup->get($row['kode_akun']);

                return [
                    'coa_id' => (int) $coa->id,
                    'harga' => $row['harga'],
                    'kuantitas' => $row['kuantitas'],
                    'subtotal' => $row['subtotal'],
                    'catatan' => $row['job'] ?? 'Billing API - '.$row['kode_akun'],
                ];
            })
            ->values()
            ->all();
    }

    private function muatCoaPendapatan(array $kodeAkun): Collection
    {
        $kodeUnik = collect($kodeAkun)
            ->map(fn (mixed $kode) => trim((string) $kode))
            ->filter(fn (string $kode) => $kode !== '')
            ->unique()
            ->values();

        $coaDitemukan = Coa::query()
            ->withCount('children')
            ->where(function ($query) use ($kodeUnik): void {
                foreach ($kodeUnik as $kode) {
                    $query->orWhereRaw('TRIM(kode) = ?', [$kode]);
                }
            })
            ->get()
            ->filter(function (Coa $coa) use ($kodeUnik): bool {
                $kodeCoa = trim((string) $coa->kode);

                return $kodeUnik->contains(fn (mixed $kode) => $kode === $kodeCoa);
            })
            ->groupBy(fn (Coa $coa) => trim((string) $coa->kode));

        $missing = $kodeUnik->reject(fn (string $kode) => $coaDitemukan->has($kode));
        if ($missing->isNotEmpty()) {
            throw new RuntimeException('COA pendapatan tidak ditemukan untuk akun: '.$missing->implode(', ').'.');
        }

        $duplicates = $kodeUnik->filter(fn (string $kode) => $coaDitemukan->get($kode)->count() > 1);
        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException('Kode COA pendapatan tidak unik untuk akun: '.$duplicates->implode(', ').'.');
        }

        $invalid = $kodeUnik->filter(function (string $kode) use ($coaDitemukan): bool {
            /** @var Coa $coa */
            $coa = $coaDitemukan->get($kode)->first();

            return (int) $coa->status_aktif !== 1
                || ! (bool) $coa->is_postable
                || (int) $coa->children_count > 0
                || ! str_contains(Str::lower((string) $coa->tipe_coa), 'pendapatan');
        });

        if ($invalid->isNotEmpty()) {
            throw new RuntimeException(
                'COA pendapatan tidak aktif, tidak postable, bukan akun leaf, atau bukan tipe pendapatan: '
                .$invalid->implode(', ').'.',
            );
        }

        return $coaDitemukan->map(fn (Collection $items) => $items->first());
    }

    private function tentukanAkunPiutang(string $penjamin): Coa
    {
        $penjaminNormal = Str::lower(trim($penjamin));
        $namaCoa = match ($penjaminNormal) {
            '', 'u/px' => self::COA_PIUTANG_UMUM,
            'bpjs' => self::COA_PIUTANG_BPJS,
            default => self::COA_PIUTANG_ASURANSI,
        };

        $daftarCoa = Coa::query()
            ->withCount('children')
            ->whereRaw('LOWER(TRIM(nama)) = ?', [Str::lower($namaCoa)])
            ->get();

        if ($daftarCoa->isEmpty()) {
            throw new RuntimeException('COA piutang tidak ditemukan: '.$namaCoa.'.');
        }

        if ($daftarCoa->count() > 1) {
            throw new RuntimeException('Nama COA piutang tidak unik: '.$namaCoa.'.');
        }

        /** @var Coa $coa */
        $coa = $daftarCoa->first();
        if ((int) $coa->status_aktif !== 1
            || ! (bool) $coa->is_postable
            || (int) $coa->children_count > 0
            || ! str_contains(Str::lower((string) $coa->tipe_coa), 'piutang')) {
            throw new RuntimeException(
                'COA piutang tidak aktif, tidak postable, bukan akun leaf, atau bukan tipe piutang: '.$namaCoa.'.',
            );
        }

        return $coa;
    }

    private function cariAtauBuatPelanggan(string $noRawat, string $namaPasien): Pelanggan
    {
        $pelanggan = Pelanggan::query()
            ->where('kode_pelanggan', $noRawat)
            ->first();

        if ($pelanggan !== null) {
            return $pelanggan;
        }

        $pelanggan = new Pelanggan;
        $pelanggan->status_aktif = true;
        $pelanggan->kode_pelanggan = $noRawat;
        $pelanggan->nama_pelanggan = $namaPasien !== '' ? $namaPasien : $noRawat;
        $pelanggan->save();

        return $pelanggan;
    }

    private function sinkronkanBukuBesar(
        FakturPenjualan $invoice,
        Coa $akunPiutang,
        array $rincianInvoice,
        float $grandtotal,
    ): void {
        $this->bukuBesarService->deleteBySource(
            self::IMPORT_INVOICE_PENDAPATAN,
            (int) $invoice->id,
        );

        $tanggal = $invoice->tanggal_faktur->format('Y-m-d');
        $payload = [[
            'coa_id' => (int) $akunPiutang->id,
            'sumber_id' => (int) $invoice->id,
            'tanggal' => $tanggal,
            ...BukuBesarService::resolvePeriode($tanggal),
            'nomer' => $invoice->nomor_faktur,
            'sumber_transaksi' => self::IMPORT_INVOICE_PENDAPATAN,
            'nominal' => $grandtotal,
            'tipe_mutasi' => 'D',
            'keterangan' => 'Akun piutang pendapatan',
            'created_at' => now(),
            'updated_at' => now(),
        ]];

        foreach ($rincianInvoice as $rincian) {
            $payload[] = [
                'coa_id' => (int) $rincian['coa_id'],
                'sumber_id' => (int) $invoice->id,
                'tanggal' => $tanggal,
                ...BukuBesarService::resolvePeriode($tanggal),
                'nomer' => $invoice->nomor_faktur,
                'sumber_transaksi' => self::IMPORT_INVOICE_PENDAPATAN,
                'nominal' => abs((float) $rincian['subtotal']),
                'tipe_mutasi' => (float) $rincian['subtotal'] < 0 ? 'D' : 'K',
                'keterangan' => $rincian['catatan'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $totalDebit = collect($payload)
            ->where('tipe_mutasi', 'D')
            ->sum('nominal');
        $totalKredit = collect($payload)
            ->where('tipe_mutasi', 'K')
            ->sum('nominal');

        if (abs($totalDebit - $totalKredit) > 0.01) {
            throw new RuntimeException(sprintf(
                'Buku besar invoice tidak balance. Total debit %.2f dan kredit %.2f.',
                $totalDebit,
                $totalKredit,
            ));
        }

        BukuBesar::query()->insert($payload);
    }

    private function pastikanBelumDiimpor(string $noRawat): void
    {
        if (SimrsImportPendapatan::query()->where('nomer_billing', $noRawat)->exists()
            || FakturPenjualan::query()
                ->where('nomor_faktur', $noRawat)
                ->orWhere('nomer_rawat', $noRawat)
                ->exists()) {
            throw new RuntimeException('No rawat ini sudah pernah diimport atau sudah menjadi invoice pendapatan.');
        }
    }

    private function normalisasiTanggal(mixed $tanggal): string
    {
        $value = trim((string) $tanggal);

        $formats = [
            ['/^(\d{4})-(\d{2})-(\d{2})(?:$|[T\s])/', 1, 2, 3],
            ['/^(\d{4})\/(\d{2})\/(\d{2})(?:$|[T\s])/', 1, 2, 3],
            ['/^(\d{2})[-\/](\d{2})[-\/](\d{4})(?:$|[T\s])/', 3, 2, 1],
        ];

        foreach ($formats as [$pattern, $yearIndex, $monthIndex, $dayIndex]) {
            if (! preg_match($pattern, $value, $parts)) {
                continue;
            }

            $year = (int) $parts[$yearIndex];
            $month = (int) $parts[$monthIndex];
            $day = (int) $parts[$dayIndex];

            if (checkdate($month, $day, $year)) {
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
        }

        throw new RuntimeException('Tanggal registrasi dari Billing API tidak valid.');
    }

    private function buatNarasi(array $billing): string
    {
        return sprintf(
            'Bridging invoice pendapatan Billing API %s %s - %s',
            (string) ($billing['status_lanjut'] ?? ''),
            (string) ($billing['no_rawat'] ?? ''),
            (string) ($billing['nama_pasien'] ?? ''),
        );
    }

    private function failedResult(array $billing, string $message): array
    {
        return [
            'no_rawat' => (string) ($billing['no_rawat'] ?? $billing['external_id'] ?? '-'),
            'berhasil' => false,
            'alasan_gagal' => $message,
        ];
    }
}
