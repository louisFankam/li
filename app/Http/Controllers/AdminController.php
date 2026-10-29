<?php

namespace App\Http\Controllers;

use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminController extends Controller
{
    public function index(Request $request)
    {
        $pharmacies = Pharmacy::all()->map(fn($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'ville' => $p->city,
            'active' => $p->is_active,
        ]);

        $users = User::with('pharmacies')->get()->map(fn($u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'role' => $u->pharmacies->first()?->pivot?->role ?? 'administrateur',
            'pharmacies' => $u->pharmacies->pluck('name')->implode(', '),
        ]);

        return Inertia::render('Administration', [
            'pharmacies' => $pharmacies,
            'users' => $users,
        ]);
    }
}
