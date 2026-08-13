<?php

namespace App\Console\Commands;

use App\Models\Region;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ImportOpenAdminDataRegions extends Command
{
    private const RAW_BASE_URL = 'https://raw.githubusercontent.com/open-admin-data/indonesia-administrative-divisions/main/data';
    private const GITHUB_VILLAGE_API_URL = 'https://api.github.com/repos/open-admin-data/indonesia-administrative-divisions/contents/data/village-by-province?ref=main';

    protected $signature = 'regions:import-open-admin-data
        {--without-villages : Import only province, regency/city, and district rows}
        {--truncate : Delete existing region rows before importing}
        {--base-url= : Override Open Admin Data raw data base URL}
        {--village-api-url= : Override GitHub contents API URL for village files}';

    protected $description = 'Import region data from open-admin-data/indonesia-administrative-divisions.';

    public function handle(): int
    {
        if ($this->option('truncate')) {
            Region::query()->delete();
        }

        $baseUrl = rtrim((string) ($this->option('base-url') ?: self::RAW_BASE_URL), '/');
        $villageApiUrl = (string) ($this->option('village-api-url') ?: self::GITHUB_VILLAGE_API_URL);

        $imported = 0;

        $this->info('Importing province, regency/city, and district data...');
        $allFlatData = $this->downloadJson("{$baseUrl}/all-flat.json");
        $allFlatRows = isset($allFlatData['data']) && is_array($allFlatData['data']) ? $allFlatData['data'] : $allFlatData;
        $imported += $this->importRows($allFlatRows);

        if (! $this->option('without-villages')) {
            $this->info('Discovering village files...');

            foreach ($this->villageFileUrls($villageApiUrl) as $url) {
                $this->line('Importing ' . basename(parse_url($url, PHP_URL_PATH) ?: $url));
                $imported += $this->importRows($this->downloadJson($url));
            }
        }

        Cache::flush();

        $this->info("Imported {$imported} region rows from Open Admin Data.");

        return self::SUCCESS;
    }

    private function importRows(array $rows): int
    {
        $count = 0;

        foreach (array_chunk($rows, 1000) as $chunk) {
            $payload = [];

            foreach ($chunk as $row) {
                if (! is_array($row) || ! isset($row['id'], $row['level'])) {
                    continue;
                }

                $payload[] = $this->normalizeRow($row);
            }

            if ($payload === []) {
                continue;
            }

            Region::query()->upsert(
                $payload,
                ['code'],
                ['name', 'type', 'parent_code', 'postal_code', 'updated_at']
            );

            $count += count($payload);
        }

        return $count;
    }

    private function normalizeRow(array $row): array
    {
        $level = (int) ($row['level'] ?? 0);
        $type = match ($level) {
            1 => 'province',
            2 => 'city',
            3 => 'district',
            4 => 'village',
            default => throw new RuntimeException('Unknown Open Admin Data level: ' . $level),
        };

        $name = data_get($row, 'name.local') ?? $row['name'] ?? null;
        $zipCodes = $row['zip_codes'] ?? [];
        $postalCode = is_array($zipCodes) && $zipCodes !== []
            ? (string) reset($zipCodes)
            : null;

        $now = now();

        return [
            'code' => (string) $row['id'],
            'name' => (string) $name,
            'type' => $type,
            'parent_code' => data_get($row, 'parent.id') !== null
                ? (string) data_get($row, 'parent.id')
                : null,
            'postal_code' => $postalCode,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private function downloadJson(string $url): array
    {
        $response = Http::timeout(120)->retry(3, 1000)->get($url);

        if (! $response->successful()) {
            throw new RuntimeException("Unable to download Open Admin Data from {$url}.");
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new RuntimeException("Open Admin Data response from {$url} must be a JSON array.");
        }

        return $data;
    }

    private function villageFileUrls(string $villageApiUrl): array
    {
        $response = Http::timeout(60)->retry(3, 1000)->get($villageApiUrl);

        if (! $response->successful()) {
            throw new RuntimeException('Unable to discover Open Admin Data village files.');
        }

        $files = $response->json();

        if (! is_array($files)) {
            throw new RuntimeException('Open Admin Data village file listing must be a JSON array.');
        }

        return collect($files)
            ->filter(fn (array $file) => ($file['type'] ?? null) === 'file')
            ->filter(fn (array $file) => str_ends_with((string) ($file['name'] ?? ''), '.json'))
            ->map(fn (array $file) => (string) ($file['download_url'] ?? ''))
            ->filter()
            ->values()
            ->all();
    }
}
