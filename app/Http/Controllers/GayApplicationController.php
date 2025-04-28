<?php

namespace App\Http\Controllers;

use App\Models\GayApplication;
use Illuminate\Http\Request;

class GayApplicationController extends Controller
{
    public function view(GayApplication $record)
    {
        return view('gay_application.view', ['record' => $record]);
    }
}
