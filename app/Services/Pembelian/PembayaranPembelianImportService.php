<?php

namespace App\Services\Pembelian;

use App\Models\Coa;
use App\Models\FakturPembelian;
use App\Models\PembayaranPembelian;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
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

class PembayaranPembelianImportService
{
    /**
     * Urutan kolom pada sheet transaksi template import.
     *
     * @var list<string>
     */
    private const COLUMNS = [
        'nomer_pembayaran',
        'tanggal',
        'kode_supplier',
        'kode_coa_bank',
        'kode_coa_hutang',
        'nomer_faktur',
        'nominal_bayar',
        'keterangan',
    ];

    public function __construct(
        private readonly PembayaranPembelianService $pembayaranPembelianService,
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
        $this->buildSupplierSheet($spreadsheet->createSheet());
        $this->buildFakturSheet($spreadsheet->createSheet());

        $spreadsheet->setActiveSheetIndex(0);

        $outputDirectory = storage_path('app/temp/pembelian-pembayaran-import');
        File::ensureDirectoryExists($outputDirectory);
        $outputPath = $outputDirectory.DIRECTORY_SEPARATOR.Str::uuid()->toString().'.xlsx';

        $writer = new Xlsx($spreadsheet);
        $writer->save($outputPath);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return [
            'output_path' => $outputPath,
            'download_name' => 'template-import-pembayaran-pembelian.xlsx',
        ];
    }

    /**
     * Import pembayaran pembelian dari file XLSX. All-or-nothing: bila ada error, tidak ada data tersimpan.
     *
     * @return array{created: int, nomer: list<string>}
     *
     * @throws PembayaranPembelianImportException
     */
    public function importFromXlsx(UploadedFile $file, string $actor = 'system'): array
    {
        $rows = $this->readRows($file);

        if ($rows === []) {
            throw new PembayaranPembelianImportException(['File tidak berisi data transaksi.']);
        }

        $suppliersByKode = Supplier::query()
            ->where('status_aktif', true)
            ->get(['id', 'kode_supplier', 'nama_supplier'])
            ->keyBy('kode_supplier');

        $coaByKode = Coa::query()
            ->selectableTransaction()
            ->get(['id', 'kode', 'nama'])
            ->keyBy('kode');

        $existingNomer = PembayaranPembelian::query()->pluck('nomer_pembayaran')->flip();

        $fakturNomers = collect($rows)->pluck('data.nomer_faktur')->filter()->unique()->values();
        $invoicesByNomer = FakturPembelian::query()
            ->whereIn('nomer_faktur', $fakturNomers)
            ->get(['id', 'supplier_id', 'akun_hutang_id', 'nomer_faktur', 'tanggal_faktur', 'grandtotal', 'sudah_terbayar', 'status_proses'])
            ->keyBy('nomer_faktur');

        [$groups, $errors] = $this->groupAndValidate($rows, $suppliersByKode, $coaByKode, $existingNomer, $invoicesByNomer);

        if ($errors !== []) {
            throw new PembayaranPembelianImportException($errors);
        }

        $createdNomer = [];
        foreach ($groups as $payload) {
            $pembayaran = $this->pembayaranPembelianService->create($payload, $actor);
            $createdNomer[] = $pembayaran->nomer_pembayaran;
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
            throw new PembayaranPembelianImportException(['File sumber tidak dapat dibaca.']);
        }

        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($path);
        } catch (\Throwable) {
            throw new PembayaranPembelianImportException(['File XLSX tidak dapat dibaca. Pastikan file valid.']);
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
     * Kelompokkan baris per nomer_pembayaran dan validasi tiap grup.
     *
     * @param  list<array{row: int, data: array<string, string>}>  $rows
     * @param  SupportCollection<string, Supplier>  $suppliersByKode
     * @param  SupportCollection<string, Coa>  $coaByKode
     * @param  SupportCollection<string, int>  $existingNomer
     * @param  SupportCollection<string, FakturPembelian>  $invoicesByNomer
     * @return array{0: array<string, array<string, mixed>>, 1: list<string>}
     */
    private function groupAndValidate(
        array $rows,
        SupportCollection $suppliersByKode,
        SupportCollection $coaByKode,
        SupportCollection $existingNomer,
        SupportCollection $invoicesByNomer,
    ): array {
        $errors = [];
        $grouped = [];

        // Lacak sisa saldo hutang kumulatif per faktur agar tidak melebihi sisa tagihan dalam batch import yang sama
        $remainingBalance = [];
        foreach ($invoicesByNomer as $inv) {
            $remainingBalance[$inv->id] = max((float) $inv->grandtotal - (float) $inv->sudah_terbayar, 0);
        }

        foreach ($rows as $entry) {
            $line = $entry['row'];
            $data = $entry['data'];
            $nomer = $data['nomer_pembayaran'];

            if ($nomer === '') {
                $errors[] = "Baris {$line}: kolom nomer_pembayaran wajib diisi.";
                continue;
            }

            if ($existingNomer->has($nomer)) {
                $errors[] = "Baris {$line}: nomor pembayaran '{$nomer}' sudah ada di database.";
                continue;
            }

            $tanggal = $this->parseTanggal($data['tanggal']);
            if ($tanggal === null) {
                $errors[] = "Baris {$line}: tanggal '{$data['tanggal']}' tidak valid (format YYYY-MM-DD).";
                continue;
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

            $kodeCoaBank = $data['kode_coa_bank'];
            if ($kodeCoaBank === '') {
                $errors[] = "Baris {$line}: kolom kode_coa_bank wajib diisi.";
                continue;
            }

            $coaBank = $coaByKode->get($kodeCoaBank);
            if (! $coaBank) {
                $errors[] = "Baris {$line}: kode_coa_bank '{$kodeCoaBank}' tidak ditemukan atau bukan akun leaf aktif.";
                continue;
            }

            $kodeCoaHutang = $data['kode_coa_hutang'];
            $coaHutang = null;
            if ($kodeCoaHutang !== '') {
                $coaHutang = $coaByKode->get($kodeCoaHutang);
                if (! $coaHutang) {
                    $errors[] = "Baris {$line}: kode_coa_hutang '{$kodeCoaHutang}' tidak ditemukan atau bukan akun leaf aktif.";
                    continue;
                }
            }

            $nomerFaktur = $data['nomer_faktur'];
            if ($nomerFaktur === '') {
                $errors[] = "Baris {$line}: kolom nomer_faktur wajib diisi.";
                continue;
            }

            $invoice = $invoicesByNomer->get($nomerFaktur);
            if (! $invoice) {
                $errors[] = "Baris {$line}: faktur pembelian dengan nomor '{$nomerFaktur}' tidak ditemukan.";
                continue;
            }

            if ((int) $invoice->supplier_id !== (int) $supplier->id) {
                $errors[] = "Baris {$line}: faktur '{$nomerFaktur}' bukan milik supplier '{$kodeSupplier}'.";
                continue;
            }

            if ((string) $invoice->status_proses === '1') {
                $errors[] = "Baris {$line}: faktur '{$nomerFaktur}' tidak dapat dibayar karena status_proses sedang aktif.";
                continue;
            }

            if (! $this->isNumeric($data['nominal_bayar'])) {
                $errors[] = "Baris {$line}: nominal_bayar harus berupa angka lebih besar dari nol.";
                continue;
            }

            $nominalBayar = (float) $data['nominal_bayar'];
            if ($nominalBayar <= 0) {
                $errors[] = "Baris {$line}: nominal_bayar harus lebih besar dari nol.";
                continue;
            }

            $currentRemaining = $remainingBalance[$invoice->id] ?? 0;
            if ($currentRemaining <= 0) {
                $errors[] = "Baris {$line}: faktur '{$nomerFaktur}' sudah lunas.";
                continue;
            }

            if ($nominalBayar > $currentRemaining + 0.00001) {
                $errors[] = sprintf(
                    "Baris %d: nominal bayar (%s) melebihi sisa hutang faktur '%s' (%s).",
                    $line,
                    number_format($nominalBayar, 0, ',', '.'),
                    $nomerFaktur,
                    number_format($currentRemaining, 0, ',', '.')
                );
                continue;
            }

            // Kurangi sisa saldo kumulatif untuk baris berikutnya
            $remainingBalance[$invoice->id] = max($currentRemaining - $nominalBayar, 0);

            if (! isset($grouped[$nomer])) {
                $grouped[$nomer] = [
                    'nomer_pembayaran' => $nomer,
                    'tanggal' => $tanggal,
                    'supplier_id' => $supplier->id,
                    'kode_supplier' => $kodeSupplier,
                    'akun_bank_id' => $coaBank->id,
                    'kode_coa_bank' => $kodeCoaBank,
                    'akun_hutang_id' => $coaHutang?->id,
                    'kode_coa_hutang' => $kodeCoaHutang,
                    'keterangan' => $data['keterangan'] !== '' ? $data['keterangan'] : null,
                    'total_bayar' => 0.0,
                    'faktur_ids' => [],
                    'rincian' => [],
                    'first_row' => $line,
                ];
            } else {
                // Validasi konsistensi header untuk nomor pembayaran yang sama
                if ($grouped[$nomer]['tanggal'] !== $tanggal) {
                    $errors[] = "Baris {$line}: tanggal '{$data['tanggal']}' tidak sama dengan baris {$grouped[$nomer]['first_row']} untuk nomor pembayaran '{$nomer}'.";
                }
                if ($grouped[$nomer]['kode_supplier'] !== $kodeSupplier) {
                    $errors[] = "Baris {$line}: kode_supplier '{$kodeSupplier}' tidak sama dengan baris {$grouped[$nomer]['first_row']} untuk nomor pembayaran '{$nomer}'.";
                }
                if ($grouped[$nomer]['kode_coa_bank'] !== $kodeCoaBank) {
                    $errors[] = "Baris {$line}: kode_coa_bank '{$kodeCoaBank}' tidak sama dengan baris {$grouped[$nomer]['first_row']} untuk nomor pembayaran '{$nomer}'.";
                }
                if ($grouped[$nomer]['kode_coa_hutang'] !== $kodeCoaHutang) {
                    $errors[] = "Baris {$line}: kode_coa_hutang '{$kodeCoaHutang}' tidak sama dengan baris {$grouped[$nomer]['first_row']} untuk nomor pembayaran '{$nomer}'.";
                }
            }

            if (isset($grouped[$nomer]['faktur_ids'][$invoice->id])) {
                $errors[] = "Baris {$line}: faktur '{$nomerFaktur}' tercantum ganda pada nomor pembayaran '{$nomer}'.";
                continue;
            }

            $grouped[$nomer]['faktur_ids'][$invoice->id] = $invoice->akun_hutang_id;
            $grouped[$nomer]['rincian'][] = [
                'faktur_pembelian_id' => $invoice->id,
                'nominal_bayar' => $nominalBayar,
                'check' => true,
            ];
            $grouped[$nomer]['total_bayar'] += $nominalBayar;
        }

        $payloads = [];
        foreach ($grouped as $nomer => $group) {
            if ($group['total_bayar'] <= 0) {
                $errors[] = "Nomor pembayaran '{$nomer}': total pembayaran harus lebih besar dari nol.";
                continue;
            }

            $resolvedAkunHutangId = $group['akun_hutang_id'];
            if ($resolvedAkunHutangId === null) {
                // Ambil akun hutang dari faktur-faktur yang dilunasi
                $invoiceHutangIds = collect($group['faktur_ids'])->values()->filter()->unique();
                if ($invoiceHutangIds->isEmpty()) {
                    $errors[] = "Nomor pembayaran '{$nomer}': faktur tidak memiliki akun hutang terdaftar. Isi kolom kode_coa_hutang.";
                    continue;
                }
                if ($invoiceHutangIds->count() > 1) {
                    $errors[] = "Nomor pembayaran '{$nomer}': faktur-faktur yang dilunasi memiliki akun hutang berbeda. Isi kolom kode_coa_hutang secara eksplisit.";
                    continue;
                }
                $resolvedAkunHutangId = (int) $invoiceHutangIds->first();
            }

            $payloads[$nomer] = [
                'supplier_id' => $group['supplier_id'],
                'akun_bank_id' => $group['akun_bank_id'],
                'akun_hutang_id' => $resolvedAkunHutangId,
                'akun_potongan_admin_id' => null,
                'nomer_pembayaran' => $group['nomer_pembayaran'],
                'tanggal' => $group['tanggal'],
                'total_bayar' => $group['total_bayar'],
                'potongan_admin' => 0,
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
            ['PB-001', '2026-10-01', 'SUP-001', '1101', '', 'INV-202610-001', 500000, 'Pembayaran faktur obat 1'],
            ['PB-001', '2026-10-01', 'SUP-001', '1101', '', 'INV-202610-002', 750000, 'Pembayaran faktur obat 2'],
            ['PB-002', '2026-10-02', 'SUP-002', '1101', '2101', 'INV-202610-003', 1200000, 'Pelunasan faktur alkes'],
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
            'PETUNJUK IMPORT PEMBAYARAN PEMBELIAN',
            '',
            '1. Satu baris mewakili pelunasan satu Faktur Pembelian.',
            '2. Baris dengan nomer_pembayaran yang sama akan digabung menjadi satu transaksi pembayaran (dapat melunasi lebih dari 1 faktur).',
            '3. Kolom tanggal, kode_supplier, kode_coa_bank, kode_coa_hutang, dan keterangan diambil dari baris pertama tiap nomer_pembayaran.',
            '4. Nilai tanggal, kode_supplier, dan kode_coa_bank harus KONSISTEN (sama) untuk semua baris dengan nomer_pembayaran yang sama.',
            '5. Format tanggal: YYYY-MM-DD.',
            '6. kode_supplier harus kode supplier aktif yang terdaftar di sistem (lihat sheet "Master Supplier").',
            '7. kode_coa_bank wajib diisi dengan kode akun kas/bank transaksi aktif (lihat sheet "Master COA").',
            '8. kode_coa_hutang bersifat opsional. Jika dikosongkan, sistem otomatis mengambil akun hutang dari Faktur Pembelian terkait.',
            '9. nomer_faktur harus terdaftar pada Faktur Pembelian dan sesuai dengan supplier yang dipilih (lihat sheet "Faktur Belum Lunas").',
            '10. nominal_bayar diisi angka > 0 dan tidak boleh melebihi sisa hutang faktur.',
            '11. nomer_pembayaran harus unik dan belum pernah digunakan di sistem.',
            '12. HAPUS baris contoh pada sheet "Transaksi" sebelum mengunggah.',
            '13. Jika ada satu baris saja yang tidak valid, SELURUH import dibatalkan (all-or-nothing, tidak ada data parsial tersimpan).',
        ];

        foreach ($lines as $index => $line) {
            $sheet->setCellValue([1, $index + 1], $line);
        }

        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getColumnDimension('A')->setWidth(110);
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

    private function buildFakturSheet(Worksheet $sheet): void
    {
        $sheet->setTitle('Faktur Belum Lunas');
        $headers = ['nomer_faktur', 'kode_supplier', 'nama_supplier', 'tanggal_faktur', 'grandtotal', 'sudah_terbayar', 'sisa_hutang'];

        foreach ($headers as $index => $header) {
            $sheet->setCellValue([$index + 1, 1], $header);
        }

        $lastColumn = Coordinate::stringFromColumnIndex(count($headers));
        $headerRange = 'A1:'.$lastColumn.'1';
        $sheet->getStyle($headerRange)->getFont()->setBold(true);
        $sheet->getStyle($headerRange)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('D1E7DD');

        $condition = DB::connection()->getDriverName() === 'sqlite'
            ? 'CAST(COALESCE(grandtotal, 0) AS INTEGER) > CAST(COALESCE(sudah_terbayar, 0) AS INTEGER)'
            : 'FLOOR(COALESCE(grandtotal, 0)) > FLOOR(COALESCE(sudah_terbayar, 0))';

        $invoices = FakturPembelian::query()
            ->with('supplier:id,kode_supplier,nama_supplier')
            ->whereRaw($condition)
            ->where(function ($statusQuery) {
                $statusQuery
                    ->whereNull('status_proses')
                    ->orWhere('status_proses', '!=', '1');
            })
            ->orderBy('tanggal_faktur')
            ->orderBy('nomer_faktur')
            ->get(['id', 'supplier_id', 'nomer_faktur', 'tanggal_faktur', 'grandtotal', 'sudah_terbayar']);

        $rowNumber = 2;
        foreach ($invoices as $inv) {
            $grandtotal = (float) $inv->grandtotal;
            $sudahTerbayar = (float) $inv->sudah_terbayar;
            $sisaHutang = max($grandtotal - $sudahTerbayar, 0);

            $sheet->setCellValueExplicit([1, $rowNumber], (string) $inv->nomer_faktur, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit([2, $rowNumber], (string) ($inv->supplier?->kode_supplier ?? ''), DataType::TYPE_STRING);
            $sheet->setCellValue([3, $rowNumber], (string) ($inv->supplier?->nama_supplier ?? ''));
            $sheet->setCellValue([4, $rowNumber], optional($inv->tanggal_faktur)->format('Y-m-d') ?? '');
            $sheet->setCellValue([5, $rowNumber], $grandtotal);
            $sheet->setCellValue([6, $rowNumber], $sudahTerbayar);
            $sheet->setCellValue([7, $rowNumber], $sisaHutang);
            $rowNumber++;
        }

        foreach (range(1, count($headers)) as $columnIndex) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($columnIndex))->setAutoSize(true);
        }
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
