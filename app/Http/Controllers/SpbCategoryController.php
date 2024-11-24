<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Facades\MessageActeeve;
use App\Models\SpbProject_Category;

class SpbCategoryController extends Controller
{
    public function index()
    {
        $purchaseCategories = SpbProject_Category::all();

        return MessageActeeve::render([
            'status' => MessageActeeve::SUCCESS,
            'status_code' => MessageActeeve::HTTP_OK,
            'data' => $purchaseCategories
        ]);
    }

    public function show($id)
    {
        $purchaseCategory = SpbProject_Category::find($id);
        if (!$purchaseCategory) {
            return MessageActeeve::notFound('data not found!');
        }

        return MessageActeeve::render([
            'status' => MessageActeeve::SUCCESS,
            'status_code' => MessageActeeve::HTTP_OK,
            'data' => $purchaseCategory
        ]);
    }
}
