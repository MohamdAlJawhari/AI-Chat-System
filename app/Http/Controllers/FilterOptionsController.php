<?php

namespace App\Http\Controllers;

use App\Services\FilterOptionsService;

class FilterOptionsController extends Controller
{
    public function __invoke(FilterOptionsService $service)
    {
        return response()->json($service->get());
    }
}
