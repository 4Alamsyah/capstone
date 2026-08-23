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
     *
     * For sub-module keys (module.*) the stored value is an access level, checked
     * against $minLevel. For boolean keys (approve.*) the stored value is a flag
     * and $minLevel is ignored.
     */
    public function handle(Request $request, Closure $next, string $permission, string $minLevel = User::LEVEL_VIEW): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403, 'Anda tidak memiliki akses ke menu/aksi ini.');
        }

        $value = $user->resolvedPermissions()[$permission] ?? false;

        $allowed = is_string($value)
            ? $user->hasAccess($permission, $minLevel)
            : (bool) $value;

        if (! $allowed) {
            abort(403, 'Anda tidak memiliki akses ke menu/aksi ini.');
        }

        return $next($request);
    }
}
