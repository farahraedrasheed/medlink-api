<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
| Scheduled tasks go here.
*/

// Auto-expire broadcast requests every 5 minutes
Schedule::command('requests:expire')->everyFiveMinutes();
