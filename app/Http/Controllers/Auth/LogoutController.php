<?php

namespace App\Http\Controllers\Auth;

use App\Facades\MessageActeeve;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken; // Impor model untuk personal access token

class LogoutController extends Controller
{
    public function __invoke(Request $request)
    {
        DB::beginTransaction();

        try {
            // Ambil token dari header Authorization
            $token = $request->bearerToken();
            
            // Hapus token dari tabel personal_access_tokens secara langsung
            if ($token) {
                $hashedToken = hash('sha256', $token);
                
                // Cari token di tabel personal_access_tokens dan hapus
                $personalAccessToken = PersonalAccessToken::where('token', $hashedToken)->first();
                
                if ($personalAccessToken) {
                    $personalAccessToken->delete();
                }
            }

            DB::commit();
            return MessageActeeve::success('Logout successfully!');
        } catch (\Throwable $th) {
            DB::rollBack();
            return MessageActeeve::error($th->getMessage());
        }
    }
}
