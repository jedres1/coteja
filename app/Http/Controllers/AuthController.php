<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AuthController extends Controller
{
    public function showLogin()
    {
        return Inertia::render('Auth/Login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (!Auth::attempt([...$credentials, 'is_active' => true], $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Credenciales invalidas.'])->onlyInput('email');
        }

        $request->session()->regenerate();

        $user = Auth::user();

        // Honor any intended URL stored before the login redirect
        $intended = $request->session()->pull('url.intended');
        if ($intended) {
            return redirect($intended);
        }

        // Route by role: admins go to dashboard, inquilinos to company selection
        return redirect()->route(
            $user->isAdmin() ? 'admin.dashboard' : 'admin.seleccionar-empresa'
        );
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
