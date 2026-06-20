<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool 
    { 
        return true; 
    }

    public function rules(): array
    {
        return [
            'first_name'       => 'required|string|max:255',
            'last_name'        => 'required|string|max:255',
            'gender'           => ['required', Rule::in(['Laki-laki', 'Perempuan'])],
            'email'            => 'required|string|email|max:255|unique:users',
            'phone'            => 'required|string|max:20|unique:users',
            'graduation_year'  => 'required|integer',
            'birth_date'       => 'required|date|before_or_equal:today',
            'password'         => 'required|string|min:8',
            'password_confirmation' => 'required|string|same:password',
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required' => 'Nama depan wajib diisi.',
            'first_name.string' => 'Nama depan harus berupa teks.',
            'last_name.required' => 'Nama belakang wajib diisi.',
            'last_name.string' => 'Nama belakang harus berupa teks.',
            'gender.required' => 'Jenis kelamin wajib diisi.',
            'gender.in' => 'Jenis kelamin hanya boleh Laki-laki atau Perempuan.',
            'email.required' => 'Email wajib diisi.',
            'email.string' => 'Email harus berupa teks.',
            'email.email' => 'Email tidak valid.',
            'email.unique' => 'Email ini sudah terdaftar.',
            'phone.required' => 'Nomor telepon wajib diisi.',
            'phone.string' => 'Nomor telepon harus berupa teks.',
            'phone.unique' => 'Nomor telepon ini sudah terdaftar.',
            'graduation_year.required' => 'Tahun kelulusan wajib diisi.',
            'graduation_year.integer' => 'Tahun kelulusan harus berupa angka.',
            'birth_date.required' => 'Tanggal lahir wajib diisi.',
            'birth_date.date' => 'Tanggal lahir tidak valid.',
            'birth_date.before_or_equal' => 'Tanggal lahir tidak boleh melebihi hari ini.',
            'password.required' => 'Kata sandi wajib diisi.',
            'password.string' => 'Kata sandi harus berupa teks.',
            'password.min' => 'Kata sandi minimal 8 karakter.',
            'password_confirmation.required' => 'Konfirmasi kata sandi wajib diisi.',
            'password_confirmation.string' => 'Konfirmasi kata sandi harus berupa teks.',
            'password_confirmation.same' => 'Konfirmasi kata sandi tidak sama.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'message' => 'Ada isian yang belum sesuai.',
                'errors'  => $validator->errors(),
            ], 422)
        );
    }
}
