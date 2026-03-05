<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;

class LoginController extends Controller
{
    use AuthenticatesUsers;

    protected $redirectTo = '/dashboard';

    public function __construct() {
        $this->middleware('guest')->except('logout');
        $this->middleware('auth')->only('logout');
    }

    protected function authenticated(Request $request, $user) {
        if ($user->hasRole('admin')) {
            return redirect()->route('dashboard');
        }
        if ($user->hasRole('teacher')) {
            return redirect()->route('dashboard');
        }
        if ($user->hasRole('student')) {
            return redirect()->route('dashboard');
        }
        return redirect($this->redirectTo);
    }
}
