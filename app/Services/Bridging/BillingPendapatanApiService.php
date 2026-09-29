<?php

namespace App\Services\Bridging;

use App\Models\SimrsImportPendapatan;
use Illuminate\Support\Collection;

class BillingPendapatanApiService
{
    public const RAWAT_JALAN = 'rawat_jalan';

    public const IGD = 'igd';

    public const RAWAT_INAP = 'rawat_inap';

    public function __construct(
        private readonly BillingApiClient $billingApiClient,
    ) {}

    public function getDokterOptions(): Collection
    {
        return collect($this->billingApiClient->getDokter())
            ->filter(fn (mixed $row) => is_array($row) && filled($row['id'] ?? null))
            ->map(fn (array $row) => [
                'id' => (string) $row['id'],
                'nama' => (string) ($row['nama'] ?? ''),
            ])
            ->sortBy('nama', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    public function getPoliOptions(): Collection
    {
        return collect($this->billingApiClient->getPoli())
            ->filter(fn (mixed $row) => is_array($row) && filled($row['id'] ?? null))
            ->map(fn (array $row) => [
                'id' => (string) $row['id'],
                'nama' => (string) ($row['nama'] ?? ''),
            ])
            ->sortBy('nama', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /** @deprecated Use getPoliOptions(). */
    public function getSpesialisOptions(): Collection
    {
        return $this->getPoliOptions();
    }

    public function getPenjaminOptions(): Collection
    {
        return collect($this->billingApiClient->getPenjamin())
            ->filter(fn (mixed $row) => is_array($row)
                && filled($row['id'] ?? null)
                && filled($row['nama'] ?? null))
            ->map(function (array $row): array {
                $name = trim((string) $row['nama']);

                return [
                    'id' => (string) $row['id'],
                    'nama' => strcasecmp($name, 'UMUM') === 0 ? 'Umum' : $name,
                ];
            })
            ->unique(fn (array $row) => mb_strtolower($row['nama']))
            ->sortBy('nama', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    public function getRincianAkun(string $externalId): Collection
    {
        return collect($this->billingApiClient->getAkun($externalId))
            ->filter(fn (mixed $row) => is_array($row))
            ->map(function (array $row): array {
                $biaya = is_numeric($row['biaya'] ?? null) ? (float) $row['biaya'] : null;
                $jumlah = is_numeric($row['jml'] ?? null) ? (float) $row['jml'] : null;

                return [
                    'akun' => trim((string) ($row['akun'] ?? '')),
                    'biaya' => $biaya,
                    'jumlah' => $jumlah,
                    'job' => filled($row['job'] ?? null) ? (string) $row['job'] : null,
                    'subtotal' => $biaya !== null && $jumlah !== null
                        ? round($biaya * $jumlah, 2)
                        : null,
                ];
            })
            ->values();
    }

    public function getKandidat(
        string $jenisLayanan,
        string $startDate,
        string $endDate,
        ?string $spesialisId = null,
        ?string $dokterId = null,
        ?string $penjamin = null,
    ): Collection {
        return $this->ambilKandidat(
            $jenisLayanan,
            $startDate,
            $endDate,
            $spesialisId,
            $dokterId,
            $penjamin,
            true,
        );
    }

    public function getKandidatUntukImpor(
        string $jenisLayanan,
        string $startDate,
        string $endDate,
        ?string $spesialisId = null,
        ?string $dokterId = null,
        ?string $penjamin = null,
    ): Collection {
        return $this->ambilKandidat(
            $jenisLayanan,
            $startDate,
            $endDate,
            $spesialisId,
            $dokterId,
            $penjamin,
            false,
        );
    }

    private function ambilKandidat(
        string $jenisLayanan,
        string $startDate,
        string $endDate,
        ?string $spesialisId,
        ?string $dokterId,
        ?string $penjamin,
        bool $excludeImported,
    ): Collection {
        $rows = match ($jenisLayanan) {
            self::RAWAT_JALAN => $this->billingApiClient->getRawatJalan($startDate, $endDate),
            self::IGD => $this->billingApiClient->getIgd($startDate, $endDate),
            self::RAWAT_INAP => $this->billingApiClient->getRawatInap($startDate, $endDate),
            default => [],
        };

        $nomorRawatTerimpor = $excludeImported
            ? SimrsImportPendapatan::query()
                ->pluck('nomer_billing')
                ->filter()
                ->mapWithKeys(fn (mixed $nomor) => [(string) $nomor => true])
            : collect();

        $penjaminFilter = mb_strtolower($this->normalisasiNamaPenjamin($penjamin));

        return collect($rows)
            ->filter(fn (mixed $row) => is_array($row)
                && filled($row['id'] ?? null))
            ->when(
                filled($spesialisId),
                fn (Collection $collection) => $collection->filter(
                    fn (array $row) => (string) ($row['poliId'] ?? '') === (string) $spesialisId,
                ),
            )
            ->when(
                filled($dokterId),
                fn (Collection $collection) => $collection->filter(
                    fn (array $row) => (string) ($row['dokterId'] ?? '') === (string) $dokterId,
                ),
            )
            ->map(fn (array $row) => $this->normalisasiKunjungan($row, $jenisLayanan))
            ->when(
                $penjaminFilter !== '',
                fn (Collection $collection) => $collection->filter(
                    fn (array $row) => mb_strtolower($this->normalisasiNamaPenjamin($row['penjamin'])) === $penjaminFilter,
                ),
            )
            ->reject(fn (array $row) => $nomorRawatTerimpor->has($row['no_rawat']))
            ->values();
    }

    private function normalisasiNamaPenjamin(?string $penjamin): string
    {
        $nama = trim((string) $penjamin);

        return strcasecmp($nama, 'UMUM') === 0 ? 'Umum' : $nama;
    }

    private function normalisasiKunjungan(array $row, string $jenisLayanan): array
    {
        $isIgd = $jenisLayanan === self::IGD;
        $isRawatInap = $jenisLayanan === self::RAWAT_INAP;
        $externalId = trim((string) ($row['id'] ?? ''));
        $nomorRawat = trim((string) ($row['pxNo'] ?? $externalId));

        return [
            'external_id' => $externalId,
            'no_rawat' => $nomorRawat,
            'nomer_rekam_medis' => trim((string) ($row['nomorRm'] ?? '')),
            'tanggal_registrasi' => $this->tanggalTanpaJam($row['tanggal'] ?? ''),
            'nama_pasien' => (string) ($row['nama'] ?? ''),
            'nama_dokter' => (string) ($row['dokterNama'] ?? ''),
            'nama_poli' => $isIgd ? 'IGD' : (string) ($row['poliNama'] ?? ''),
            'status_lanjut' => match (true) {
                $isIgd => 'IGD',
                $isRawatInap => 'Rawat Inap',
                default => 'Rawat Jalan',
            },
            'penjamin' => trim((string) ($row['penjaminNama'] ?? '')),
            'no_sep' => trim((string) ($row[$isIgd ? 'noSEP' : 'noSep'] ?? '')),
        ];
    }

    private function tanggalTanpaJam(mixed $tanggal): string
    {
        return preg_split('/[T\s]/', trim((string) $tanggal), 2)[0] ?? '';
    }
}
