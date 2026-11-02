<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Pharmacy;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\PharmacyContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index(Request $request, PharmacyContext $context)
    {
        $this->authorize('viewAny', User::class);

        $actor = $request->user();
        $managedPharmacies = Pharmacy::query()
            ->whereIn('id', $context->accessibleIds($request))
            ->orderBy('name')
            ->get()
            ->filter(fn (Pharmacy $pharmacy) => $actor->hasPermission('manage_users', $pharmacy))
            ->values();

        $managedIds = $managedPharmacies->pluck('id')->all();

        $users = User::with(['pharmacies' => fn ($query) => $query->whereIn('pharmacies.id', $managedIds)])
            ->orderBy('name')
            ->get()
            ->filter(fn (User $user) => $actor->is_super_admin || $actor->canManageUser($user))
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_super_admin' => $user->is_super_admin,
                'can_update' => $actor->can('update', $user),
                'can_delete' => $actor->can('delete', $user),
                'pharmacies' => $user->pharmacies->map(fn ($pharmacy) => [
                    'id' => $pharmacy->id,
                    'name' => $pharmacy->name,
                    'role' => $pharmacy->pivot->role,
                ])->values(),
            ])->values();

        $rolesByPharmacy = $managedPharmacies->mapWithKeys(fn (Pharmacy $pharmacy) => [
            (string) $pharmacy->id => collect(User::ROLES)
                ->filter(fn (string $role) => $actor->canAssignRole($role, $pharmacy))
                ->values()
                ->all(),
        ]);

        return Inertia::render('Users', [
            'users' => $users,
            'pharmacies' => $managedPharmacies->map(fn (Pharmacy $pharmacy) => [
                'id' => $pharmacy->id,
                'name' => $pharmacy->name,
            ])->values(),
            'roles' => $rolesByPharmacy->flatten()->unique()->values()->all(),
            'roles_by_pharmacy' => $rolesByPharmacy,
        ]);
    }

    public function store(StoreUserRequest $request)
    {
        $this->authorize('create', User::class);

        $data = $request->validated();
        $this->assertAssignmentsAllowed($request->user(), $data['pharmacies']);

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
        $this->assertAssignmentsAllowed($request->user(), $data['pharmacies']);
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

    /**
     * @param  array<int, array{id: int, role: string}>  $assignments
     */
    private function assertAssignmentsAllowed(User $actor, array $assignments): void
    {
        foreach ($assignments as $index => $assignment) {
            $pharmacy = Pharmacy::query()->find($assignment['id']);

            if ($pharmacy === null
                || ! $actor->canAccessPharmacy($pharmacy)
                || ! $actor->canAssignRole($assignment['role'], $pharmacy)) {
                throw ValidationException::withMessages([
                    "pharmacies.{$index}.role" => 'Vous ne pouvez pas attribuer ce rôle sur cette pharmacie.',
                ]);
            }
        }
    }
}
