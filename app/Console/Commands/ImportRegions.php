<?php

namespace App\Console\Commands;

use App\Models\Region;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;

class ImportRegions extends Command
{
    protected $signature = 'regions:import {source=storage/app/regions/regions.json : Local JSON file path or HTTPS URL}';

    protected $description = 'Import region master data for domicile dropdowns.';

    public function handle(): int
    {
        $source = (string) $this->argument('source');
        $regions = $this->loadRegions($source);
        $imported = 0;

        foreach ($regions as $index => $region) {
            $validated = $this->validateRegion($region, $index);

            Region::updateOrCreate(
                ['code' => $validated['code']],
                [
                    'name' => $validated['name'],
                    'type' => $validated['type'],
                    'parent_code' => $validated['parent_code'] ?? null,
                    'postal_code' => $validated['postal_code'] ?? null,
                ]
            );

            $imported++;
        }

        Cache::flush();

        $this->info("Imported {$imported} region rows.");

        return self::SUCCESS;
    }

    private function loadRegions(string $source): array
    {
        if (Str::startsWith($source, ['http://', 'https://'])) {
            $response = Http::timeout(60)->get($source);

            if (! $response->successful()) {
                throw new RuntimeException("Unable to download regions from {$source}.");
            }

            $json = $response->body();
        } else {
            $path = base_path($source);

            if (! File::exists($path)) {
                throw new RuntimeException("Region source file does not exist: {$source}");
            }

            $json = File::get($path);
        }

        $data = json_decode($json, true);

        if (! is_array($data)) {
            throw new RuntimeException('Region source must be a JSON array.');
        }

        return $data;
    }

    private function validateRegion(array $region, int $index): array
    {
        $validator = Validator::make($region, [
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:province,city,district,village'],
            'parent_code' => ['nullable', 'string', 'max:20'],
            'postal_code' => ['nullable', 'string', 'max:10'],
        ]);

        if ($validator->fails()) {
            throw new RuntimeException(
                "Invalid region row at index {$index}: " . $validator->errors()->toJson()
            );
        }

        $validated = $validator->validated();

        if ($validated['type'] !== 'province' && empty($validated['parent_code'])) {
            throw new RuntimeException("Region row at index {$index} must include parent_code.");
        }

        return $validated;
    }
}
