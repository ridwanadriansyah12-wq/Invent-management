<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Usage in routes: middleware('role:it,procurement')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();

        if (!$user->is_active) {
            auth()->logout();
            return redirect()->route('login')->with('error', 'Akun Anda telah dinonaktifkan.');
        }

        // Map legacy role names to new RBAC roles
        $normalizedRoles = array_map(function ($r) {
            return match($r) {
                'it'          => 'admin',
                'procurement' => 'approver',
                'gudang'      => 'staff',
                default       => $r,
            };
        }, $roles);

        // Admin has super-user access to all roles
        if ($user->role === 'admin' || in_array($user->role, $normalizedRoles, true)) {
            return $next($request);
        }

        abort(403, 'Akses ditolak: Anda tidak memiliki izin untuk melakukan tindakan ini.');
    }
}
