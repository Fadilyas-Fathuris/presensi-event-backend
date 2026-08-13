<?php

namespace Tests\Feature;

use App\Models\Region;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAdminDataImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_import_open_admin_data_format(): void
    {
        Http::fake([
            'https://raw.example.test/all-flat.json' => Http::response([
                'Data from Open Admin Data (https://openadmindata.org/id/) — CC-BY-4.0.',
                [
                    'id' => '32',
                    'level' => 1,
                    'name' => ['local' => 'Jawa Barat'],
                    'parent' => null,
                    'zip_codes' => [],
                ],
                [
                    'id' => '32.73',
                    'level' => 2,
                    'name' => ['local' => 'Kota Bandung'],
                    'parent' => ['id' => '32'],
                    'zip_codes' => [],
                ],
                [
                    'id' => '32.73.01',
                    'level' => 3,
                    'name' => ['local' => 'Sukasari'],
                    'parent' => ['id' => '32.73'],
                    'zip_codes' => [],
                ],
            ]),
            'https://api.example.test/villages' => Http::response([
                [
                    'name' => 'west-java-32.json',
                    'type' => 'file',
                    'download_url' => 'https://raw.example.test/village-by-province/west-java-32.json',
                ],
            ]),
            'https://raw.example.test/village-by-province/west-java-32.json' => Http::response([
                [
                    'id' => '32.73.01.1001',
                    'level' => 4,
                    'name' => ['local' => 'Isola'],
                    'parent' => ['id' => '32.73.01'],
                    'zip_codes' => ['40154'],
                ],
            ]),
        ]);

        $this->artisan('regions:import-open-admin-data', [
            '--base-url' => 'https://raw.example.test',
            '--village-api-url' => 'https://api.example.test/villages',
        ])->assertSuccessful();

        $this->assertDatabaseHas('regions', [
            'code' => '32',
            'name' => 'Jawa Barat',
            'type' => 'province',
            'parent_code' => null,
        ]);

        $this->assertDatabaseHas('regions', [
            'code' => '32.73.01.1001',
            'name' => 'Isola',
            'type' => 'village',
            'parent_code' => '32.73.01',
            'postal_code' => '40154',
        ]);

        $this->assertSame(4, Region::query()->count());
    }
}
