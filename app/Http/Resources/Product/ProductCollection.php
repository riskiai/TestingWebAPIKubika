<?php

namespace App\Http\Resources\Product;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ProductCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [];

        foreach ($this as $product) {
            $data[] = [
                'id' => $product->id,
                'nama' => $product->nama,
                'kode_produk' => $product->kode_produk,
                'deskripsi' => $product->deskripsi,
                'stok' => $product->stok,
                'kategori' => [
                    'id' => $product->kategori->id,
                    'name' => $product->kategori->name,
                    'kode_kategori' => $product->kategori->kode_kategori,
                ],
                'created_at' => $product->created_at,
                'updated_at' => $product->updated_at,
            ];
        }

        return $data;
    }
}
