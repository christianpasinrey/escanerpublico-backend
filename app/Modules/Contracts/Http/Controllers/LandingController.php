<?php

namespace Modules\Contracts\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Modules\Contracts\Services\Stats\LandingStatsService;

class LandingController extends Controller
{
    public function show(LandingStatsService $stats): Response
    {
        return response()
            ->view('landing.index', ['stats' => $stats->cached()])
            ->header('Cache-Control', 'public, s-maxage=300, stale-while-revalidate=3600');
    }
}
