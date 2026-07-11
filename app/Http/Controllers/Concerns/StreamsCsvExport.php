<?php

namespace App\Http\Controllers\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait StreamsCsvExport
{
    protected function streamCsvExport(Request $request, Builder $query, string $prefix, array $headers, Closure $rowMapper): StreamedResponse
    {
        $validated = $request->validate([
            'startDate' => ['required', 'date_format:Y-m-d'],
            'endDate' => ['required', 'date_format:Y-m-d', 'after_or_equal:startDate'],
        ]);

        $fileName = sprintf('%s-%s-%s.csv', $prefix, str_replace('-', '', $validated['startDate']), str_replace('-', '', $validated['endDate']));

        return response()->streamDownload(function () use ($query, $headers, $rowMapper): void {
            $handle = fopen('php://output', 'wb');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers);

            $query->reorder()->orderBy('id')->lazyById(1000, 'id')->each(function ($row) use ($handle, $rowMapper): void {
                fputcsv($handle, $rowMapper($row));
            });

            fclose($handle);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    protected function csvNumber(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
