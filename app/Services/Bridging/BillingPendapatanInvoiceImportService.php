<?php

namespace App\Services\Bridging;

use App\Exceptions\BillingApiException;
use App\Models\Coa;
use App\Models\FakturPenjualan;
use App\Models\FakturPenjualanRinci;
use App\Models\MappingPenjaminPiutang;
use App\Models\Pelaksana;
use App\Models\SimrsImportPendapatan;
use App\Services\LogAktifitasService;
use App\Services\Pendapatan\InvoicePendapatanService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class BillingPendapatanInvoiceImportService
{
    private const IMPORT_INVOICE_PENDAPATAN = 'Invoice Pendapatan';

    public function __construct(
        private readonly BillingPendapatanApiService $billingPendapatanApiService,
        private readonly BillingApiClient $billingApiClient,
        private readonly LogAktifitasService $logService,
        private readonly InvoicePendapatanService $invoicePendapatanService,
    ) {}

    public function imporBanyak(
        array $selectedExternalIds,
        string $jenisLayanan,
        string $startDate,
        string $endDate,
        ?string $spesialisId,
        ?string $dokterId,
        ?string $penjamin,
        string $actor,
    ): array {
        $kandidat = $this->billingPendapatanApiService->getKandidatUntukImpor(
            $jenisLayanan,
            $startDate,
            $endDate,
            $spesialisId,
            $dokterId,
            $penjamin,
        )->groupBy('external_id');
        $penjaminApi = $this->billingPendapatanApiService->getPenjaminOptions()
            ->keyBy(fn (array $option) => Str::lower(trim($option['nama'])));
        $mappingPenjamin = MappingPenjaminPiutang::query()
            ->with(['coa' => fn ($query) => $query->withCount('children')])
            ->get()
            ->keyBy(fn (MappingPenjaminPiutang $mapping) => (string) $mapping->penjamin_id);

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

            $namaPenjamin = trim((string) ($billing['penjamin'] ?? ''));
            $namaPenjamin = Str::lower(
                $namaPenjamin === '' || strcasecmp($namaPenjamin, 'U/Px') === 0
                    ? 'Umum'
                    : $namaPenjamin,
            );
            $penjaminApiItem = $penjaminApi->get($namaPenjamin);
            if ($penjaminApiItem === null) {
                $hasil[] = $this->failedResult($billing, 'Penjamin tidak ditemukan pada Billing API.');

                continue;
            }
            $billing['penjamin_id'] = (string) $penjaminApiItem['id'];
            $mappingPiutang = $mappingPenjamin->get($billing['penjamin_id']);
            if ($mappingPiutang === null) {
                $hasil[] = $this->failedResult(
                    $billing,
                    'Mapping akun piutang untuk penjamin '.$penjaminApiItem['nama'].' belum disetting.',
                );

                continue;
            }

            try {
                $hasil[] = $this->imporSatu($billing, $mappingPiutang, $actor);
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

    private function imporSatu(
        array $billing,
        MappingPenjaminPiutang $mappingPiutang,
        string $actor,
    ): array {
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

        $penjamin = $this->normalisasiPenjamin((string) ($billing['penjamin'] ?? ''));
        $akunPiutang = $this->resolveAkunPiutang($mappingPiutang);

        DB::transaction(function () use (
            $billing,
            $noRawat,
            $tanggalRegistrasi,
            $rincianInvoice,
            $grandtotal,
            $akunPiutang,
            $penjamin,
            $actor,
        ): void {
            $this->pastikanBelumDiimpor($noRawat);

            $invoice = new FakturPenjualan;
            $pelanggan = $this->invoicePendapatanService->resolvePelangganFromApi(
                (string) $billing['penjamin_id'],
                $penjamin['nama'],
            );

            if ($pelanggan === null) {
                throw new RuntimeException('Data penjamin dari Billing API tidak lengkap.');
            }

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
            $invoice->kode_penjamin = (string) $billing['penjamin_id'];
            $invoice->nama_penjamin = $penjamin['nama'];
            $invoice->save();

            foreach ($rincianInvoice as $rincian) {
                $detail = new FakturPenjualanRinci;
                $detail->faktur_penjualan_id = (int) $invoice->id;
                $detail->pelaksana_id = $rincian['pelaksana_id'];
                $detail->kode_proyek = $rincian['kode_proyek'];
                $detail->coa_id = $rincian['coa_id'];
                $detail->harga = $rincian['harga'];
                $detail->kuantitas = $rincian['kuantitas'];
                $detail->subtotal = $rincian['subtotal'];
                $detail->catatan = $rincian['catatan'];
                $detail->save();
            }

            $this->invoicePendapatanService->syncLedger($invoice, $rincianInvoice);

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
            $kodeProyek = trim((string) ($row['job'] ?? ''));
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
                'job' => $kodeProyek !== '' ? $kodeProyek : null,
            ];
        }

        if ($barisValid === []) {
            throw new RuntimeException('Rincian akun Billing API tidak memiliki nominal yang dapat diinvoice.');
        }

        $coaLookup = $this->muatCoaPendapatan(array_column($barisValid, 'kode_akun'));
        $pelaksanaLookup = $this->muatPelaksanaAktif(array_column($barisValid, 'job'));

        return collect($barisValid)
            ->map(function (array $row) use ($coaLookup, $pelaksanaLookup): array {
                /** @var Coa $coa */
                $coa = $coaLookup->get($row['kode_akun']);
                /** @var Pelaksana|null $pelaksana */
                $pelaksana = $row['job'] === null
                    ? null
                    : $pelaksanaLookup->get($row['job']);

                return [
                    'coa_id' => (int) $coa->id,
                    'pelaksana_id' => $pelaksana?->id,
                    'kode_proyek' => $row['job'],
                    'harga' => $row['harga'],
                    'kuantitas' => $row['kuantitas'],
                    'subtotal' => $row['subtotal'],
                    'catatan' => $row['job'] ?? 'Billing API - '.$row['kode_akun'],
                ];
            })
            ->values()
            ->all();
    }

    private function muatPelaksanaAktif(array $kodeProyek): Collection
    {
        $kodeUnik = collect($kodeProyek)
            ->filter(fn (mixed $kode) => is_string($kode) && $kode !== '')
            ->uniqueStrict()
            ->values();

        if ($kodeUnik->isEmpty()) {
            return collect();
        }

        return Pelaksana::query()
            ->where('status_aktif', true)
            ->whereIn('no_proyek', $kodeUnik)
            ->get()
            ->filter(fn (Pelaksana $pelaksana) => $kodeUnik->containsStrict((string) $pelaksana->no_proyek))
            ->keyBy('no_proyek');
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

    private function resolveAkunPiutang(MappingPenjaminPiutang $mapping): Coa
    {
        /** @var Coa $coa */
        $coa = $mapping->coa;
        if ($coa === null
            || (int) $coa->status_aktif !== 1
            || (int) $coa->children_count > 0) {
            throw new RuntimeException(
                'COA pada mapping penjamin '.$mapping->nama_penjamin
                .' tidak aktif atau bukan akun leaf.',
            );
        }

        return $coa;
    }

    private function normalisasiPenjamin(string $penjamin): array
    {
        $nilaiPenjamin = trim($penjamin);

        if ($nilaiPenjamin === '' || Str::lower($nilaiPenjamin) === 'u/px') {
            return [
                'kode' => 'U/Px',
                'nama' => 'Umum',
            ];
        }

        return [
            'kode' => $nilaiPenjamin,
            'nama' => $nilaiPenjamin,
        ];
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
        $bagian = array_map(
            fn (mixed $nilai): string => trim((string) $nilai),
            [
                $billing['nama_poli'] ?? '',
                $billing['penjamin'] ?? '',
                $billing['external_id'] ?? '',
                $billing['nama_pasien'] ?? '',
            ],
        );

        return implode(' - ', array_filter($bagian, fn (string $nilai): bool => $nilai !== ''));
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
