<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Plafonds de remise par rôle
    |--------------------------------------------------------------------------
    |
    | Au-delà du plafond, la remise nécessite la permission `approve_discount`
    | (gérant / responsable). Toute remise est tracée dans l'audit.
    |
    */
    'caps' => [
        'vendeur' => ['percent' => 5, 'amount' => 10000],
        'caissier' => ['percent' => 5, 'amount' => 10000],
        'pharmacien' => ['percent' => 10, 'amount' => 50000],
        'gerant' => ['percent' => 25, 'amount' => 250000],
        'responsable_regional' => ['percent' => 30, 'amount' => 500000],
        'administrateur' => ['percent' => 100, 'amount' => PHP_INT_MAX],
    ],

    'default' => ['percent' => 0, 'amount' => 0],

    /** Remise maximale absolue, même pour un approbateur. */
    'absolute_max_percent' => 50,

];
