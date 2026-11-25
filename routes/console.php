<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('moneygemen:cleanup')->daily();
