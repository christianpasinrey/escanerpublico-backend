<?php

namespace Modules\Contracts\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Contracts\Models\Organization;

class OrganizationController
{
    public function index(Request $request): JsonResponse
    {
        $query = Organization::query();

        if ($q = $request->input('q')) {
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                  ->orWhere('nif', 'like', "%{$q}%")
                  ->orWhere('identifier', 'like', "%{$q}%");
            });
        }

        return response()->json(
            $query->withCount('contracts')
                ->orderByDesc('contracts_count')
                ->paginate($request->input('per_page', 25))
        );
    }

    public function show(Organization $organization): JsonResponse
    {
        $organization->load(['addresses.country', 'addresses.city', 'contacts']);
        $organization->loadCount('contracts');

        return response()->json($organization);
    }
}
