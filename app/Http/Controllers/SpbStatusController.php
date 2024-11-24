<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Facades\MessageActeeve;
use App\Models\SpbProject_Status;

class SpbStatusController extends Controller
{
    public function index()
    {
        $purchaseStatus = SpbProject_Status::whereNotIn('id', [SpbProject_Status::VERIFIED])->get();

        return MessageActeeve::render([
            'status' => MessageActeeve::SUCCESS,
            'status_code' => MessageActeeve::HTTP_OK,
            'data' => $purchaseStatus
        ]);
    }

    public function show($id)
    {
        $purchaseStatus = SpbProject_Status::find($id);
        if (!$purchaseStatus) {
            return MessageActeeve::notFound('data not found!');
        }

        return MessageActeeve::render([
            'status' => MessageActeeve::SUCCESS,
            'status_code' => MessageActeeve::HTTP_OK,
            'data' => $purchaseStatus
        ]);
    }
}
