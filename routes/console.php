<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('demo:seed-if-empty', function () {
    $hasDemoData = DB::table('users')->exists()
        || DB::table('categories')->exists()
        || DB::table('services')->exists();

    if ($hasDemoData) {
        $this->info('Demo seed skipped: database already has data.');
        return self::SUCCESS;
    }

    $this->call('db:seed', [
        '--class' => 'Database\\Seeders\\DatabaseSeeder',
        '--force' => true,
    ]);

    $this->info('Demo seed completed.');
    return self::SUCCESS;
})->purpose('Seed BoukKuk demo data only when the database is empty');
