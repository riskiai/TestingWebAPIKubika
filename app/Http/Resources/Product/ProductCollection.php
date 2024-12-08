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
                'type_pembelian' => $product->type_pembelian, 
                'harga'=> $product->harga, 
                'ongkir' => $product->ongkir,
                'kategori' => [
                        'id' => optional($product->kategori)->id,
                        'name' => optional($product->kategori)->name,
                        'kode_kategori' => optional($product->kategori)->kode_kategori,
                    ],
                'created_at' => $product->created_at,
                'updated_at' => $product->updated_at,
            ];
        }

        return $data;
    }
}
