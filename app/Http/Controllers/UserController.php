<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Pharmacy;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('manage_users');

        $users = User::with('pharmacies')->orderBy('name')->get()->map(fn ($u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'is_super_admin' => $u->is_super_admin,
            'pharmacies' => $u->pharmacies->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'role' => $p->pivot->role,
            ])->values(),
        ]);

        return Inertia::render('Users', [
            'users' => $users,
            'pharmacies' => Pharmacy::orderBy('name')->get(),
            'roles' => User::ROLES,
        ]);
    }

    public function store(StoreUserRequest $request)
    {
        $data = $request->validated();

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'email_verified_at' => now(),
            ]);

            $sync = [];
            foreach ($data['pharmacies'] as $p) {
                $sync[$p['id']] = ['role' => $p['role']];
            }
            $user->pharmacies()->sync($sync);

            return $user;
        });

        app(AuditLogger::class)->log('user.created', User::class, $user->id, [], [
            'name' => $user->name,
            'email' => $user->email,
            'pharmacies' => $data['pharmacies'],
        ]);

        return redirect()->back()->with('success', 'Utilisateur créé');
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $this->authorize('update', $user);

        $data = $request->validated();
        $old = $user->toArray();

        DB::transaction(function () use ($user, $data) {
            $user->update(['name' => $data['name'], 'email' => $data['email']]);

            $sync = [];
            foreach ($data['pharmacies'] as $p) {
                $sync[$p['id']] = ['role' => $p['role']];
            }
            $user->pharmacies()->sync($sync);
        });

        app(AuditLogger::class)->log('user.updated', User::class, $user->id, $old, [
            'name' => $data['name'],
            'email' => $data['email'],
            'pharmacies' => $data['pharmacies'],
        ]);

        return redirect()->back()->with('success', 'Utilisateur mis à jour');
    }

    public function destroy(Request $request, User $user)
    {
        $this->authorize('delete', $user);

        abort_if($request->user()->id === $user->id, 403, 'Vous ne pouvez pas supprimer votre propre compte.');

        $old = $user->toArray();
        $user->delete();

        app(AuditLogger::class)->log('user.deleted', User::class, $user->id, $old, []);

        return redirect()->back()->with('success', 'Utilisateur supprimé');
    }
}
