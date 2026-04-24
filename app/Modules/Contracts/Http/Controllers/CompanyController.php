<?php

namespace Modules\Contracts\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Contracts\Models\Company;

class CompanyController
{
    public function index(Request $request): JsonResponse
    {
        $query = Company::query();

        if ($q = $request->input('q')) {
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                    ->orWhere('nif', 'like', "%{$q}%");
            });
        }

        return response()->json(
            $query->withCount('awards')
                ->orderByDesc('awards_count')
                ->paginate($request->input('per_page', 25))
        );
    }

    public function show(int $id): JsonResponse
    {
        $company = Company::with(['addresses', 'contacts'])->findOrFail($id);
        $company->setAttribute('awards_count', $company->awards()->count());

        return response()->json($company);
    }

    public function stats(int $id): JsonResponse
    {
        Company::findOrFail($id);

        return response()->json(['total_awards' => 0, 'total_amount' => 0, 'by_year' => []]);
    }
}
