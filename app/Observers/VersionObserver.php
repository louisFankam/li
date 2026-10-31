<?php

namespace App\Observers;

use Illuminate\Database\Eloquent\Model;

/**
 * Incrémente le compteur `version` à chaque écriture.
 *
 * Le pull de synchronisation ne renvoie que les enregistrements dont la
 * version est supérieure à celle connue du poste client : sans cette
 * incrémentation, `since` ne renverrait jamais rien.
 */
class VersionObserver
{
    public function saved(Model $model): void
    {
        // On évite la boucle infinie : la sauvegarde déclenchée ci-dessous
        // repasse dans `saved`, mais `version` n'est alors plus modifié.
        if ($model->isDirty('version')) {
            return;
        }

        $model->forceFill(['version' => (int) $model->getAttribute('version') + 1]);
        $model->saveQuietly();
    }
}
