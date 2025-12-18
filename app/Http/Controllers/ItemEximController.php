<?php

namespace App\Http\Controllers;

use App\Imports\ItemExim;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ItemEximController extends Controller
{
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls',
        ]);

        Excel::import(new ItemExim, $request->file('file'));

        return response()->json([
            'message' => 'Import completed successfully'
        ]);
    }
}
