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

    public function getPenjaminOptions(): Collection
    {
        return collect($this->billingApiClient->getPenjamin())
            ->filter(fn (mixed $row) => is_array($row)
                && filled($row['ID'] ?? null)
                && filled($row['PxRS'] ?? null))
            ->map(function (array $row): array {
                $name = trim((string) $row['PxRS']);

                return [
                    'id' => (string) $row['ID'],
                    'nama' => strcasecmp($name, 'U/Px') === 0 ? 'Umum' : $name,
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
            self::RAWAT_JALAN => $this->billingApiClient->getRawatJalan(
                $startDate,
                $endDate,
                $spesialisId,
                $dokterId,
            ),
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
                && filled($row['ID'] ?? null))
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

        return strcasecmp($nama, 'U/Px') === 0 ? 'Umum' : $nama;
    }

    private function normalisasiKunjungan(array $row, string $jenisLayanan): array
    {
        $isIgd = $jenisLayanan === self::IGD;
        $isRawatInap = $jenisLayanan === self::RAWAT_INAP;
        $externalId = trim((string) ($row['ID'] ?? ''));

        return [
            'external_id' => $externalId,
            'no_rawat' => $externalId,
            'nomer_rekam_medis' => trim((string) ($row['RegNum'] ?? '')),
            'tanggal_registrasi' => $this->tanggalTanpaJam($row['Tanggal'] ?? ''),
            'nama_pasien' => (string) ($row['Nama'] ?? ''),
            'nama_dokter' => (string) ($row['Dokter'] ?? ''),
            'nama_poli' => $isIgd ? 'IGD' : (string) ($row['SubLayanan'] ?? ''),
            'status_lanjut' => match (true) {
                $isIgd => 'IGD',
                $isRawatInap => 'Rawat Inap',
                default => 'Rawat Jalan',
            },
            'penjamin' => trim((string) ($row['PxRS'] ?? '')),
        ];
    }

    private function tanggalTanpaJam(mixed $tanggal): string
    {
        return preg_split('/[T\s]/', trim((string) $tanggal), 2)[0] ?? '';
    }
}
