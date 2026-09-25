<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Serialize site access changes with operational writes, including access checks. */
class PharmacyWriteTransaction
{
    public function handle(Request $request, Closure $next)
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }
        abort_unless($request->user()?->organization_id, 403);
        DB::beginTransaction();
        try {
            DB::table('organizations')->where('id', $request->user()->organization_id)->lockForUpdate()->first();
            $response = $next($request);
            // Laravel may render exceptions inside the pipeline. Roll back error responses too.
            if ($response->getStatusCode() >= 400) {
                DB::rollBack();
            } else {
                DB::commit();
            }
            return $response;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
