<?php

namespace App\Http\Controllers;

use App\Support\IndonesianDestinations;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DestinationController extends Controller
{
    public function show(string $slug): View
    {
        $destination = collect(IndonesianDestinations::all())
            ->first(fn (array $destination): bool => Str::slug($destination['name']) === $slug);

        abort_if($destination === null, 404);

        return view('destinations.show', compact('destination'));
    }
}
