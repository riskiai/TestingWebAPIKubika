<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Product;
use Illuminate\Http\Request;
use App\Facades\MessageActeeve;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Requests\Product\CreateRequest;
use App\Http\Requests\Product\UpdateRequest;
use App\Http\Resources\Product\ProductCollection;

class ProdukController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::query();

        // Filter pencarian berdasarkan 'id' atau 'nama'
        if ($request->has('search')) {
            $query->where(function ($query) use ($request) {
                $query->where('id', 'like', '%' . $request->search . '%')
                      ->orWhere('nama', 'like', '%' . $request->search . '%');
            });
        }

        // Filter berdasarkan 'kode_produk'
          if ($request->has('kode_produk')) {
            $query->where('kode_produk', 'like', '%' . $request->kode_produk . '%');
        }

        // Filter berdasarkan 'kode_kategori' melalui relasi ke tabel kategori
        if ($request->has('kode_kategori')) {
            $query->whereHas('kategori', function ($query) use ($request) {
                $query->where('kode_kategori', 'like', '%' . $request->kode_kategori . '%');
            });
        }

        // Filter berdasarkan rentang tanggal pada 'created_at'
        if ($request->has('date')) {
            $date = str_replace(['[', ']'], '', $request->date);
            $date = explode(", ", $date);
            $query->whereBetween('created_at', [$date[0], $date[1]]);
        }

        // Paginate hasil query berdasarkan jumlah per halaman
        $products = $query->paginate($request->per_page); // Default per_page = 10 jika tidak ada

        return new ProductCollection($products);
    }

    /* public function produkAll(Request $request)
    {
        $query = Product::query();

        // Filter pencarian berdasarkan 'id' atau 'nama'
        if ($request->has('search')) {
            $query->where(function ($query) use ($request) {
                $query->where('id', 'like', '%' . $request->search . '%')
                      ->orWhere('nama', 'like', '%' . $request->search . '%');
            });
        }

        // Filter berdasarkan 'kode_produk'
          if ($request->has('kode_produk')) {
            $query->where('kode_produk', 'like', '%' . $request->kode_produk . '%');
        }

        // Filter berdasarkan 'kode_kategori' melalui relasi ke tabel kategori
        if ($request->has('kode_kategori')) {
            $query->whereHas('kategori', function ($query) use ($request) {
                $query->where('kode_kategori', 'like', '%' . $request->kode_kategori . '%');
            });
        }

        // Filter berdasarkan rentang tanggal pada 'created_at'
        if ($request->has('date')) {
            $date = str_replace(['[', ']'], '', $request->date);
            $date = explode(", ", $date);
            $query->whereBetween('created_at', [$date[0], $date[1]]);
        }

        // Ambil semua data tanpa pagination
        $products = $query->get();

        return new ProductCollection($products);
    } */

    public function produkAll(Request $request)
    {
        $keyword = trim($request->input('search', ''));

        $query = Product::select('id', 'nama', 'type_pembelian', 'harga_product');

        /* ── filter keyword ── */
        if ($keyword !== '') {
            $query->where(fn ($q) =>
                $q->where('id',   'like', "%{$keyword}%")
                ->orWhere('nama','like', "%{$keyword}%"));
        } else {
            $query->limit(5);   // batasi 5 jika tidak search
        }

        /* ── filter lain (opsional) ── */
        if ($request->filled('kode_produk')) {
            $query->where('kode_produk', 'like', "%{$request->kode_produk}%");
        }

        if ($request->filled('kode_kategori')) {
            $query->whereHas('kategori', fn ($q) =>
                $q->where('kode_kategori', 'like', "%{$request->kode_kategori}%"));
        }

        if ($request->filled('date')) {
            $range = explode(', ', str_replace(['[',']'], '', $request->date));
            if (count($range) === 2) {
                $query->whereBetween('created_at', [$range[0], $range[1]]);
            }
        }

        /* ── ambil & kirim ── */
        $products = $query->orderBy('nama')->get();

        //  ⬇️  Wrap di dalam "data"
        return response()->json(['data' => $products], 200);
    }
    
    public function store(CreateRequest $request)
    {
        DB::beginTransaction();

        try {
            // Membuat produk baru, kode_produk otomatis di-generate di model
            $product = Product::create([
                'nama' => $request->nama,
                'id_kategori' => $request->id_kategori,
                'deskripsi' => $request->deskripsi,
                'stok' => $request->stok,
                'type_pembelian' => $request->type_pembelian,
                'harga_product' => $request->harga_product,
                // 'ongkir' => $request->ongkir,
            ]);

            DB::commit();
            return MessageActeeve::success("Product {$product->nama} has been successfully created");
        } catch (\Throwable $th) {
            DB::rollBack();
            return MessageActeeve::error($th->getMessage());
        }
    }

    public function show(string $id)
    {
        // Mengambil data produk berdasarkan ID
        $product = Product::find($id);
        
        // Jika produk tidak ditemukan
        if (!$product) {
            return MessageActeeve::notFound('Product not found!');
        }

        // Mengembalikan data produk yang ditemukan, disesuaikan dengan format ProductCollection
        return MessageActeeve::render([
            'status' => MessageActeeve::SUCCESS,
            'status_code' => MessageActeeve::HTTP_OK,
            'data' => [
                'id' => $product->id,
                'nama' => $product->nama,
                'kode_produk' => $product->kode_produk,
                'type_pembelian' => $product->type_pembelian,
                'harga_product' => $product->harga_product,
                'kategori' => [
                    'id' => optional($product->kategori)->id,
                    'name' => optional($product->kategori)->name,
                    'kode_kategori' => optional($product->kategori)->kode_kategori,
                ],
                'deskripsi' => $product->deskripsi,
                // 'stok' => $product->stok,
                // 'ongkir' => $product->ongkir,
                'created_at' => $product->created_at,
                'updated_at' => $product->updated_at,
            ]
        ]);
    }

    public function update(UpdateRequest $request, $id)
    {
        DB::beginTransaction();
    
        // Cari produk berdasarkan ID
        $product = Product::find($id);
        if (!$product) {
            return MessageActeeve::notFound('Product not found!');
        }
    
        try {
            // Ambil nama baru dari request, atau gunakan nama lama jika tidak ada perubahan
            $newName = $request->input('nama', $product->nama);
    
            // Log nama baru untuk debugging
            Log::info('Nama Baru: ' . $newName);
    
            // Tidak perlu generate kode produk baru, biarkan kode_produk tetap sama
            $newKodeProduk = $product->kode_produk; // Mempertahankan kode_produk lama
    
            // Log kode produk yang lama dan yang baru (tetap sama)
            Log::info('Kode Produk (Tetap): ' . $newKodeProduk);
    
            // Perbarui produk dengan nama dan kode_produk yang lama
            $product->update([
                'nama' => $newName,
                'id_kategori' => $request->input('id_kategori', $product->id_kategori),
                'deskripsi' => $request->input('deskripsi', $product->deskripsi),
                'stok' => $request->input('stok', $product->stok),
                'kode_produk' => $newKodeProduk, // Pertahankan kode produk lama
                'type_pembelian' => $request->input('type_pembelian', $product->type_pembelian),
                'harga_product' => $request->input('harga_product', $product->harga_product),
                // 'ongkir' => $request->input('ongkir', $product->ongkir),
            ]);
    
            DB::commit();
            return MessageActeeve::success("Product {$product->nama} has been updated");
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('Error updating product: ' . $th->getMessage());
            return MessageActeeve::error($th->getMessage());
        }
    }
    

    public function destroy(string $id)
    {
        DB::beginTransaction();

        // Cari product berdasarkan ID
        $product = Product::find($id);
        if (!$product) {
            return MessageActeeve::notFound('Product not found!');
        }

        try {
            // Hapus kategori
            $product->delete();

            // Commit transaksi jika berhasil
            DB::commit();
            return MessageActeeve::success("Product {$product->name} has been deleted");
        } catch (\Throwable $th) {
            // Rollback jika ada error
            DB::rollBack();
            return MessageActeeve::error($th->getMessage());
        }
    }
}















