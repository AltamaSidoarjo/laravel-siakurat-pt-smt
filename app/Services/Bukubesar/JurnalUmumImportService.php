<?php

namespace App\Services\Bukubesar;

use App\Models\Coa;
use App\Models\JurnalUmum;
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

class JurnalUmumImportService
{
    /**
     * Urutan kolom pada sheet transaksi template import.
     *
     * @var list<string>
     */
    private const COLUMNS = ['nomer', 'tanggal', 'keterangan', 'kode_coa', 'debit', 'kredit', 'catatan'];

    public function __construct(
        private readonly JurnalUmumService $jurnalUmumService,
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

        $outputDirectory = storage_path('app/temp/jurnal-umum-import');
        File::ensureDirectoryExists($outputDirectory);
        $outputPath = $outputDirectory.DIRECTORY_SEPARATOR.Str::uuid()->toString().'.xlsx';

        $writer = new Xlsx($spreadsheet);
        $writer->save($outputPath);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return [
            'output_path' => $outputPath,
            'download_name' => 'template-import-jurnal-umum.xlsx',
        ];
    }

    /**
     * Import jurnal dari file XLSX. All-or-nothing: bila ada error, tidak ada data tersimpan.
     *
     * @return array{created: int, nomer: list<string>}
     *
     * @throws JurnalUmumImportException
     */
    public function importFromXlsx(UploadedFile $file): array
    {
        $rows = $this->readRows($file);

        if ($rows === []) {
            throw new JurnalUmumImportException(['File tidak berisi data transaksi.']);
        }

        $coaByKode = Coa::query()
            ->selectableTransaction()
            ->pluck('id', 'kode');

        $existingNomer = JurnalUmum::query()->pluck('nomer')->flip();

        [$groups, $errors] = $this->groupAndValidate($rows, $coaByKode, $existingNomer);

        if ($errors !== []) {
            throw new JurnalUmumImportException($errors);
        }

        $createdNomer = [];
        foreach ($groups as $payload) {
            $jurnal = $this->jurnalUmumService->create($payload);
            $createdNomer[] = $jurnal->nomer;
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
            throw new JurnalUmumImportException(['File sumber tidak dapat dibaca.']);
        }

        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($path);
        } catch (\Throwable $throwable) {
            throw new JurnalUmumImportException(['File XLSX tidak dapat dibaca. Pastikan file valid.']);
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

            $kodeCoa = $data['kode_coa'];
            if ($kodeCoa === '') {
                $errors[] = "Baris {$line}: kolom kode_coa wajib diisi.";
                continue;
            }

            if (! $coaByKode->has($kodeCoa)) {
                $errors[] = "Baris {$line}: kode_coa '{$kodeCoa}' tidak ditemukan atau bukan akun transaksi (leaf aktif).";
                continue;
            }

            $tanggal = $this->parseTanggal($data['tanggal']);
            if ($tanggal === null) {
                $errors[] = "Baris {$line}: tanggal '{$data['tanggal']}' tidak valid (format YYYY-MM-DD).";
                continue;
            }

            if (! $this->isNumeric($data['debit']) || ! $this->isNumeric($data['kredit'])) {
                $errors[] = "Baris {$line}: debit/kredit harus berupa angka.";
                continue;
            }

            $debit = (float) $data['debit'];
            $kredit = (float) $data['kredit'];

            if ($debit < 0 || $kredit < 0) {
                $errors[] = "Baris {$line}: debit/kredit tidak boleh negatif.";
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
                    'rincian' => [],
                    'total_debit' => 0.0,
                    'total_kredit' => 0.0,
                ];
            }

            $grouped[$nomer]['rincian'][] = [
                'coa_id' => $coaByKode->get($kodeCoa),
                'debit' => $debit,
                'kredit' => $kredit,
                'catatan' => $data['catatan'] !== '' ? $data['catatan'] : null,
            ];
            $grouped[$nomer]['total_debit'] += $debit;
            $grouped[$nomer]['total_kredit'] += $kredit;
        }

        foreach ($grouped as $nomer => $group) {
            if (round($group['total_debit'], 2) !== round($group['total_kredit'], 2)) {
                $errors[] = sprintf(
                    "Jurnal '%s': total debit (%s) tidak sama dengan total kredit (%s).",
                    $nomer,
                    number_format($group['total_debit'], 2, ',', '.'),
                    number_format($group['total_kredit'], 2, ',', '.'),
                );
            }
        }

        $payloads = [];
        foreach ($grouped as $nomer => $group) {
            $payloads[$nomer] = [
                'nomer' => $group['nomer'],
                'tanggal' => $group['tanggal'],
                'keterangan' => $group['keterangan'],
                'debit' => $group['total_debit'],
                'kredit' => $group['total_kredit'],
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
            ['JU-001', '2026-10-01', 'Pembayaran listrik', '5101', 500000, 0, 'Beban listrik'],
            ['JU-001', '2026-10-01', 'Pembayaran listrik', '1101', 0, 500000, 'Kas keluar'],
            ['JU-002', '2026-10-02', 'Setoran modal', '1101', 1000000, 0, ''],
            ['JU-002', '2026-10-02', 'Setoran modal', '3101', 0, 1000000, ''],
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
            'PETUNJUK IMPORT JURNAL UMUM',
            '',
            '1. Satu baris mewakili satu rincian (satu akun) pada jurnal.',
            '2. Baris dengan nomer yang sama akan digabung menjadi satu jurnal.',
            '3. Kolom tanggal & keterangan diambil dari baris pertama tiap nomer (format tanggal: YYYY-MM-DD).',
            '4. Isi kolom kode_coa dengan kode akun dari sheet "Master COA" (hanya akun leaf/aktif).',
            '5. Setiap baris isi salah satu: debit ATAU kredit (yang lain diisi 0).',
            '6. Total debit harus SAMA dengan total kredit untuk setiap nomer jurnal.',
            '7. Nomer jurnal harus unik dan belum pernah dipakai di sistem.',
            '8. HAPUS baris contoh (JU-001, JU-002) pada sheet "Transaksi" sebelum mengunggah.',
            '9. Jika ada satu baris saja yang tidak valid, SELURUH import dibatalkan (tidak ada yang tersimpan).',
        ];

        foreach ($lines as $index => $line) {
            $sheet->setCellValue([1, $index + 1], $line);
        }

        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getColumnDimension('A')->setWidth(90);
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
