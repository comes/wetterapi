<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Archive;

class WeatherController extends Controller
{
    public function weather() {
	$pageSize = request()->get('pageSize',500);
    	return Archive::paginate($pageSize);
    }

    public function current() {
       	return Archive::first();
    }
}
