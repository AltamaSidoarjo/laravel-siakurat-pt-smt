<?php

namespace App\Services\Bridging;

use App\Exceptions\BillingApiException;
use App\Models\Coa;
use App\Models\JurnalUmum;
use App\Models\JurnalUmumRinci;
use App\Models\SimrsImportPendapatan;
use App\Services\Bukubesar\BukuBesarService;
use App\Services\LogAktifitasService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class BillingPendapatanJournalImportService
{
    private const IMPORT_JURNAL_UMUM = 'Jurnal Umum';

    private const DEBIT_TYPE_KEYWORDS = [
        'kasbank',
        'aktiva',
        'aset',
        'piutang',
        'persediaan',
        'beban',
        'biaya',
    ];

    private const CREDIT_TYPE_KEYWORDS = [
        'hutang',
        'utang',
        'kewajiban',
        'ekuitas',
        'modal',
        'pendapatan',
    ];

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
                Log::error('Impor jurnal pendapatan Billing API gagal.', [
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
        $rincianApi = $this->billingApiClient->getAkun((string) $billing['external_id']);
        $rincianJurnal = $this->siapkanRincianJurnal($rincianApi);
        $totalDebit = round((float) collect($rincianJurnal)->sum('debit'), 2);
        $totalKredit = round((float) collect($rincianJurnal)->sum('kredit'), 2);

        if (abs($totalDebit - $totalKredit) > 0.01) {
            throw new RuntimeException(sprintf(
                'Jurnal tidak balance. Total debit %.2f dan kredit %.2f.',
                $totalDebit,
                $totalKredit,
            ));
        }

        DB::transaction(function () use (
            $billing,
            $noRawat,
            $tanggalRegistrasi,
            $rincianJurnal,
            $totalDebit,
            $totalKredit,
            $actor,
        ): void {
            $this->pastikanBelumDiimpor($noRawat);

            $jurnal = JurnalUmum::query()->create([
                'nomer' => $noRawat,
                'tanggal' => $tanggalRegistrasi,
                'keterangan' => $this->buatNarasi($billing),
                'debit' => $totalDebit,
                'kredit' => $totalKredit,
            ]);

            foreach ($rincianJurnal as $rincian) {
                JurnalUmumRinci::query()->create([
                    'jurnal_umum_id' => (int) $jurnal->id,
                    ...$rincian,
                ]);
            }

            $this->bukuBesarService->syncFromJurnalUmum(
                (int) $jurnal->id,
                $noRawat,
                $tanggalRegistrasi,
                (string) $jurnal->keterangan,
                $rincianJurnal,
            );

            SimrsImportPendapatan::query()->create([
                'nomer_billing' => $noRawat,
                'tanggal_reg' => $tanggalRegistrasi,
                'user_importer' => $actor,
                'import_time' => now(),
                'dokter' => (string) ($billing['nama_dokter'] ?? ''),
                'nama_pasien' => (string) ($billing['nama_pasien'] ?? ''),
                'penjamin' => null,
                'poli' => (string) ($billing['nama_poli'] ?? ''),
                'status_layanan' => (string) ($billing['status_lanjut'] ?? ''),
                'total_tagihan' => $totalDebit,
                'alamat' => null,
                'jam_reg' => null,
                'kode_dokter' => null,
                'kode_penjamin' => null,
                'kode_poli' => null,
                'nama_kabupaten' => null,
                'nama_kecamatan' => null,
                'nama_kelurahan' => null,
                'no_rekam_medis' => null,
                'import_ke' => self::IMPORT_JURNAL_UMUM,
            ]);

            $this->logService->log('Bridging Pendapatan', 'create', null, [
                'no_rawat' => $noRawat,
                'jenis_proses' => self::IMPORT_JURNAL_UMUM,
                'total_biaya' => $totalDebit,
            ]);
        });

        return [
            'no_rawat' => $noRawat,
            'berhasil' => true,
            'alasan_gagal' => null,
        ];
    }

    private function siapkanRincianJurnal(array $rincianApi): array
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

            $nominal = round((float) $biaya * (float) $jumlah, 2);
            if (! is_finite($nominal)) {
                throw new RuntimeException(sprintf('Nominal akun %s tidak valid.', $kodeAkun));
            }

            if ($nominal == 0.0) {
                continue;
            }

            $barisValid[] = [
                'kode_akun' => $kodeAkun,
                'nominal' => $nominal,
                'job' => filled($row['job'] ?? null) ? trim((string) $row['job']) : null,
            ];
        }

        if ($barisValid === []) {
            throw new RuntimeException('Rincian akun Billing API tidak memiliki nominal yang dapat dijurnal.');
        }

        $coaLookup = $this->muatCoa(array_column($barisValid, 'kode_akun'));

        return collect($barisValid)
            ->map(function (array $row) use ($coaLookup): array {
                /** @var Coa $coa */
                $coa = $coaLookup->get($row['kode_akun']);
                $normalSide = $this->tentukanSaldoNormal((string) $coa->tipe_coa, $row['kode_akun']);
                $side = $row['nominal'] < 0 ? ($normalSide === 'D' ? 'K' : 'D') : $normalSide;
                $nominal = abs((float) $row['nominal']);

                return [
                    'coa_id' => (int) $coa->id,
                    'debit' => $side === 'D' ? $nominal : 0,
                    'kredit' => $side === 'K' ? $nominal : 0,
                    'catatan' => $row['job'] ?? 'Billing API - '.$row['kode_akun'],
                ];
            })
            ->values()
            ->all();
    }

    private function muatCoa(array $kodeAkun): Collection
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

                return $kodeUnik->contains(
                    fn (mixed $kode) => $kode === $kodeCoa,
                );
            })
            ->groupBy(fn (Coa $coa) => trim((string) $coa->kode));

        $missing = $kodeUnik->reject(fn (string $kode) => $coaDitemukan->has($kode));
        if ($missing->isNotEmpty()) {
            throw new RuntimeException('COA tidak ditemukan untuk akun: '.$missing->implode(', ').'.');
        }

        $duplicates = $kodeUnik->filter(fn (string $kode) => $coaDitemukan->get($kode)->count() > 1);
        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException('Kode COA tidak unik untuk akun: '.$duplicates->implode(', ').'.');
        }

        $invalid = $kodeUnik->filter(function (string $kode) use ($coaDitemukan): bool {
            /** @var Coa $coa */
            $coa = $coaDitemukan->get($kode)->first();

            return (int) $coa->status_aktif !== 1
                || ! (bool) $coa->is_postable
                || (int) $coa->children_count > 0;
        });

        if ($invalid->isNotEmpty()) {
            throw new RuntimeException(
                'COA tidak aktif, tidak postable, atau bukan akun leaf: '.$invalid->implode(', ').'.',
            );
        }

        return $coaDitemukan->map(fn (Collection $items) => $items->first());
    }

    private function tentukanSaldoNormal(string $tipeCoa, string $kodeAkun): string
    {
        $tipe = Str::lower(trim($tipeCoa));

        foreach (self::DEBIT_TYPE_KEYWORDS as $keyword) {
            if (str_contains($tipe, $keyword)) {
                return 'D';
            }
        }

        foreach (self::CREDIT_TYPE_KEYWORDS as $keyword) {
            if (str_contains($tipe, $keyword)) {
                return 'K';
            }
        }

        throw new RuntimeException(sprintf(
            'Tipe COA %s untuk akun %s tidak dikenali.',
            $tipeCoa !== '' ? $tipeCoa : '(kosong)',
            $kodeAkun,
        ));
    }

    private function pastikanBelumDiimpor(string $noRawat): void
    {
        if (SimrsImportPendapatan::query()->where('nomer_billing', $noRawat)->exists()
            || JurnalUmum::query()->where('nomer', $noRawat)->exists()) {
            throw new RuntimeException('No rawat ini sudah pernah diimport atau sudah menjadi nomor jurnal.');
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
            'Bridging pendapatan Billing API %s %s - %s',
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
