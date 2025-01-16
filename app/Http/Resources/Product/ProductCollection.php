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
                'type_pembelian' => $product->type_pembelian,
                'harga_product'=> $product->harga_product,
                'kategori' => [
                    'id' => optional($product->kategori)->id,
                    'name' => optional($product->kategori)->name,
                    'kode_kategori' => optional($product->kategori)->kode_kategori,
                ],
                'deskripsi' => $product->deskripsi,
                'stok' => $product->stok,
                'ongkir' => $product->ongkir,
                'created_at' => $product->created_at,
                'updated_at' => $product->updated_at,
            ];
        }

        return $data;
    }
}
