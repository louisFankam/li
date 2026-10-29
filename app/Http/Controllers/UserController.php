<?php

namespace App\Http\Controllers;

use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with('pharmacies')->get()->map(fn($u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'pharmacies' => $u->pharmacies->map(fn($p) => ['id' => $p->id, 'name' => $p->name, 'role' => $p->pivot->role])->values(),
        ]);
        return Inertia::render('Users', ['users' => $users, 'pharmacies' => Pharmacy::orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'pharmacy_id' => 'required|exists:pharmacies,id',
            'role' => 'required|string|max:40',
        ]);
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'email_verified_at' => now(),
        ]);
        $user->pharmacies()->attach($data['pharmacy_id'], ['role' => $data['role']]);
        return redirect()->back()->with('success', 'Utilisateur créé');
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'pharmacy_id' => 'required|exists:pharmacies,id',
            'role' => 'required|string|max:40',
        ]);
        $user->update(['name' => $data['name'], 'email' => $data['email']]);
        $user->pharmacies()->sync([$data['pharmacy_id'] => ['role' => $data['role']]]);
        return redirect()->back()->with('success', 'Utilisateur mis à jour');
    }

    public function destroy(User $user)
    {
        $user->delete();
        return redirect()->back()->with('success', 'Utilisateur supprimé');
    }
}
