<?php

namespace App\Services;

use App\Models\Region;
use Illuminate\Validation\ValidationException;

class DomicileResolver
{
    private const FIELDS = [
        'domicile_province_code',
        'domicile_city_code',
        'domicile_district_code',
        'domicile_village_code',
        'domicile_postal_code',
        'domicile_address',
    ];

    public function hasDomicileInput(array $input): bool
    {
        foreach (self::FIELDS as $field) {
            if (array_key_exists($field, $input)) {
                return true;
            }
        }

        return false;
    }

    public function resolve(array $input): ?array
    {
        if (! $this->hasDomicileInput($input)) {
            return null;
        }

        $province = $this->findRegion($input['domicile_province_code'] ?? null, 'province', 'domicile_province_code');
        $city = $this->findRegion($input['domicile_city_code'] ?? null, 'city', 'domicile_city_code');
        $district = $this->findRegion($input['domicile_district_code'] ?? null, 'district', 'domicile_district_code');
        $village = $this->findRegion($input['domicile_village_code'] ?? null, 'village', 'domicile_village_code');

        if ($city && ! $province) {
            $this->fail('domicile_province_code', 'Provinsi wajib dipilih jika kabupaten/kota diisi.');
        }

        if ($district && ! $city) {
            $this->fail('domicile_city_code', 'Kabupaten/kota wajib dipilih jika kecamatan diisi.');
        }

        if ($village && ! $district) {
            $this->fail('domicile_district_code', 'Kecamatan wajib dipilih jika desa/kelurahan diisi.');
        }

        $this->ensureParent($city, $province, 'domicile_city_code', 'Kabupaten/kota tidak sesuai dengan provinsi.');
        $this->ensureParent($district, $city, 'domicile_district_code', 'Kecamatan tidak sesuai dengan kabupaten/kota.');
        $this->ensureParent($village, $district, 'domicile_village_code', 'Desa/kelurahan tidak sesuai dengan kecamatan.');

        $postalCode = $input['domicile_postal_code'] ?? $village?->postal_code;
        $address = $input['domicile_address'] ?? null;

        return [
            'province_code' => $province?->code,
            'province_name' => $province?->name,
            'city_code' => $city?->code,
            'city_name' => $city?->name,
            'district_code' => $district?->code,
            'district_name' => $district?->name,
            'village_code' => $village?->code,
            'village_name' => $village?->name,
            'postal_code' => $postalCode,
            'address' => $address,
        ];
    }

    private function findRegion(?string $code, string $type, string $field): ?Region
    {
        if ($code === null || $code === '') {
            return null;
        }

        $region = Region::query()
            ->where('code', $code)
            ->where('type', $type)
            ->first();

        if (! $region) {
            $this->fail($field, 'Kode wilayah tidak valid.');
        }

        return $region;
    }

    private function ensureParent(?Region $child, ?Region $parent, string $field, string $message): void
    {
        if (! $child || ! $parent) {
            return;
        }

        if ($child->parent_code !== $parent->code) {
            $this->fail($field, $message);
        }
    }

    private function fail(string $field, string $message): void
    {
        throw ValidationException::withMessages([
            $field => [$message],
        ]);
    }
}
