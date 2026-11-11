<?php

namespace App\Http\Controllers;

use App\Support\NusantaraCulturalMap;

class CulturalMapController extends Controller
{
    public function __invoke()
    {
        return view('cultural-map.index', [
            'entries' => NusantaraCulturalMap::entries(),
        ]);
    }
}
