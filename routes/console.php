<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('warna:info', function () {
    $this->info('WARNA BUDAYA Laravel project siap digunakan.');
})->purpose('Menampilkan informasi project WARNA BUDAYA');

Schedule::command('analytics:prune')->daily();
