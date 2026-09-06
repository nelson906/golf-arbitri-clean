<?php

namespace App\Http\Controllers;

use App\Http\Concerns\InteractsWithAuthUser;

abstract class Controller
{
    use InteractsWithAuthUser;
}
