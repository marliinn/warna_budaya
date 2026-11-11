<?php

namespace App\Http\Controllers;

use App\Support\NusantaraDigitalBooks;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;

class DigitalBookController extends Controller
{
    public function index(): View
    {
        return view('digital-books.index', [
            'books' => NusantaraDigitalBooks::all(),
        ]);
    }

    public function show(string $slug): View|Response
    {
        $book = NusantaraDigitalBooks::find($slug);

        abort_if($book === null, 404);

        return view('digital-books.show', [
            'book' => $book,
            'books' => NusantaraDigitalBooks::all(),
        ]);
    }
}
