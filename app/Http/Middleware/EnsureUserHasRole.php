<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Verifica que el usuario autenticado posea alguno de los roles permitidos para la ruta.
     */
    public function handle(Request $request, Closure $next, string ...$allowedRoles): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        if (in_array($user->role, $allowedRoles, true)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Acceso restringido: tu perfil actual no tiene permisos para esta operación. Cierra sesión e ingresa con la cuenta correspondiente.',
            ], 403);
        }

        return redirect($user->getDashboardRoute())
            ->with('status', 'Acceso restringido por perfil. Para ingresar a otro módulo, cierra sesión e inicia con el correo correspondiente.');
    }
}
