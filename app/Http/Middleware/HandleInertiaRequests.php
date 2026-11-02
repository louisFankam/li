<?php

namespace App\Http\Middleware;

use App\Models\Pharmacy;
use App\Support\Permissions;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        $user = $request->user();
        $pharmacies = $user?->is_super_admin
            ? Pharmacy::query()->orderBy('name')->get(['id', 'name', 'city'])
            : ($user
                ? $user->pharmacies()->orderBy('name')->get(['pharmacies.id', 'name', 'city'])
                : collect());

        // La pharmacie active est résolue par le contexte (session + fallback
        // sur la première pharmacie accessible), afin que la Topbar et la
        // Sidebar reflètent toujours le site réellement utilisé.
        $activePharmacy = $user?->currentPharmacy();
        $activePharmacyId = $activePharmacy?->id;
        if ($activePharmacyId && ! $pharmacies->contains('id', (int) $activePharmacyId)) {
            $activePharmacyId = null;
        }

        $role = $user?->roleFor($activePharmacy);
        $permissions = $user
            ? collect(Permissions::all())
                ->filter(fn (string $permission) => $user->hasPermission($permission))
                ->values()
                ->all()
            : [];

        return array_merge(parent::share($request), [
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $user
                    ? [
                        ...$user->toArray(),
                        'role' => $role,
                    ]
                    : null,
                'permissions' => $permissions,
            ],
            'pharmacies' => $pharmacies->map(fn ($pharmacy) => [
                'id' => $pharmacy->id,
                'name' => $pharmacy->name,
                'short' => str_replace('Pharmacie ', '', $pharmacy->name),
                'city' => $pharmacy->city,
            ])->values(),
            'activePharmacyId' => $activePharmacyId,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ]);
    }
}
