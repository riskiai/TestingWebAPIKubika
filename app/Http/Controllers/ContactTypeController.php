<?php

namespace App\Http\Controllers;

use App\Models\ContactType;
use Illuminate\Http\Request;
use App\Facades\MessageActeeve;

class ContactTypeController extends Controller
{
    public function index()
    {
        $contactTypes = ContactType::get();

        return MessageActeeve::render([
            'status' => MessageActeeve::SUCCESS,
            'status_code' => MessageActeeve::HTTP_OK,
            'data' => $contactTypes
        ]);
    }

    public function show($id)
    {
        $contactType = ContactType::find($id);
        if (!$contactType) {
            return MessageActeeve::notFound('data not found!');
        }

        return MessageActeeve::render([
            'status' => MessageActeeve::SUCCESS,
            'status_code' => MessageActeeve::HTTP_OK,
            'data' => $contactType
        ]);
    }
}
