<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Archive;

class WeatherController extends Controller
{
    public function weather() {
    	return Archive::paginate(48);
    }

    public function current() {
       	return Archive::first();
    }
}
