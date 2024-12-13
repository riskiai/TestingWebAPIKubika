<?php

namespace App\Http\Controllers\User;

use App\Models\User;
use App\Mail\RegisterMail;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Facades\MessageActeeve;
use App\Mail\ResetPasswordMail;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Http\Requests\User\CreateRequest;
use App\Http\Requests\User\UpdateRequest;
use App\Http\Resources\Users\UsersCollection;
use App\Http\Requests\User\UpdatePasswordRequest;

class UsersController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query();

        // Filter berdasarkan parameter 'search'
        if ($request->has('search')) {
            $query->where(function ($query) use ($request) {
                $query->where('id', 'like', '%' . $request->search . '%')
                    ->orWhere('name', 'like', '%' . $request->search . '%')
                    ->orWhereHas('role', function ($query) use ($request) {
                        $query->where('role_name', 'like', '%' . $request->search . '%');
                    })
                    ->orWhereHas('divisi', function ($query) use ($request) {
                        $query->where('name', 'like', '%' . $request->search . '%');
                    });
            });
        }

        // Filter berdasarkan 'divisi_name' secara spesifik
        if ($request->has('divisi_name')) {
            $query->whereHas('divisi', function ($query) use ($request) {
                $query->where('name', 'like', '%' . $request->divisi_name . '%');
            });
        }

        // Filter berdasarkan rentang tanggal (parameter 'date')
        if ($request->has('date')) {
            $date = str_replace(['[', ']'], '', $request->date);
            $date = explode(", ", $date);
            $query->whereBetween('created_at', $date);
        }

        $users = $query->paginate($request->per_page);

        return new UsersCollection($users);
    }


    public function show($id)
    {
        $user = User::find($id);
        if (!$user) {
            return MessageActeeve::notFound('User not found!');
        }

        return MessageActeeve::render([
            'status' => MessageActeeve::SUCCESS,
            'status_code' => MessageActeeve::HTTP_OK,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => [
                    'id' => $user->role->id,
                    'role_name' => $user->role->role_name,
                ],
                'divisi' => [
                    'id' => $user->divisi->id ?? null,
                    'name' => $user->divisi->name ?? null,
                    'kode_divisi' => $user->divisi->kode_divisi ?? null,
                ],
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ]
        ]);
    }

    public function store(CreateRequest $request)
    {
        DB::beginTransaction();

        try {
            // Generate password acak 6 karakter
            $randomPassword = $this->generateRandomPassword();

            // Buat user baru dengan nama, email, password, role, dan divisi
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => bcrypt($randomPassword), // Enkripsi password acak
                'role_id' => $request->role,
                'divisi_id' => $request->divisi,
            ]);

            $user->salary()->create([
                "daily_salary" => $request->daily_salary,
                "hourly_salary" => $request->hourly_salary,
                "hourly_overtime_salary" => $request->hourly_overtime_salary,
            ]);

            // Simpan password acak dalam atribut sementara untuk email
            $user->passwordRecovery = $randomPassword;

            // Kirim email ke pengguna dengan kata sandi acak
            Mail::to($user->email)->send(new RegisterMail($user));

            DB::commit();

            // Tambahkan info password acak ke pesan sukses
            return MessageActeeve::success("User {$user->name} has been successfully created with role {$user->role->role_name}");
        } catch (\Throwable $th) {
            DB::rollBack();
            return MessageActeeve::error($th->getMessage());
        }
    }


    public function update(UpdateRequest $request, $id)
    {
        DB::beginTransaction();

        $user = User::find($id);
        if (!$user) {
            return MessageActeeve::notFound('Data not found!');
        }

        try {
            $userData = [];

            // Update bidang-bidang yang disertakan dalam permintaan
            if ($request->has('name')) {
                $userData['name'] = $request->name;
            }
            if ($request->has('email')) {
                $userData['email'] = $request->email;
            }
            if ($request->has('role')) {
                $userData['role_id'] = $request->role;
            }
            if ($request->has('divisi')) { // Tambahkan pengecekan untuk update divisi
                $userData['divisi_id'] = $request->divisi;
            }

            $user->update($userData);
            
            if ($user->salary ) {
                $user->salary->update([
                    "daily_salary" => $request->daily_salary,
                    "hourly_salary" => $request->hourly_salary,
                    "hourly_overtime_salary" => $request->hourly_overtime_salary,
                ]);
            }else {
                $user->salary()->create([
                    "daily_salary" => $request->daily_salary,
                    "hourly_salary" => $request->hourly_salary,
                    "hourly_overtime_salary" => $request->hourly_overtime_salary,
                ]);
            }

            DB::commit();
            return MessageActeeve::success("User $user->name has been updated");
        } catch (\Throwable $th) {
            DB::rollBack();
            return MessageActeeve::error($th->getMessage());
        }
    }

    /**
     * Generate a random 6-character password
     *
     * @return string
     */
    private function generateRandomPassword(): string
    {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $password = substr(str_shuffle($characters), 0, 6);
        return $password;
    }

    public function updatepassword(UpdatePasswordRequest $request)
    {
        DB::beginTransaction();

        $user = User::findOrFail(auth()->user()->id);

        // Verifikasi apakah old_password cocok dengan kata sandi saat ini
        if (!Hash::check($request->old_password, $user->password)) {
            return MessageActeeve::error("Old password does not match");
        }

        try {
            // Update password dengan password baru yang di-hash
            $user->update([
                "password" => Hash::make($request->new_password)
            ]);

            DB::commit();
            return MessageActeeve::success("User $user->name has been updated");
        } catch (\Throwable $th) {
            DB::rollBack();
            return MessageActeeve::error($th->getMessage());
        }
    }

    public function resetPassword(Request $request, $id)
    {
        DB::beginTransaction();

        $user = User::find($id);
        if (!$user) {
            return MessageActeeve::notFound("data not found!");
        }

        $password = Str::random(8);
        if ($request->has('password')) {
            $password = $request->password;
        }

        try {
            $user->update([
                "password" => Hash::make($password)
            ]);
            $user->passwordRecovery = $password;

            Mail::to($user)->send(new ResetPasswordMail($user));

            DB::commit();
            return MessageActeeve::success("user $user->name has been updated");
        } catch (\Throwable $th) {
            DB::rollBack();
            return MessageActeeve::error($th->getMessage());
        }
    }

    public function destroy(string $id)
    {
        DB::beginTransaction();

        $user = User::find($id);
        if (!$user) {
            return MessageActeeve::notFound('data not found!');
        }

        try {
            $user->delete();

            DB::commit();
            return MessageActeeve::success("user $user->name has been deleted");
        } catch (\Throwable $th) {
            DB::rollBack();
            return MessageActeeve::error($th->getMessage());
        }
    }

}
