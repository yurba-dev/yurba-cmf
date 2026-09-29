<?php

namespace Yurba\Cmf\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

class Csv
{
    public static function download(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // bom so excel reads utf-8 correctly
            fputcsv($out, array_map([static::class, 'safeCell'], $headers));
            foreach ($rows as $row) {
                fputcsv($out, array_map([static::class, 'safeCell'], (array) $row));
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    // cells may hold visitor input: a leading = + - @ tab or cr would run as a formula, so it gets an apostrophe
    public static function safeCell(mixed $value): mixed
    {
        if (! is_string($value) || $value == '' || is_numeric($value)) {
            return $value;
        }

        // one more apostrophe before a trigger so unsafeCell() can strip exactly one and restore the original
        return preg_match('/^\'*[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }

    // undo safeCell() so an export round-trips through import
    public static function unsafeCell(?string $value): ?string
    {
        return $value !== null && preg_match('/^\'\'*[=+\-@\t\r]/', $value) ? substr($value, 1) : $value;
    }

    // [header, rows keyed by header]
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
            if (count(array_filter($line, fn ($v) => $v !== null && $v !== '')) == 0) {
                continue;
            }

            if ($header === null) {
                $line[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $line[0]);
                $header = array_map(fn ($h) => trim((string) $h), $line);

                continue;
            }

            $row = [];
            foreach ($header as $i => $key) {
                $row[$key] = static::unsafeCell($line[$i] ?? null);
            }
            $rows[] = $row;
        }

        fclose($handle);

        return [$header ?? [], $rows];
    }
}
