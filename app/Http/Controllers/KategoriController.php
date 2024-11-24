<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Kategori;
use Illuminate\Http\Request;
use App\Facades\MessageActeeve;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Requests\Kategori\CreateRequest;
use App\Http\Requests\Kategori\UpdateRequest;
use App\Http\Resources\Kategori\KategoriCollection;

class KategoriController extends Controller
{
    public function index(Request $request)
    {
        $query = Kategori::query();

        // Filter pencarian berdasarkan 'id' atau 'name'
        if ($request->has('search')) {
            $query->where(function ($query) use ($request) {
                $query->where('id', 'like', '%' . $request->search . '%')
                      ->orWhere('name', 'like', '%' . $request->search . '%');
            });
        }

        // Filter berdasarkan 'kode_kategori'
        if ($request->has('kode_kategori')) {
            $query->where('kode_kategori', 'like', '%' . $request->kode_kategori . '%');
        }

        // Filter berdasarkan tanggal (range)
        if ($request->has('date')) {
            $date = str_replace(['[', ']'], '', $request->date);
            $date = explode(", ", $date);
            $query->whereBetween('created_at', [$date[0], $date[1]]);
        }

        // Paginate hasil query berdasarkan jumlah per halaman
        $kategori = $query->paginate($request->per_page);

        return new KategoriCollection($kategori);
    }

    public function store(CreateRequest $request)
    {
        DB::beginTransaction();

        try {
            // Membuat kategori baru dengan hanya mengambil 'name' dari request
            $kategori = Kategori::create([
                'name' => $request->name,
            ]);

            DB::commit();
            return MessageActeeve::success("Category {$kategori->name} has been successfully created");
        } catch (\Throwable $th) {
            DB::rollBack();
            return MessageActeeve::error($th->getMessage());
        }
    }

    public function show(string $id)
    {
        // Mengambil data kategori berdasarkan ID
        $kategori = Kategori::find($id);
        
        // Jika kategori tidak ditemukan
        if (!$kategori) {
            return MessageActeeve::notFound('Data not found!');
        }

        // Mengembalikan data kategori yang ditemukan
        return MessageActeeve::render([
            'status' => MessageActeeve::SUCCESS,
            'status_code' => MessageActeeve::HTTP_OK,
            'data' => [
                'id' => $kategori->id,
                'name' => $kategori->name,
                'kode_kategori' => $kategori->kode_kategori,
                'created_at' => $kategori->created_at,
                'updated_at' => $kategori->updated_at,
            ]
        ]);
    }

    public function update(UpdateRequest $request, $id)
    {
        DB::beginTransaction();

        // Cari kategori berdasarkan ID
        $kategori = Kategori::find($id);
        if (!$kategori) {
            return MessageActeeve::notFound('Category not found!');
        }

        try {
            // Ambil nama baru dari request, atau gunakan nama lama jika tidak ada perubahan
            $newName = $request->input('name', $kategori->name);

            // Log nama baru untuk debugging
            Log::info('Nama Baru: ' . $newName);

            // Generate slug untuk kode kategori baru dari nama baru
            $firstChar = substr($newName, 0, 1);
            $middleChar = substr($newName, (int)(strlen($newName) / 2), 1);
            $lastChar = substr($newName, -1);
            $nameSlug = strtoupper($firstChar . $middleChar . $lastChar);

            // Pecah kode kategori lama dengan asumsi format KTG-xxx-YYY-dd-mm-yyyy
            $kodeParts = explode('-', $kategori->kode_kategori);

            // Log kode kategori lama dan hasil pemecahan
            Log::info('Kode Kategori Lama: ' . $kategori->kode_kategori);
            Log::info('Kode Parts: ' . json_encode($kodeParts));

            // Validasi apakah kode kategori memiliki format yang benar
            if (count($kodeParts) === 4) {
                $incrementPart = $kodeParts[1]; // Bagian nomor increment tetap sama
                $datePart = $kodeParts[3]; // Bagian tanggal tetap sama
            } else {
                // Jika format tidak sesuai, gunakan default
                $incrementPart = '001';
                $datePart = Carbon::now()->format('d-m-Y');
            }

            // Buat kode kategori baru hanya dengan mengubah singkatan nama
            $newKodeKategori = "KTG-{$incrementPart}-{$nameSlug}-{$datePart}";

            // Log kode kategori yang baru
            Log::info('Kode Kategori Baru: ' . $newKodeKategori);

            // Perbarui kategori dengan nama dan kode_kategori yang baru
            $kategori->update([
                'name' => $newName,
                'kode_kategori' => $newKodeKategori, // Update kode kategori
            ]);

            DB::commit();
            return MessageActeeve::success("Category {$kategori->name} has been updated");
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('Error updating category: ' . $th->getMessage());
            return MessageActeeve::error($th->getMessage());
        }
    }

    public function destroy(string $id)
    {
        DB::beginTransaction();

        // Cari kategori berdasarkan ID
        $kategori = Kategori::find($id);
        if (!$kategori) {
            return MessageActeeve::notFound('Category not found!');
        }

        try {
            // Hapus kategori
            $kategori->delete();

            // Commit transaksi jika berhasil
            DB::commit();
            return MessageActeeve::success("Category {$kategori->name} has been deleted");
        } catch (\Throwable $th) {
            // Rollback jika ada error
            DB::rollBack();
            return MessageActeeve::error($th->getMessage());
        }
    }

}
