<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('transit:prune')->daily();
