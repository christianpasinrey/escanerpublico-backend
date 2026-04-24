<?php

namespace Modules\Contracts\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Contracts\Models\Contract;

class HealthController extends Controller
{
    public function show(): JsonResponse
    {
        return response()
            ->json([
                'status' => 'ok',
                'snapshot_updated_at' => Contract::max('snapshot_updated_at'),
                'contracts' => Contract::count(),
            ])
            ->header('Cache-Control', 'no-store, max-age=0');
    }
}
