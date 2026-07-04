<?php

namespace App\Services\Pengaturan;

use App\Services\LogAktifitasService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

class FileConversionService
{
    private const DEFAULT_DELIMITER = ',';

    public function __construct(
        private readonly LogAktifitasService $logService,
    ) {
    }

    /**
     * @return array{output_path: string, download_name: string}
     */
    public function convertCsvToXlsx(UploadedFile $sourceFile): array
    {
        $sourcePath = $sourceFile->getRealPath();

        if ($sourcePath === false) {
            throw new RuntimeException('File sumber tidak dapat dibaca.');
        }

        $outputDirectory = storage_path('app/temp/file-conversions');
        File::ensureDirectoryExists($outputDirectory);

        $originalName = pathinfo($sourceFile->getClientOriginalName(), PATHINFO_FILENAME);
        $sanitizedName = Str::of($originalName)->trim()->value();
        $downloadName = ($sanitizedName !== '' ? $sanitizedName : 'hasil-konversi').'.xlsx';
        $outputPath = $outputDirectory.DIRECTORY_SEPARATOR.Str::uuid()->toString().'.xlsx';

        $delimiter = $this->detectDelimiter($sourcePath);
        $spreadsheet = new Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();
        $worksheet->setTitle('CSV to XLSX');

        $handle = fopen($sourcePath, 'rb');

        if ($handle === false) {
            throw new RuntimeException('Gagal membuka file CSV.');
        }

        try {
            $currentRow = 1;

            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                if ($currentRow === 1 && isset($row[0])) {
                    $row[0] = $this->stripUtf8Bom((string) $row[0]);
                }

                if ($this->isBlankRow($row)) {
                    continue;
                }

                foreach ($row as $columnIndex => $value) {
                    $cellCoordinate = Coordinate::stringFromColumnIndex($columnIndex + 1).$currentRow;

                    $worksheet->setCellValueExplicit(
                        $cellCoordinate,
                        (string) ($value ?? ''),
                        DataType::TYPE_STRING
                    );
                }

                $currentRow++;
            }
        } finally {
            fclose($handle);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($outputPath);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        $this->logService->log('Konversi File', 'convert', null, [
            'tipe_konversi' => 'csv_to_xlsx',
            'nama_file_sumber' => $sourceFile->getClientOriginalName(),
        ]);

        return [
            'output_path' => $outputPath,
            'download_name' => $downloadName,
        ];
    }
    private function detectDelimiter(string $sourcePath): string
    {
        $handle = fopen($sourcePath, 'rb');

        if ($handle === false) {
            throw new RuntimeException('Gagal membaca sampel file CSV.');
        }

        $scores = [
            ',' => 0,
            ';' => 0,
            "\t" => 0,
            '|' => 0,
        ];
        $sampleCount = 0;

        try {
            while ($sampleCount < 5 && ($line = fgets($handle)) !== false) {
                $line = $this->stripUtf8Bom($line);

                if (trim($line) === '') {
                    continue;
                }

                foreach ($scores as $delimiter => $score) {
                    $scores[$delimiter] += substr_count($line, $delimiter);
                }

                $sampleCount++;
            }
        } finally {
            fclose($handle);
        }

        arsort($scores);
        $delimiter = array_key_first($scores);

        if ($delimiter === null || $scores[$delimiter] === 0) {
            return self::DEFAULT_DELIMITER;
        }

        return $delimiter;
    }

    private function isBlankRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) ($value ?? '')) !== '') {
                return false;
            }
        }

        return true;
    }

    private function stripUtf8Bom(string $value): string
    {
        if (str_starts_with($value, "\xEF\xBB\xBF")) {
            return substr($value, 3);
        }

        return $value;
    }
}
