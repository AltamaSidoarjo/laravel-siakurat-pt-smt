<?php

namespace App\Services\Kasbank;

use App\Models\Coa;
use App\Models\KasbankPenerimaan;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class KasbankPenerimaanImportService
{
    /**
     * Urutan kolom pada sheet transaksi template import.
     *
     * @var list<string>
     */
    private const COLUMNS = ['nomer', 'tanggal', 'keterangan', 'kode_coa_kas', 'kode_coa_rincian', 'nominal', 'catatan'];

    public function __construct(
        private readonly KasbankPenerimaanService $kasbankPenerimaanService,
    ) {
    }

    /**
     * Buat file XLSX template import dan kembalikan path temporernya.
     *
     * @return array{output_path: string, download_name: string}
     */
    public function buildTemplate(): array
    {
        $spreadsheet = new Spreadsheet();

        $this->buildTransactionSheet($spreadsheet->getActiveSheet());
        $this->buildPetunjukSheet($spreadsheet->createSheet());
        $this->buildCoaSheet($spreadsheet->createSheet());

        $spreadsheet->setActiveSheetIndex(0);

        $outputDirectory = storage_path('app/temp/kasbank-penerimaan-import');
        File::ensureDirectoryExists($outputDirectory);
        $outputPath = $outputDirectory.DIRECTORY_SEPARATOR.Str::uuid()->toString().'.xlsx';

        $writer = new Xlsx($spreadsheet);
        $writer->save($outputPath);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return [
            'output_path' => $outputPath,
            'download_name' => 'template-import-kasbank-penerimaan.xlsx',
        ];
    }

    /**
     * Import penerimaan dari file XLSX. All-or-nothing: bila ada error, tidak ada data tersimpan.
     *
     * @return array{created: int, nomer: list<string>}
     *
     * @throws KasbankPenerimaanImportException
     */
    public function importFromXlsx(UploadedFile $file): array
    {
        $rows = $this->readRows($file);

        if ($rows === []) {
            throw new KasbankPenerimaanImportException(['File tidak berisi data transaksi.']);
        }

        $coaByKode = Coa::query()
            ->selectableTransaction()
            ->pluck('id', 'kode');

        $existingNomer = KasbankPenerimaan::query()->pluck('nomer')->flip();

        [$groups, $errors] = $this->groupAndValidate($rows, $coaByKode, $existingNomer);

        if ($errors !== []) {
            throw new KasbankPenerimaanImportException($errors);
        }

        $createdNomer = [];
        foreach ($groups as $payload) {
            $penerimaan = $this->kasbankPenerimaanService->create($payload);
            $createdNomer[] = $penerimaan->nomer;
        }

        return [
            'created' => count($createdNomer),
            'nomer' => $createdNomer,
        ];
    }

    /**
     * Baca baris data (tanpa header) dari sheet pertama.
     *
     * @return list<array{row: int, data: array<string, string>}>
     */
    private function readRows(UploadedFile $file): array
    {
        $path = $file->getRealPath();

        if ($path === false) {
            throw new KasbankPenerimaanImportException(['File sumber tidak dapat dibaca.']);
        }

        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($path);
        } catch (\Throwable $throwable) {
            throw new KasbankPenerimaanImportException(['File XLSX tidak dapat dibaca. Pastikan file valid.']);
        }

        $worksheet = $spreadsheet->getSheet(0);
        $rows = [];
        $rowNumber = 0;

        foreach ($worksheet->toArray(null, true, false, false) as $cells) {
            $rowNumber++;

            if ($rowNumber === 1) {
                continue; // lewati baris header
            }

            $data = [];
            foreach (self::COLUMNS as $index => $column) {
                $data[$column] = trim((string) ($cells[$index] ?? ''));
            }

            if ($this->isBlankRow($data)) {
                continue;
            }

            $rows[] = ['row' => $rowNumber, 'data' => $data];
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $rows;
    }

    /**
     * Kelompokkan baris per nomer dan validasi tiap grup.
     *
     * @param  list<array{row: int, data: array<string, string>}>  $rows
     * @return array{0: array<string, array<string, mixed>>, 1: list<string>}
     */
    private function groupAndValidate(array $rows, $coaByKode, $existingNomer): array
    {
        $errors = [];
        $grouped = [];

        foreach ($rows as $entry) {
            $line = $entry['row'];
            $data = $entry['data'];
            $nomer = $data['nomer'];

            if ($nomer === '') {
                $errors[] = "Baris {$line}: kolom nomer wajib diisi.";
                continue;
            }

            $kodeKas = $data['kode_coa_kas'];
            if ($kodeKas === '') {
                $errors[] = "Baris {$line}: kolom kode_coa_kas wajib diisi.";
                continue;
            }

            if (! $coaByKode->has($kodeKas)) {
                $errors[] = "Baris {$line}: kode_coa_kas '{$kodeKas}' tidak ditemukan atau bukan akun transaksi (leaf aktif).";
                continue;
            }

            $kodeRincian = $data['kode_coa_rincian'];
            if ($kodeRincian === '') {
                $errors[] = "Baris {$line}: kolom kode_coa_rincian wajib diisi.";
                continue;
            }

            if (! $coaByKode->has($kodeRincian)) {
                $errors[] = "Baris {$line}: kode_coa_rincian '{$kodeRincian}' tidak ditemukan atau bukan akun transaksi (leaf aktif).";
                continue;
            }

            $tanggal = $this->parseTanggal($data['tanggal']);
            if ($tanggal === null) {
                $errors[] = "Baris {$line}: tanggal '{$data['tanggal']}' tidak valid (format YYYY-MM-DD).";
                continue;
            }

            if (! $this->isNumeric($data['nominal'])) {
                $errors[] = "Baris {$line}: nominal harus berupa angka.";
                continue;
            }

            $nominal = (float) $data['nominal'];

            if ($nominal < 0) {
                $errors[] = "Baris {$line}: nominal tidak boleh negatif.";
                continue;
            }

            if (! isset($grouped[$nomer])) {
                if ($existingNomer->has($nomer)) {
                    $errors[] = "Baris {$line}: nomer '{$nomer}' sudah ada di database.";
                    continue;
                }

                $grouped[$nomer] = [
                    'nomer' => $nomer,
                    'tanggal' => $tanggal,
                    'keterangan' => $data['keterangan'] !== '' ? $data['keterangan'] : null,
                    'coa_id' => $coaByKode->get($kodeKas),
                    'kode_coa_kas' => $kodeKas,
                    'first_row' => $line,
                    'rincian' => [],
                    'total' => 0.0,
                ];
            } else {
                if ($grouped[$nomer]['tanggal'] !== $tanggal) {
                    $errors[] = sprintf(
                        "Baris %d: tanggal '%s' berbeda dengan baris pertama nomer '%s' (%s).",
                        $line,
                        $data['tanggal'],
                        $nomer,
                        $grouped[$nomer]['tanggal'],
                    );
                    continue;
                }

                if ($grouped[$nomer]['kode_coa_kas'] !== $kodeKas) {
                    $errors[] = sprintf(
                        "Baris %d: kode_coa_kas '%s' berbeda dengan baris pertama nomer '%s' (%s).",
                        $line,
                        $kodeKas,
                        $nomer,
                        $grouped[$nomer]['kode_coa_kas'],
                    );
                    continue;
                }
            }

            $grouped[$nomer]['rincian'][] = [
                'coa_id' => $coaByKode->get($kodeRincian),
                'nominal' => $nominal,
                'catatan' => $data['catatan'] !== '' ? $data['catatan'] : null,
            ];
            $grouped[$nomer]['total'] += $nominal;
        }

        foreach ($grouped as $nomer => $group) {
            if (round($group['total'], 2) <= 0) {
                $errors[] = sprintf(
                    "Penerimaan '%s': total (%s) harus lebih besar dari nol.",
                    $nomer,
                    number_format($group['total'], 2, ',', '.'),
                );
            }
        }

        $payloads = [];
        foreach ($grouped as $nomer => $group) {
            $payloads[$nomer] = [
                'coa_id' => $group['coa_id'],
                'nomer' => $group['nomer'],
                'tanggal' => $group['tanggal'],
                'keterangan' => $group['keterangan'],
                'total' => $group['total'],
                'rincian' => $group['rincian'],
            ];
        }

        return [$payloads, $errors];
    }

    private function buildTransactionSheet(Worksheet $sheet): void
    {
        $sheet->setTitle('Transaksi');

        foreach (self::COLUMNS as $index => $column) {
            $sheet->setCellValue([$index + 1, 1], $column);
        }

        $examples = [
            ['KBR-001', '2026-10-01', 'Terima pendapatan jasa', '1101', '4101', 500000, 'Pendapatan jasa'],
            ['KBR-001', '2026-10-01', 'Terima pendapatan jasa', '1101', '4102', 300000, 'Pendapatan lain'],
            ['KBR-002', '2026-10-02', 'Terima piutang', '1101', '1201', 1000000, ''],
        ];

        $rowNumber = 2;
        foreach ($examples as $example) {
            foreach ($example as $index => $value) {
                $sheet->setCellValue([$index + 1, $rowNumber], $value);
            }
            $rowNumber++;
        }

        $lastColumn = Coordinate::stringFromColumnIndex(count(self::COLUMNS));
        $headerRange = 'A1:'.$lastColumn.'1';
        $sheet->getStyle($headerRange)->getFont()->setBold(true);
        $sheet->getStyle($headerRange)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('D1E7DD');

        foreach (range(1, count(self::COLUMNS)) as $columnIndex) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($columnIndex))->setAutoSize(true);
        }
    }

    private function buildPetunjukSheet(Worksheet $sheet): void
    {
        $sheet->setTitle('Petunjuk');

        $lines = [
            'PETUNJUK IMPORT KASBANK PENERIMAAN',
            '',
            '1. Satu baris mewakili satu rincian (akun lawan) pada penerimaan.',
            '2. Baris dengan nomer yang sama akan digabung menjadi satu penerimaan.',
            '3. Kolom tanggal, keterangan, dan kode_coa_kas diambil dari baris pertama tiap nomer.',
            '4. Nilai tanggal & kode_coa_kas harus KONSISTEN (sama) untuk semua baris dengan nomer yang sama.',
            '5. Format tanggal: YYYY-MM-DD.',
            '6. Isi kode_coa_kas dengan akun kas/bank dan kode_coa_rincian dengan akun lawan dari sheet "Master COA" (hanya akun leaf/aktif).',
            '7. Kolom nominal diisi angka >= 0 pada setiap baris rincian.',
            '8. Total penerimaan (jumlah seluruh nominal rincian per nomer) harus lebih besar dari nol.',
            '9. Nomer penerimaan harus unik dan belum pernah dipakai di sistem.',
            '10. HAPUS baris contoh (KBR-001, KBR-002) pada sheet "Transaksi" sebelum mengunggah.',
            '11. Jika ada satu baris saja yang tidak valid, SELURUH import dibatalkan (tidak ada yang tersimpan).',
        ];

        foreach ($lines as $index => $line) {
            $sheet->setCellValue([1, $index + 1], $line);
        }

        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getColumnDimension('A')->setWidth(95);
    }

    private function buildCoaSheet(Worksheet $sheet): void
    {
        $sheet->setTitle('Master COA');
        $sheet->setCellValue('A1', 'kode');
        $sheet->setCellValue('B1', 'nama');

        $sheet->getStyle('A1:B1')->getFont()->setBold(true);
        $sheet->getStyle('A1:B1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('D1E7DD');

        $rowNumber = 2;
        foreach (Coa::query()->selectableTransaction()->get(['kode', 'nama']) as $coa) {
            $sheet->setCellValueExplicit([1, $rowNumber], (string) $coa->kode, DataType::TYPE_STRING);
            $sheet->setCellValue([2, $rowNumber], (string) $coa->nama);
            $rowNumber++;
        }

        $sheet->getColumnDimension('A')->setAutoSize(true);
        $sheet->getColumnDimension('B')->setAutoSize(true);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    }

    private function parseTanggal(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        try {
            $date = Carbon::createFromFormat('Y-m-d', $value);
        } catch (\Throwable) {
            return null;
        }

        if ($date === false || $date->format('Y-m-d') !== $value) {
            return null;
        }

        return $date->toDateString();
    }

    private function isNumeric(string $value): bool
    {
        return $value !== '' && is_numeric($value);
    }

    /**
     * @param  array<string, string>  $data
     */
    private function isBlankRow(array $data): bool
    {
        foreach ($data as $value) {
            if ($value !== '') {
                return false;
            }
        }

        return true;
    }
}
