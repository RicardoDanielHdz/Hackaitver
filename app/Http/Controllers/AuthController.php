<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            /** @var User $user */
            $user = Auth::user();

            return redirect($user->getDashboardRoute());
        }

        $demoUsers = User::orderBy('id')->get();

        return view('auth.login', compact('demoUsers'));
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $remember = $request->boolean('remember', true);

        if (Auth::attempt($credentials, $remember)) {
            $request->session()->regenerate();

            /** @var User $user */
            $user = Auth::user();

            return redirect()->intended($user->getDashboardRoute())
                ->with('status', 'Sesión activa: '.$user->name.' · '.$user->job_title);
        }

        return back()->withErrors([
            'email' => 'Credenciales inválidas. Verifique su correo corporativo y contraseña.',
        ])->onlyInput('email');
    }

    public function quickLogin(string $role): RedirectResponse
    {
        $targetRole = $role === 'operador_acomodo' ? 'operador' : $role;
        $user = User::where('role', $targetRole)->first();

        if ($user !== null) {
            Auth::login($user);
            request()->session()->regenerate();

            $redirectUrl = $role === 'operador_acomodo'
                ? route('wms.index')
                : $user->getDashboardRoute();

            return redirect($redirectUrl)
                ->with('status', 'Cambio de vista activo: '.$user->name.' ('.$user->job_title.')');
        }

        return redirect()->route('login')->withErrors([
            'email' => 'El perfil solicitado no se encuentra registrado.',
        ]);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Sesión finalizada correctamente.');
    }
}
