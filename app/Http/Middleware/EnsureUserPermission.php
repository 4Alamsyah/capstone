<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserPermission
{
    /**
     * Ensure authenticated user has required permission.
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->hasPermission($permission)) {
            abort(403, 'Anda tidak memiliki akses ke menu/aksi ini.');
        }

        return $next($request);
    }
}
