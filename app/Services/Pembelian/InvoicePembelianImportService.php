<?php

namespace App\Services\Pembelian;

use App\Models\Coa;
use App\Models\FakturPembelian;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection as SupportCollection;
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

class InvoicePembelianImportService
{
    /**
     * Urutan kolom pada sheet transaksi template import.
     *
     * @var list<string>
     */
    private const COLUMNS = [
        'nomer_faktur',
        'tanggal_faktur',
        'tanggal_jatuh_tempo',
        'kode_supplier',
        'kode_coa_hutang',
        'kode_coa_rincian',
        'kuantitas',
        'harga',
        'catatan',
        'keterangan',
    ];

    public function __construct(
        private readonly InvoicePembelianService $invoicePembelianService,
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
        $this->buildSupplierSheet($spreadsheet->createSheet());
        $this->buildCoaSheet($spreadsheet->createSheet());

        $spreadsheet->setActiveSheetIndex(0);

        $outputDirectory = storage_path('app/temp/pembelian-invoice-import');
        File::ensureDirectoryExists($outputDirectory);
        $outputPath = $outputDirectory.DIRECTORY_SEPARATOR.Str::uuid()->toString().'.xlsx';

        $writer = new Xlsx($spreadsheet);
        $writer->save($outputPath);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return [
            'output_path' => $outputPath,
            'download_name' => 'template-import-invoice-pembelian.xlsx',
        ];
    }

    /**
     * Import invoice pembelian dari file XLSX. All-or-nothing: bila ada error, tidak ada data tersimpan.
     *
     * @return array{created: int, nomer: list<string>}
     *
     * @throws InvoicePembelianImportException
     */
    public function importFromXlsx(UploadedFile $file, string $actor = 'system'): array
    {
        $rows = $this->readRows($file);

        if ($rows === []) {
            throw new InvoicePembelianImportException(['File tidak berisi data transaksi.']);
        }

        $suppliersByKode = Supplier::query()
            ->where('status_aktif', true)
            ->get(['id', 'kode_supplier', 'nama_supplier'])
            ->keyBy('kode_supplier');

        $coaByKode = Coa::query()
            ->selectableTransaction()
            ->get(['id', 'kode', 'nama', 'tipe_coa'])
            ->keyBy('kode');

        $existingNomer = FakturPembelian::query()->pluck('nomer_faktur')->flip();

        [$groups, $errors] = $this->groupAndValidate($rows, $suppliersByKode, $coaByKode, $existingNomer);

        if ($errors !== []) {
            throw new InvoicePembelianImportException($errors);
        }

        $createdNomer = [];
        foreach ($groups as $payload) {
            $invoice = $this->invoicePembelianService->create($payload, $actor);
            $createdNomer[] = $invoice->nomer_faktur;
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
            throw new InvoicePembelianImportException(['File sumber tidak dapat dibaca.']);
        }

        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($path);
        } catch (\Throwable) {
            throw new InvoicePembelianImportException(['File XLSX tidak dapat dibaca. Pastikan file valid.']);
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
     * Kelompokkan baris per nomer_faktur dan validasi tiap grup.
     *
     * @param  list<array{row: int, data: array<string, string>}>  $rows
     * @param  SupportCollection<string, Supplier>  $suppliersByKode
     * @param  SupportCollection<string, Coa>  $coaByKode
     * @param  SupportCollection<string, int>  $existingNomer
     * @return array{0: array<string, array<string, mixed>>, 1: list<string>}
     */
    private function groupAndValidate(
        array $rows,
        SupportCollection $suppliersByKode,
        SupportCollection $coaByKode,
        SupportCollection $existingNomer,
    ): array {
        $errors = [];
        $grouped = [];

        foreach ($rows as $entry) {
            $line = $entry['row'];
            $data = $entry['data'];
            $nomer = $data['nomer_faktur'];

            if ($nomer === '') {
                $errors[] = "Baris {$line}: kolom nomer_faktur wajib diisi.";
                continue;
            }

            if ($existingNomer->has($nomer)) {
                $errors[] = "Baris {$line}: nomor faktur '{$nomer}' sudah ada di database.";
                continue;
            }

            $tanggalFaktur = $this->parseTanggal($data['tanggal_faktur']);
            if ($tanggalFaktur === null) {
                $errors[] = "Baris {$line}: tanggal_faktur '{$data['tanggal_faktur']}' tidak valid (format YYYY-MM-DD).";
                continue;
            }

            $tanggalJatuhTempo = null;
            if ($data['tanggal_jatuh_tempo'] !== '') {
                $tanggalJatuhTempo = $this->parseTanggal($data['tanggal_jatuh_tempo']);
                if ($tanggalJatuhTempo === null) {
                    $errors[] = "Baris {$line}: tanggal_jatuh_tempo '{$data['tanggal_jatuh_tempo']}' tidak valid (format YYYY-MM-DD).";
                    continue;
                }
            }

            $kodeSupplier = $data['kode_supplier'];
            if ($kodeSupplier === '') {
                $errors[] = "Baris {$line}: kolom kode_supplier wajib diisi.";
                continue;
            }

            $supplier = $suppliersByKode->get($kodeSupplier);
            if (! $supplier) {
                $errors[] = "Baris {$line}: kode_supplier '{$kodeSupplier}' tidak ditemukan atau tidak aktif.";
                continue;
            }

            $kodeCoaHutang = $data['kode_coa_hutang'];
            if ($kodeCoaHutang === '') {
                $errors[] = "Baris {$line}: kolom kode_coa_hutang wajib diisi.";
                continue;
            }

            $coaHutang = $coaByKode->get($kodeCoaHutang);
            if (! $coaHutang) {
                $errors[] = "Baris {$line}: kode_coa_hutang '{$kodeCoaHutang}' tidak ditemukan atau bukan akun leaf aktif.";
                continue;
            }

            if (! $this->isPayableCoa($coaHutang)) {
                $errors[] = "Baris {$line}: akun '{$kodeCoaHutang}' bukan akun bertipe hutang/liabilitas yang valid.";
                continue;
            }

            $kodeCoaRincian = $data['kode_coa_rincian'];
            if ($kodeCoaRincian === '') {
                $errors[] = "Baris {$line}: kolom kode_coa_rincian wajib diisi.";
                continue;
            }

            $coaRincian = $coaByKode->get($kodeCoaRincian);
            if (! $coaRincian) {
                $errors[] = "Baris {$line}: kode_coa_rincian '{$kodeCoaRincian}' tidak ditemukan atau bukan akun leaf aktif.";
                continue;
            }

            if (! $this->isNumeric($data['kuantitas']) || (float) $data['kuantitas'] <= 0) {
                $errors[] = "Baris {$line}: kuantitas harus berupa angka lebih besar dari nol.";
                continue;
            }

            if (! $this->isNumeric($data['harga']) || (float) $data['harga'] <= 0) {
                $errors[] = "Baris {$line}: harga harus berupa angka lebih besar dari nol.";
                continue;
            }

            $kuantitas = (float) $data['kuantitas'];
            $harga = (float) $data['harga'];
            $catatan = $data['catatan'] !== '' ? $data['catatan'] : null;

            if (! isset($grouped[$nomer])) {
                $grouped[$nomer] = [
                    'nomer_faktur' => $nomer,
                    'tanggal_faktur' => $tanggalFaktur,
                    'tanggal_jatuh_tempo' => $tanggalJatuhTempo,
                    'supplier_id' => $supplier->id,
                    'kode_supplier' => $kodeSupplier,
                    'akun_hutang_id' => $coaHutang->id,
                    'kode_coa_hutang' => $kodeCoaHutang,
                    'keterangan' => $data['keterangan'] !== '' ? $data['keterangan'] : null,
                    'rincian' => [],
                    'first_row' => $line,
                ];
            } else {
                // Validasi konsistensi header untuk baris dengan nomor faktur yang sama
                if ($grouped[$nomer]['tanggal_faktur'] !== $tanggalFaktur) {
                    $errors[] = "Baris {$line}: tanggal_faktur '{$data['tanggal_faktur']}' tidak sama dengan baris {$grouped[$nomer]['first_row']} untuk nomor faktur '{$nomer}'.";
                }
                if ($grouped[$nomer]['tanggal_jatuh_tempo'] !== $tanggalJatuhTempo) {
                    $errors[] = "Baris {$line}: tanggal_jatuh_tempo '{$data['tanggal_jatuh_tempo']}' tidak sama dengan baris {$grouped[$nomer]['first_row']} untuk nomor faktur '{$nomer}'.";
                }
                if ($grouped[$nomer]['kode_supplier'] !== $kodeSupplier) {
                    $errors[] = "Baris {$line}: kode_supplier '{$kodeSupplier}' tidak sama dengan baris {$grouped[$nomer]['first_row']} untuk nomor faktur '{$nomer}'.";
                }
                if ($grouped[$nomer]['kode_coa_hutang'] !== $kodeCoaHutang) {
                    $errors[] = "Baris {$line}: kode_coa_hutang '{$kodeCoaHutang}' tidak sama dengan baris {$grouped[$nomer]['first_row']} untuk nomor faktur '{$nomer}'.";
                }
            }

            $grouped[$nomer]['rincian'][] = [
                'coa_id' => $coaRincian->id,
                'kuantitas' => $kuantitas,
                'harga' => $harga,
                'catatan' => $catatan,
            ];
        }

        $payloads = [];
        foreach ($grouped as $nomer => $group) {
            if (empty($group['rincian'])) {
                $errors[] = "Nomor faktur '{$nomer}': rincian pembelian tidak boleh kosong.";
                continue;
            }

            $payloads[$nomer] = [
                'nomer_faktur' => $group['nomer_faktur'],
                'tanggal_faktur' => $group['tanggal_faktur'],
                'tanggal_jatuh_tempo' => $group['tanggal_jatuh_tempo'],
                'supplier_id' => $group['supplier_id'],
                'akun_hutang_id' => $group['akun_hutang_id'],
                'keterangan' => $group['keterangan'],
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
            ['INV-202611-001', '2026-11-01', '2026-11-30', 'SUP-001', '210001', '510101', 10, 50000, 'Paracetamol tablet', 'Faktur obat rutin'],
            ['INV-202611-001', '2026-11-01', '2026-11-30', 'SUP-001', '210001', '510102', 5, 80000, 'Amoxicillin kapsul', 'Faktur obat rutin'],
            ['INV-202611-002', '2026-11-02', '', 'SUP-002', '210001', '520101', 2, 250000, 'Kasa steril', 'Pembelian BHP medis'],
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
            'PETUNJUK IMPORT INVOICE PEMBELIAN',
            '',
            '1. Satu baris mewakili 1 baris rincian barang/biaya/persediaan pada faktur pembelian.',
            '2. Baris dengan nomer_faktur yang sama akan digabung menjadi satu invoice pembelian (dapat memiliki banyak rincian akun).',
            '3. Kolom tanggal_faktur, tanggal_jatuh_tempo, kode_supplier, kode_coa_hutang, dan keterangan diambil dari baris pertama tiap nomer_faktur.',
            '4. Nilai tanggal_faktur, tanggal_jatuh_tempo, kode_supplier, dan kode_coa_hutang harus KONSISTEN (sama) untuk semua baris dengan nomer_faktur yang sama.',
            '5. Format tanggal: YYYY-MM-DD.',
            '6. kode_supplier harus kode supplier aktif yang terdaftar di sistem (lihat sheet "Master Supplier").',
            '7. kode_coa_hutang harus kode akun bertipe hutang/liabilitas leaf aktif (lihat sheet "Master COA").',
            '8. kode_coa_rincian harus kode akun biaya/persediaan leaf aktif (lihat sheet "Master COA").',
            '9. kuantitas dan harga diisi angka desimal > 0.',
            '10. nomer_faktur harus unik dan belum pernah digunakan di sistem.',
            '11. HAPUS baris contoh pada sheet "Transaksi" sebelum mengunggah file.',
            '12. Jika terdapat 1 baris saja yang tidak valid, SELURUH import dibatalkan (all-or-nothing, tidak ada data parsial tersimpan).',
        ];

        foreach ($lines as $index => $line) {
            $sheet->setCellValue([1, $index + 1], $line);
        }

        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getColumnDimension('A')->setWidth(115);
    }

    private function buildSupplierSheet(Worksheet $sheet): void
    {
        $sheet->setTitle('Master Supplier');
        $sheet->setCellValue('A1', 'kode_supplier');
        $sheet->setCellValue('B1', 'nama_supplier');

        $sheet->getStyle('A1:B1')->getFont()->setBold(true);
        $sheet->getStyle('A1:B1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('D1E7DD');

        $rowNumber = 2;
        foreach (Supplier::query()->where('status_aktif', true)->orderBy('kode_supplier')->get(['kode_supplier', 'nama_supplier']) as $supplier) {
            $sheet->setCellValueExplicit([1, $rowNumber], (string) $supplier->kode_supplier, DataType::TYPE_STRING);
            $sheet->setCellValue([2, $rowNumber], (string) $supplier->nama_supplier);
            $rowNumber++;
        }

        $sheet->getColumnDimension('A')->setAutoSize(true);
        $sheet->getColumnDimension('B')->setAutoSize(true);
    }

    private function buildCoaSheet(Worksheet $sheet): void
    {
        $sheet->setTitle('Master COA');
        $sheet->setCellValue('A1', 'kode');
        $sheet->setCellValue('B1', 'nama');
        $sheet->setCellValue('C1', 'tipe_coa');

        $sheet->getStyle('A1:C1')->getFont()->setBold(true);
        $sheet->getStyle('A1:C1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('D1E7DD');

        $rowNumber = 2;
        foreach (Coa::query()->selectableTransaction()->get(['kode', 'nama', 'tipe_coa']) as $coa) {
            $sheet->setCellValueExplicit([1, $rowNumber], (string) $coa->kode, DataType::TYPE_STRING);
            $sheet->setCellValue([2, $rowNumber], (string) $coa->nama);
            $sheet->setCellValue([3, $rowNumber], (string) ($coa->tipe_coa ?? ''));
            $rowNumber++;
        }

        $sheet->getColumnDimension('A')->setAutoSize(true);
        $sheet->getColumnDimension('B')->setAutoSize(true);
        $sheet->getColumnDimension('C')->setAutoSize(true);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    }

    private function isPayableCoa(Coa $coa): bool
    {
        $lowerType = strtolower((string) $coa->tipe_coa);

        return str_contains($lowerType, 'hutang')
            || str_contains($lowerType, 'utang')
            || str_contains($lowerType, 'liabilitas');
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
