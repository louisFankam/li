<?php

namespace App\Http\Middleware;

use App\Services\PharmacyContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Initialise et valide le contexte pharmacie pour chaque requête authentifiée.
 * Empêche aussi qu'une session conserve une pharmacie devenue inaccessible.
 */
class EnsurePharmacyScope
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $context = app(PharmacyContext::class);
            $ids = $context->accessibleIds($request);

            // Aucune pharmacie : on laisse passer (compte technique, pages de
            // compte, admin global). Les contrôleurs métier appellent
            // PharmacyContext::requireActive() qui renvoie 403.
            if ($ids === []) {
                return $next($request);
            }

            $active = $request->session()->get('active_pharmacy_id');
            if ($active !== null && ! in_array((int) $active, $ids, true)) {
                $request->session()->forget('active_pharmacy_id');
                $active = null;
            }

            if ($active === null) {
                $request->session()->put('active_pharmacy_id', $ids[0]);
            }
        }

        return $next($request);
    }
}
