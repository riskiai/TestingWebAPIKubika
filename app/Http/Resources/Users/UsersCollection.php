<?php

namespace App\Http\Resources\Users;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class UsersCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [];

        foreach ($this as $user) {
            $data[] = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->role_name,
                'divisi' => [
                    'name' => $user->divisi->name ?? null,
                    'kode_divisi' => $user->divisi->kode_divisi ?? null,
                ],
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ];
        }

        return $data;
    }
}
