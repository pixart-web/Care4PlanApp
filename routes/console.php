<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('care4all:hello', function () {
    $this->info('Care4All pronto.');
});
