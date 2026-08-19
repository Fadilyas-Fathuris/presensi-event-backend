<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class GoogleRegisterCompleteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $currentYear = date('Y');

        return [
            'temp_token' => 'required|string',
            'phone' => 'required|string|max:20',
            'graduation_year' => "required|digits:4|integer|min:1950|max:{$currentYear}",
            'birth_date' => 'required|date|before:today',
            'gender' => ['required', Rule::in(['Laki-laki', 'Perempuan'])],
            'domicile_province_code' => 'nullable|string|max:20',
            'domicile_city_code' => 'nullable|string|max:20',
            'domicile_district_code' => 'nullable|string|max:20',
            'domicile_village_code' => 'nullable|string|max:20',
            'domicile_postal_code' => 'nullable|string|max:10',
            'domicile_address' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'temp_token.required' => 'Token registrasi wajib diisi.',
            'temp_token.string' => 'Token registrasi tidak valid.',
            'phone.required' => 'Nomor telepon wajib diisi.',
            'phone.string' => 'Nomor telepon harus berupa teks.',
            'phone.max' => 'Nomor telepon maksimal 20 karakter.',
            'graduation_year.required' => 'Tahun kelulusan wajib diisi.',
            'graduation_year.digits' => 'Tahun kelulusan harus 4 digit.',
            'graduation_year.integer' => 'Tahun kelulusan harus berupa angka.',
            'graduation_year.min' => 'Tahun kelulusan minimal 1950.',
            'graduation_year.max' => 'Tahun kelulusan tidak boleh melebihi tahun ini.',
            'birth_date.required' => 'Tanggal lahir wajib diisi.',
            'birth_date.date' => 'Tanggal lahir tidak valid.',
            'birth_date.before' => 'Tanggal lahir harus sebelum hari ini.',
            'gender.required' => 'Jenis kelamin wajib diisi.',
            'gender.in' => 'Jenis kelamin hanya boleh Laki-laki atau Perempuan.',
            'domicile_province_code.string' => 'Kode provinsi harus berupa teks.',
            'domicile_province_code.max' => 'Kode provinsi maksimal 20 karakter.',
            'domicile_city_code.string' => 'Kode kota/kabupaten harus berupa teks.',
            'domicile_city_code.max' => 'Kode kota/kabupaten maksimal 20 karakter.',
            'domicile_district_code.string' => 'Kode kecamatan harus berupa teks.',
            'domicile_district_code.max' => 'Kode kecamatan maksimal 20 karakter.',
            'domicile_village_code.string' => 'Kode desa/kelurahan harus berupa teks.',
            'domicile_village_code.max' => 'Kode desa/kelurahan maksimal 20 karakter.',
            'domicile_postal_code.string' => 'Kode pos harus berupa teks.',
            'domicile_postal_code.max' => 'Kode pos maksimal 10 karakter.',
            'domicile_address.string' => 'Alamat harus berupa teks.',
            'domicile_address.max' => 'Alamat maksimal 1000 karakter.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Ada isian yang belum sesuai.',
                'errors' => $validator->errors(),
            ], 422)
        );
    }
}