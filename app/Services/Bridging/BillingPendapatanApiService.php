<?php

namespace App\Services\Bridging;

use App\Models\SimrsImportPendapatan;
use Illuminate\Support\Collection;

class BillingPendapatanApiService
{
    public const RAWAT_JALAN = 'rawat_jalan';

    public const IGD = 'igd';

    public function __construct(
        private readonly BillingApiClient $billingApiClient,
    ) {}

    public function getDokterOptions(): Collection
    {
        return collect($this->billingApiClient->getDokter())
            ->filter(fn (mixed $row) => is_array($row) && filled($row['ID'] ?? null))
            ->map(fn (array $row) => [
                'id' => (string) $row['ID'],
                'nama' => (string) ($row['Dokter'] ?? ''),
            ])
            ->sortBy('nama', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    public function getSpesialisOptions(): Collection
    {
        return collect($this->billingApiClient->getSpesialis())
            ->filter(fn (mixed $row) => is_array($row) && filled($row['ID'] ?? null))
            ->map(fn (array $row) => [
                'id' => (string) $row['ID'],
                'nama' => (string) ($row['Spesialis'] ?? ''),
            ])
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
    ): Collection {
        return $this->ambilKandidat(
            $jenisLayanan,
            $startDate,
            $endDate,
            $spesialisId,
            $dokterId,
            true,
        );
    }

    public function getKandidatUntukImpor(
        string $jenisLayanan,
        string $startDate,
        string $endDate,
        ?string $spesialisId = null,
        ?string $dokterId = null,
    ): Collection {
        return $this->ambilKandidat(
            $jenisLayanan,
            $startDate,
            $endDate,
            $spesialisId,
            $dokterId,
            false,
        );
    }

    private function ambilKandidat(
        string $jenisLayanan,
        string $startDate,
        string $endDate,
        ?string $spesialisId,
        ?string $dokterId,
        bool $excludeImported,
    ): Collection {
        $rows = match ($jenisLayanan) {
            self::RAWAT_JALAN => $this->billingApiClient->getRawatJalan(
                $startDate,
                $endDate,
                $spesialisId,
                $dokterId,
            ),
            self::IGD => $this->billingApiClient->getIgd($startDate, $endDate),
            default => [],
        };

        $nomorRawatTerimpor = $excludeImported
            ? SimrsImportPendapatan::query()
                ->pluck('nomer_billing')
                ->filter()
                ->mapWithKeys(fn (mixed $nomor) => [(string) $nomor => true])
            : collect();

        return collect($rows)
            ->filter(fn (mixed $row) => is_array($row)
                && filled($row['ID'] ?? null)
                && filled($row['RegNum'] ?? null))
            ->map(fn (array $row) => $this->normalisasiKunjungan($row, $jenisLayanan))
            ->reject(fn (array $row) => $nomorRawatTerimpor->has($row['no_rawat']))
            ->values();
    }

    private function normalisasiKunjungan(array $row, string $jenisLayanan): array
    {
        $isIgd = $jenisLayanan === self::IGD;

        return [
            'external_id' => trim((string) ($row['ID'] ?? '')),
            'no_rawat' => trim((string) $row['RegNum']),
            'tanggal_registrasi' => $this->tanggalTanpaJam($row['Tanggal'] ?? ''),
            'nama_pasien' => (string) ($row['Nama'] ?? ''),
            'nama_dokter' => (string) ($row['Dokter'] ?? ''),
            'nama_poli' => $isIgd ? 'IGD' : (string) ($row['SubLayanan'] ?? ''),
            'status_lanjut' => $isIgd ? 'IGD' : 'Rawat Jalan',
            'penjamin' => trim((string) ($row['PxRS'] ?? '')),
        ];
    }

    private function tanggalTanpaJam(mixed $tanggal): string
    {
        return preg_split('/[T\s]/', trim((string) $tanggal), 2)[0] ?? '';
    }
}
