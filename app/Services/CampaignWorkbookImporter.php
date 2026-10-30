<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\DataImport;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

class CampaignWorkbookImporter
{
    private const SHEET = 'Campaign Meta Ads';

    public function preview(UploadedFile $file): array
    {
        $rows = $this->readRows($file->getRealPath());

        return ['headers' => array_keys($rows[0] ?? []), 'rows' => array_slice($rows, 0, 8), 'total' => count($rows)];
    }

    public function import(UploadedFile $file, ?int $userId): DataImport
    {
        $hash = hash_file('sha256', $file->getRealPath());
        if (DataImport::where('file_hash', $hash)->exists()) {
            throw new RuntimeException('File ini sudah pernah diimpor.');
        }
        $rows = $this->readRows($file->getRealPath());
        $errors = [];
        $imported = 0;
        $skipped = 0;
        DB::transaction(function () use ($rows, $file, $userId, $hash, &$errors, &$imported, &$skipped) {
            foreach ($rows as $index => $row) {
                try {
                    $date = $this->dateValue($row['Tanggal'] ?? null);
                    $name = trim((string) ($row['Campaign'] ?? ''));
                    $platform = trim((string) ($row['Platform'] ?? ''));
                    if (! $date || ! $name || ! $platform) {
                        $skipped++;

                        continue;
                    }
                    $sourceHash = hash('sha256', implode('|', [$date, $name, $platform]));
                    Campaign::updateOrCreate(['source_hash' => $sourceHash], [
                        'date' => $date, 'name' => $name, 'objective' => ($row['Objective'] ?? null) ?: 'Leads', 'platform' => $platform,
                        'budget' => $this->number($row['Budget'] ?? 0), 'impressions' => $this->number($row['Impression'] ?? 0),
                        'reach' => $this->number($row['Reach'] ?? 0), 'link_clicks' => $this->number($row['Klik Link'] ?? 0),
                        'profile_visits' => $this->number($row['Kunjungan Profil'] ?? 0), 'chats' => $this->number($row['Chat'] ?? 0),
                        'leads_count' => $this->number($row['Leads'] ?? 0), 'closings_count' => $this->number($row['Closing'] ?? 0),
                        'revenue' => $this->number($row['Omzet'] ?? 0), 'status' => ($row['Status'] ?? null) ?: 'Aktif',
                        'notes' => ($row['Catatan'] ?? null) ?: null, 'source' => 'excel',
                    ]);
                    $imported++;
                } catch (\Throwable $e) {
                    $errors[] = 'Baris '.($index + 2).': '.$e->getMessage();
                    $skipped++;
                }
            }
            DataImport::create(['user_id' => $userId, 'filename' => $file->getClientOriginalName(), 'file_hash' => $hash,
                'status' => empty($errors) ? 'completed' : 'completed_with_errors', 'rows_total' => count($rows),
                'rows_imported' => $imported, 'rows_skipped' => $skipped, 'errors' => $errors ?: null]);
        });

        return DataImport::where('file_hash', $hash)->firstOrFail();
    }

    private function readRows(string $path): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Ekstensi PHP zip belum aktif. Aktifkan zip pada PHP yang menjalankan aplikasi.');
        }
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('File XLSX tidak dapat dibuka.');
        }
        try {
            $shared = $this->sharedStrings($zip);
            $workbook = $this->xml($zip, 'xl/workbook.xml');
            $rels = $this->xml($zip, 'xl/_rels/workbook.xml.rels');
            $target = null;
            foreach ($workbook->sheets->sheet as $sheet) {
                if ((string) $sheet['name'] !== self::SHEET) {
                    continue;
                }
                $attrs = $sheet->attributes('r', true);
                $rid = (string) $attrs['id'];
                foreach ($rels->Relationship as $rel) {
                    if ((string) $rel['Id'] === $rid) {
                        $target = (string) $rel['Target'];
                    }
                }
            }
            if (! $target) {
                throw new RuntimeException('Sheet "'.self::SHEET.'" tidak ditemukan.');
            }
            $target = str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/'.ltrim($target, '/');
            $sheetXml = $this->xml($zip, $target);
            $grid = [];
            foreach ($sheetXml->sheetData->row as $row) {
                $values = [];
                foreach ($row->c as $cell) {
                    $ref = (string) $cell['r'];
                    preg_match('/^[A-Z]+/', $ref, $m);
                    $column = $m[0];
                    $type = (string) $cell['t'];
                    $raw = (string) $cell->v;
                    if ($type === 's') {
                        $value = $shared[(int) $raw] ?? '';
                    } elseif ($type === 'inlineStr') {
                        $value = (string) $cell->is->t;
                    } elseif ($type === 'b') {
                        $value = $raw === '1';
                    } else {
                        $value = $raw;
                    }
                    $values[$column] = $value;
                } $grid[] = $values;
            }
            if (! $grid) {
                return [];
            }
            $headers = $grid[0];
            $rows = [];
            foreach (['Tanggal', 'Campaign', 'Platform'] as $required) {
                if (! in_array($required, array_map('trim', $headers), true)) {
                    throw new RuntimeException('Kolom wajib tidak ditemukan: '.$required);
                }
            }
            foreach (array_slice($grid, 1) as $rawRow) {
                $row = [];
                foreach ($headers as $col => $header) {
                    $row[trim((string) $header)] = $rawRow[$col] ?? null;
                }
                if (array_filter($row, fn ($v) => $v !== null && $v !== '')) {
                    $rows[] = $row;
                }
            }

return $rows;
        } finally {
            $zip->close();
        }
    }

    private function sharedStrings(ZipArchive $zip): array
    {
        $text = $zip->getFromName('xl/sharedStrings.xml');
        if ($text === false) {
            return [];
        } $xml = $this->parseXml($text);
        $out = [];
        foreach ($xml->si as $si) {
            $parts = [];
            if (isset($si->t)) {
                $parts[] = (string) $si->t;
            } foreach ($si->r as $r) {
                $parts[] = (string) $r->t;
            } $out[] = implode('', $parts);
        }

return $out;
    }

    private function xml(ZipArchive $zip, string $name): SimpleXMLElement
    {
        $text = $zip->getFromName($name);
        if ($text === false) {
            throw new RuntimeException("Bagian XLSX $name tidak ditemukan.");
        }

return $this->parseXml($text);
    }

    private function parseXml(string $text): SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $xml = simplexml_load_string($text, SimpleXMLElement::class, LIBXML_NONET);
            if ($xml === false) {
                throw new RuntimeException('Struktur XML dalam XLSX tidak valid.');
            }

            return $xml;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function number(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : (float) str_replace(['Rp', '.', ' '], ['', '', ''], (string) $value);
    }

    private function dateValue(mixed $value): ?string
    {
        if (! $value) {
            return null;
        } if (is_numeric($value)) {
            return Carbon::create(1899, 12, 30)->addDays((int) $value)->toDateString();
        } try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
