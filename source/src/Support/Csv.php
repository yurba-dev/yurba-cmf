<?php

namespace Yurba\Cmf\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

// dependency-free csv read/write; the seam an optional .xlsx driver would slot into
class Csv
{
    public static function download(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // bom so excel reads utf-8 correctly
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /** @return array{0: string[], 1: array<int, array<string, string|null>>} */
    public static function read(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return [[], []];
        }

        $header = null;
        $rows = [];

        while (($line = fgetcsv($handle)) !== false) {
            // skip blank lines (fgetcsv yields [null])
            if (count(array_filter($line, fn ($v) => $v !== null && $v !== '')) === 0) {
                continue;
            }

            if ($header === null) {
                $line[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $line[0]); // strip bom
                $header = array_map(fn ($h) => trim((string) $h), $line);

                continue;
            }

            $row = [];
            foreach ($header as $i => $key) {
                $row[$key] = $line[$i] ?? null;
            }
            $rows[] = $row;
        }

        fclose($handle);

        return [$header ?? [], $rows];
    }
}
