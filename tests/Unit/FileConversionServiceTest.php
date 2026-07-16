<?php

namespace Tests\Unit;

use App\Services\LogAktifitasService;
use App\Services\Pengaturan\FileConversionService;
use Illuminate\Http\UploadedFile;
use Mockery;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class FileConversionServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_service_converts_semicolon_delimited_csv_to_xlsx(): void
    {
        $service = $this->makeService();
        $file = UploadedFile::fake()->createWithContent('semicolon.csv', "kode;nama\n00123;Alpha\n");

        $result = $service->convertCsvToXlsx($file);

        try {
            $spreadsheet = IOFactory::load($result['output_path']);
            $sheet = $spreadsheet->getActiveSheet();

            $this->assertSame('kode', $sheet->getCell('A1')->getValue());
            $this->assertSame('nama', $sheet->getCell('B1')->getValue());
            $this->assertSame('00123', $sheet->getCell('A2')->getValue());
            $this->assertSame('Alpha', $sheet->getCell('B2')->getValue());
        } finally {
            if (isset($spreadsheet)) {
                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet);
            }

            if (is_file($result['output_path'])) {
                unlink($result['output_path']);
            }
        }
    }

    public function test_service_preserves_leading_zero_values_as_text(): void
    {
        $service = $this->makeService();
        $file = UploadedFile::fake()->createWithContent('leading-zero.csv', "kode,nilai\n00123,00045\n");

        $result = $service->convertCsvToXlsx($file);

        try {
            $spreadsheet = IOFactory::load($result['output_path']);
            $sheet = $spreadsheet->getActiveSheet();

            $this->assertSame('00123', $sheet->getCell('A2')->getValue());
            $this->assertSame('00045', $sheet->getCell('B2')->getValue());
            $this->assertSame('s', $sheet->getCell('A2')->getDataType());
            $this->assertSame('s', $sheet->getCell('B2')->getDataType());
        } finally {
            if (isset($spreadsheet)) {
                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet);
            }

            if (is_file($result['output_path'])) {
                unlink($result['output_path']);
            }
        }
    }

    private function makeService(): FileConversionService
    {
        $logService = Mockery::mock(LogAktifitasService::class);
        $logService->shouldReceive('log')->once();

        return new FileConversionService($logService);
    }
}
