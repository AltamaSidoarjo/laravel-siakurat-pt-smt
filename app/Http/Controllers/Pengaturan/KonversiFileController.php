<?php

namespace App\Http\Controllers\Pengaturan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pengaturan\ConvertCsvToXlsxRequest;
use App\Services\Pengaturan\FileConversionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class KonversiFileController extends Controller
{
    public function __construct(
        private readonly FileConversionService $fileConversionService,
    ) {
    }

    public function index(): View
    {
        return view('pengaturan.konversi-file.index', [
            'page' => 'app',
            'phpUploadMaxFileSize' => ini_get('upload_max_filesize') ?: 'tidak diketahui',
            'phpPostMaxSize' => ini_get('post_max_size') ?: 'tidak diketahui',
        ]);
    }

    public function convertCsvToXlsx(ConvertCsvToXlsxRequest $request): BinaryFileResponse|RedirectResponse
    {
        try {
            $result = $this->fileConversionService->convertCsvToXlsx(
                $request->file('source_file')
            );
        } catch (Throwable $throwable) {
            report($throwable);

            return back()
                ->withErrors([
                    'source_file' => 'File CSV tidak dapat diproses. Pastikan delimiter dan isi file valid.',
                ]);
        }

        return response()
            ->download(
                $result['output_path'],
                $result['download_name'],
                [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ]
            )
            ->deleteFileAfterSend(true);
    }
}
