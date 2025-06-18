<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('picking:run')->everyFiveMinutes();
Schedule::command('printers:keepAlive')->everyMinute();
Schedule::command('dropbox:refreshToken')->everyFourHours();
