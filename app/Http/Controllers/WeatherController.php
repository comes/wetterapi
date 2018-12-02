<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Archive;

class WeatherController extends Controller
{
    public function weather()
    {
        $pageSize = request()->get('pageSize', 500);
        return Archive::paginate($pageSize);
    }

    public function current()
    {
        /** @var Collection */
        $data = Archive::first()->toArray();
        $data = array_add($data, 'rain24h', with(new Archive)->getRainPast24h());
        $data = array_add($data, 'rainCurrentMonth', with(new Archive)->getRainCurrentMonth());
        $data = array_add($data, 'rainLastMonth', with(new Archive)->getRainLastMonth());

        return $data;
    }
}
