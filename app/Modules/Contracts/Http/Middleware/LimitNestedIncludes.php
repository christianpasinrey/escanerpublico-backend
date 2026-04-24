<?php

namespace Modules\Contracts\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LimitNestedIncludes
{
    public function handle(Request $request, Closure $next, int $maxDepth = 3): Response
    {
        $include = (string) $request->query('include', '');
        if ($include === '') {
            return $next($request);
        }

        foreach (explode(',', $include) as $item) {
            $depth = substr_count(trim($item), '.');
            if ($depth >= $maxDepth) {
                return response()->json([
                    'error' => 'include_too_deep',
                    'message' => "Max include depth is {$maxDepth} (got: {$depth})",
                ], 400);
            }
        }

        return $next($request);
    }
}
