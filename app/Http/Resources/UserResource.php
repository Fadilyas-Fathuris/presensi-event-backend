<?php

namespace App\Http\Resources;

use App\Models\UserDomicile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $domicile = $this->whenLoaded('domicile');

        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'gender' => $this->gender,
            'email' => $this->email,
            'phone' => $this->phone,
            'graduation_year' => $this->graduation_year,
            'birth_date' => $this->birth_date,
            'avatar_url' => $this->avatar_url,
            'role' => $this->role,
            'admin_level' => $this->admin_level,
            'status' => $this->status,
            'auth_provider' => $this->auth_provider,
            'google_linked' => !is_null($this->google_id),
            'has_password' => !empty($this->password),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'domicile' => $domicile instanceof UserDomicile
                ? $this->formatDomicile($domicile)
                : null,
        ];
    }

    private function formatDomicile(UserDomicile $domicile): array
    {
        return [
            'province' => [
                'code' => $domicile->province_code,
                'name' => $domicile->province_name,
            ],
            'city' => [
                'code' => $domicile->city_code,
                'name' => $domicile->city_name,
            ],
            'district' => [
                'code' => $domicile->district_code,
                'name' => $domicile->district_name,
            ],
            'village' => [
                'code' => $domicile->village_code,
                'name' => $domicile->village_name,
            ],
            'postal_code' => $domicile->postal_code,
            'address' => $domicile->address,
        ];
    }
}
