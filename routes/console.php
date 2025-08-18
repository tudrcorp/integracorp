<?php

use App\Jobs\DocumentUploadReminder;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Artisan::command('inspire', function () {
//     $this->comment(Inspiring::quote());
// })->purpose('Display an inspiring quote');

Schedule::job(new DocumentUploadReminder)->everyFiveMinutes();
// Schedule::command('reminder:uploaddoc')->everyFiveMinutes()->runInBackground();