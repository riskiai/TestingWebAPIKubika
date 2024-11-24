<?php

namespace App\Http\Resources\Kategori;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class KategoriCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [];

        foreach ($this as $kategori) {
            $data[] = [
                'id' => $kategori->id,
                'name' => $kategori->name,
                'kode_kategori' => $kategori->kode_kategori,
                'created_at' => $kategori->created_at,
                'updated_at' => $kategori->updated_at,
            ];
        }

        return $data;
    }
}
