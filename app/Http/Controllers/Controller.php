<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    // Provides $this->authorize(), which every record-level action must call.
    use AuthorizesRequests;
}
