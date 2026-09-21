<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class PhiAccessLoggingMiddleware
{
    /**
     * Handle an incoming request.
     * Logs access to Protected Health Information (PHI) for HIPAA compliance.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $response = $next($request);

        // Check if this is a PHI-related endpoint
        $phiEndpoints = [
            'clients', 'patients', 'cases', 'documents', 'medical', 'treatment', 'records'
        ];

        $isPhiAccess = false;
        foreach ($phi_endpoints as $endpoint) {
            if (str_contains($request->path(), $endpoint)) {
                $isPhiAccess = true;
                break;
            }
        }

        if ($isPhiAccess && $user) {
            $this->logPhiAccess($request, $user, $response->getStatusCode());
        }

        return $response;
    }

    /**
     * Log PHI access for HIPAA compliance
     */
    private function logPhiAccess(Request $request, $user, int $statusCode): void
    {
        $logData = [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'role' => $user->role,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'endpoint' => $request->path(),
            'method' => $request->method(),
            'status_code' => $statusCode,
            'timestamp' => now()->toIso8601String(),
            'request_id' => uniqid('phi_', true),
        ];

        // Log to audit_logs table if available
        try {
            \App\Models\AuditLog::create([
                'user_id' => $user->id,
                'organization_id' => $user->organization_id,
                'event' => 'phi_access',
                'description' => 'PHI access: ' . $request->path(),
                'metadata' => json_encode($logData),
                'ip_address' => $request->ip(),
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Fallback to file logging if table doesn't exist
            Log::channel('daily')->info('PHI Access Log', $logData);
        }
    }
}
