# PharmaGest — Plan d'implémentation (cahier des charges v1)

> Fichier de suivi des tâches. Cocher `[x]` dès qu'une tâche est terminée et validée (tests + build).
> Une IA reprenant le projet doit lire ce fichier en premier, puis `docs/PLAN_IMPLEMENTATION.md` (feuille de route détaillée), puis `docs/cahier_des_charges...` (PDF) pour le détail métier.
> Statut global : socle MVP partiellement implémenté, plusieurs modules à compléter.
> **Plan directeur : `docs/PLAN_IMPLEMENTATION.md`** (phases, sécurité, design system, offline, tests, déploiement).

## Légende
- `[x]` fait et validé
- `[ ]` à faire
- `[-]` en cours / partiel
- `[!]` bloqué (dépendance externe)

---

## État actuel (déjà réalisé)

### Socle & infrastructure
- [x] Projet Laravel 12 + Vue 3 + Inertia + TypeScript + Tailwind (starter kit)
- [x] Authentification (login, reset password, vérification email) — enregistrement désactivé (projet client uniquement)
- [x] Layout app avec sidebar fixe (corrigé : `h-screen sticky`, scroll interne)
- [x] Topbar avec sélecteur de pharmacie active et indicateur de sync réel (pending Dexie, dernière sync, état réseau)
- [x] Migration `2026_10_28_164240_create_core_schema.php` (toutes les tables cœur)
- [x] Migration `2026_10_30_000001_add_sync_and_security_columns.php` + `2026_10_30_000002_business_hardening.php`
- [x] Seed `DemoSeeder` (3 pharmacies, utilisateurs, produits, stock, assureurs, clients, ventes) — idempotent et cohérent (statuts/totaux/paiements)

### Modules déjà implémentés (niveau basique)
- [x] Pharmacies : CRUD + activation (vue active)
- [x] Catégories : CRUD
- [x] Fournisseurs : CRUD
- [x] Produits : CRUD + liaison catégorie + stock par pharmacie
- [x] Utilisateurs : CRUD multi-sites (rôles : administrateur, responsable_regional, gerant, pharmacien, vendeur, caissier, gestionnaire_stock, comptable)
- [x] Clients : CRUD + édition + suppression (avec liaison assureur)
- [x] Assureurs : CRUD + édition + désactivation
- [x] Ventes : création avec items, paiements simples/mixtes, remise montant/pourcent, assurance (part client/assureur), décrément stock atomique avec verrou, aperçu serveur (`POST /vente/preview`), ticket imprimable (`?print=1`), annulation avec restock (`SaleService::cancel`)
- [x] Transferts : création, expédition, réception totale/partielle, annulation
- [x] Assurances : vue synthèse + liste des dossiers (soldes, bordereau, plafond de remboursement, transitions validées)
- [x] Caisses : cycle de vie (ouverture atomique, mouvements, clôture avec écart)
- [x] Tableau de bord : indicateurs réels (CA, tickets avec delta, alertes stock, dossiers en attente, caisse ouverte)
- [x] Audit : filtres + pagination + détail avant/après
- [x] Rapports : filtres période, exports ventes + bordereau assurances CSV
- [x] Administration : pharmacies + utilisateurs
- [x] Hors ligne/sync : pull (snapshot versionné `since` + objets métier), push (idempotence `client_event_id`, handlers cash/stock/inventaire/sale.cancelled), observers version, device token + boucle (start idempotent, CSRF via cookie `XSRF-TOKEN`), Topbar pending/last-sync réels, conflicts page + résolution.

### Valeurs fictives éliminées (audit V1)
- Sidebar : permissions, compteur Dexie, déconnexion, drawer mobile, `no-print`, `aria-label`
- Topbar : état réseau réel, compteur en attente, dernière synchro, menu mobile, bouton « Nouvelle vente » masqué sans `make_sales`, `no-print`
- Dashboard : CA/journée, tickets + delta, alertes stock, dossiers en attente réels ; liens vers pages concrètes
- Stocks : stats réelles (`value`, `ruptures`, `alerts`, `references`), filtres recherche/statut locaux, suppression valeurs factices
- Vente : aperçu serveur (debounce), panier borné au stock, remise + motif, paiements multiples, part client/assureur, `client_request_id`, désactivation tant que `reste à encaisser ≠ 0`
- Tickets : formatage GNF, impression, auto-print `?print=1`
- Login : suppression du doublon d’`<h1>`
- Achats : création, envoi, réception partielle, annulation
- Transferts : expédition/réception partielles, annulation idempotente

### Modules encore en cours / à valider
- [ ] Caisse et inventaire hors ligne (handlers push dédiés en attente côté client)
- [ ] Retours clients (à faire)
- [ ] Unités & conditionnements (vente fractionnée)
- [ ] Lots / péremption
- [ ] Rapports centraux (multi-sites)
- [ ] Tests sync coupure/reprise (manuel)
- [ ] 2FA, sauvegardes, déploiement, documentation utilisateur, procès-verbal de recette

---

## Phase 0 — Stabilisation & gouvernance (PRIORITÉ HAUTE)

### 0.1 Autorisation côté serveur
- [x] Système de permissions par rôle (`App\Support\Permissions`) + gates Laravel
- [x] `User::hasPermission`, `roleFor`, `currentPharmacy`, `canAccessPharmacy`
- [x] Gates appliqués dans Product, Sale, Purchase, Cash, Insurance, Inventory, Report, Audit controllers
- [x] Middleware `pharmacy.scope` dédié (logique dans `UsesActivePharmacy`, à renforcer)
- [x] Créer Policies par modèle (optionnel, gates suffisent pour l'instant)
- [-] Vérification `pharmacy_id` à chaque requête : `PharmacyContext::guard()` existe mais n'est appelé par aucun contrôleur ; l'isolation repose sur les policies, désormais couvertes par des tests cross-pharmacie sur ventes, caisses, achats, assurances, transferts, conflits et utilisateurs

### 0.2 Permissions granulaires
- [x] Choisir librairie (Spatie Permission ou tables custom `permissions`/`role_permissions`)
- [x] Définir les permissions : consultation, création, modification, validation, annulation, export, multi-sites, paramètres
- [x] Mapper les rôles CDC (admin client, responsable régional, gérant, pharmacien/délivrance, vendeur/caissier, gestionnaire stock, comptable/assurance) vers permissions
- [-] Appliquer les permissions dans routes/contrôleurs/UI : serveur durci et testé sur les parcours critiques ; masquage UI limité aux actions Utilisateurs et aux liens de navigation
- [x] Tests : accès cross-pharmacie refusé, permission manquante → 403 (`tests/Feature/Security`)

### 0.3 Utilisateurs multi-sites
- [x] `UserController::store/update` acceptent plusieurs pharmacies avec rôle par site
- [x] Frontend `Users.vue` envoie le format `pharmacies: [{id, role}]`
- [x] UI multi-sélection (`Users.vue`), limitée aux pharmacies administrables par l'acteur

### 0.4 Audit log
- [x] Service `AuditLogger` créé
- [x] Appel sur création/mise à jour/désactivation produit et création vente
- [x] Étendre à remises, annulations, corrections stock, clôture caisse, assurance, droits, sync
- [ ] Observer/model events pour tracer automatiquement les modifications de prix
- [x] Page Audit (lecture)

### 0.5 Validation & qualité
- [-] Form Requests pour chaque contrôleur : présents sur les parcours métier principaux ; validations inline restantes dans `SyncController`, `PurchaseController` et `PharmacyController`
- [x] Messages d'erreur en français
- [x] Tests policies + smoke étendu : security tests cross-pharmacie, matrice de permissions, utilisateurs, conflits et audit

**Sortie Phase 0** : droits testés, données isolées par site.

---

## Phase 1 — Stock, achats & inventaires

### 1.1 Achats & réceptions
- [x] `PurchaseController` + routes (index/store/receive/status) + page Achats
- [x] Page Achats (création commande, réception totale/partielle)
- [x] Réception totale/partielle avec saisie des quantités reçues
- [x] Incrémentation du stock uniquement à la réception validée (mouvement `reception`)
- [ ] Historique des prix d'achat par produit/fournisseur
- [ ] Enregistrement facture fournisseur + statut de paiement (optionnel)

### 1.2 Inventaires
- [x] `InventoryController` : comptage physique + ajustements (perte/casse/correction)
- [x] Calcul écart vs stock théorique + mouvement `inventaire`
- [x] Validation → mouvement `inventaire` avec justification
- [x] Page Inventaires avec comptage et ajustements

### 1.3 Ajustements de stock
- [x] Perte/casse/correction avec justification obligatoire + permission
- [x] Mouvement `perte`/`casse`/`correction`

### 1.4 Retours clients
- [ ] Opération de retour liée à la vente d'origine (motif, utilisateur, approbateur) (à faire)
- [ ] Restitution d'argent et remise en stock = actions distinctes et contrôlées
- [ ] Réintégration stock selon permission et nature du retour
- [x] Annulation de vente finalisée → restock + statut cancelled (avoir à compléter)

### 1.5 Unités & conditionnements
- [ ] Choix d'unité vendable dans la vente (boîte/plaquette/etc.) selon produit
- [ ] Conversion effective via `conversion_factor` (stock en unité de base)
- [ ] Ventes fractionnées uniquement si autorisées par produit

### 1.6 Lots (recommandation à valider)
- [ ] Clarifier "liens" → suivi numéro de lot sans péremption (à confirmer client)
- [ ] Ajouter `lot_number` sur réception et mouvement de stock (optionnel)

**Sortie Phase 1** : stock négatif bloqué, mouvements traçables (incl. réceptions partielles).

---

## Phase 2 — Vente & caisse complètes

### 2.1 Écran de vente
- [ ] Scanner code-barres (input auto-focus + Enter)
- [ ] Recherche instantanée par nom/DCI/code/code-barres
- [ ] Modification quantités et conditionnement autorisé
- [ ] Affichage immédiat prix, stock disponible, total
- [ ] Sélection client facultative
- [ ] Paiement simple ou réparti (déjà supporté, à finaliser UI)

### 2.2 Remises
- [x] Plafond par rôle (taux/montant max)
- [x] Validation par gérant au-delà du plafond (aucun contournement silencieux)
- [x] Prix minimum : remise plafonnée à 50 % du panier, vente à 0 GNF refusée ; contrôle sous coût d'achat non applicable sans marge fiable
- [x] Motif obligatoire + trace (montants origine/remise, utilisateur, approbateur)

### 2.3 Annulation / avoir
- [x] Vente finalisée non supprimée → annulation avec restock
- [ ] Lien vers vente d'origine, motif, utilisateur, approbateur (à compléter)

### 2.4 Tickets
- [-] Génération ticket imprimable (identité pharmacie, n°, date, lignes, remises, totaux, règlements, mentions légales) : ticket existant (`Ticket.vue`, auto-print `?print=1`) ; mentions légales manquantes
- [x] Réimpression (route `vente.ticket` accessible pour toute vente)
- [ ] Format réglementaire Guinée à confirmer

### 2.5 Paiements configurables
- [ ] Table `payment_methods` (espèces, mobile money, carte, autre)
- [ ] CRUD paramètres des moyens de paiement
- [ ] Paiement mixte (déjà supporté côté back)

### 2.6 Idempotence & traçabilité
- [x] Clé d'idempotence par vente (`client_request_id`)
- [x] `sale_number` robuste (UUID/séquence) au lieu de `count()+1`
- [ ] Origine de site sur chaque opération

### 2.7 Caisse (cycle de vie)
- [x] Ouverture de session (fond initial)
- [x] Mouvements de caisse (entrée/retrait)
- [x] Clôture : comparaison théorique vs compté + écart
- [x] Historique des sessions
- [x] Remplacer les valeurs statiques (dépenses/fond/résultat) par calculs réels

### 2.8 Tests
- [x] Vente avec stock suffisant diminue stock + enregistre paiements une seule fois (idempotence `client_request_id`)
- [x] Vente > stock bloquée même avec concurrence (deux processus MySQL)
- [x] Remise > plafond nécessite validation + laisse trace
- [-] Annulation : restock, motif, acteur, claim annulé et double annulation bloquée testés ; création d'un avoir et lien d'origine toujours à faire

**Sortie Phase 2** : scénarios de comptoir acceptés.

---

## Phase 3 — Assurances

### 3.1 Calcul
- [x] Appliquer `insurance_rules` (pourcentage/montant fixe) avec fallback 80/20
- [ ] Répartition claire avant validation (part client / part assureur)

### 3.2 Cycle de vie des dossiers
- [x] Statuts dossier modifiables (à facturer/envoyé/remboursé/rejeté/annulé)
- [x] Enregistrement règlements/rejets via statut + référence/notes
- [x] Montants de la vente non modifiés rétroactivement lors du règlement

### 3.3 Rapports & exports
- [ ] Rapports par assureur, pharmacie, période, montant facturé/reçu/solde dû (à faire)
- [ ] Exports des dossiers (CSV/PDF) (à faire)

### 3.4 Tests
- [x] Vente avec assurance calcule parts client/assureur et crée créance suivie jusqu'au règlement/rejet

**Sortie Phase 3** : rapprochement validé sur exemples réels.

---

## Phase 4 — Rapports, exports & tableaux de bord

### 4.1 Page Rapports
- [x] Filtres période (pharmacie/vendeur à étendre)
- [ ] Rapports : ventes, remises, stock, achats, assurances, caisses, transferts, activité utilisateurs

### 4.2 Exports
- [ ] Excel/CSV et PDF respectant les droits
- [ ] Métadonnées : période, pharmacie, date de génération, filtres appliqués

### 4.3 Tableau de bord central
- [ ] Vue réseau, sites, indicateurs, alertes, état de sync
- [ ] Signalisation des sites non synchronisés
- [ ] Valeurs réelles (pas de hardcode : fiabilité, dépenses, fond, résultat)

### 4.4 Tableau de bord pharmacie
- [ ] Ventes du jour, alertes stock, caisse, opérations en attente

---

## Phase 5 — Mode hors ligne & synchronisation (CHANTIER MAJEUR)

### 5.1 Architecture
- [ ] Décision infra : nœud local par pharmacie (Laravel/MySQL local) + serveur central (à budgéter)
- [ ] Déploiement local par site (réseau local, postes navigateur)

### 5.2 File d'événements
- [x] Table `sync_events` : idempotency key, origine site, type, payload, statut, tentatives
- [-] Chaque transaction locale → événement persistant : vente hors ligne branchée et atomique ; caisse et inventaire restent au LOT 6

### 5.3 Service de synchronisation
- [x] Envoi automatique à la reconnexion
- [x] Retries après échec sans perte : statut `failed` conservé et rejouable côté serveur, outbox client retentée
- [x] Accusé de réception; statut central "synchronisé" uniquement après ACK
- [x] Idempotence : répétition d'envoi ne crée pas de doublon

### 5.4 Conflits
- [x] Journalisation des conflits + présentation à un responsable
- [x] Résolution réelle : appliquer, annuler ou ajuster le payload, avec trace du responsable
- [x] Aucune opération écartée silencieusement : types inconnus rejetés, no-op transformés en conflit

### 5.5 Données de référence versionnées
- [-] Produits, clients, assureurs, règles d'assurance et stocks diffusés par journal monotone et tombstones ; règles de remise et comptes toujours hors pull

### 5.6 UI de sync
- [x] Indicateur permanent en haut (Topbar) : état partagé et réactif, nombre d'opérations locales et dernière synchronisation
- [ ] Rapports centraux : signaler sites non synchronisés
- [ ] Transferts inter-sites non confirmés tant que sync complète

### 5.7 Tests
- [ ] Coupure Internet → plusieurs ventes locales → sync sans doublon au retour
- [ ] Rapports centraux identifient sites en attente

**Sortie Phase 5** : tests de coupure/reprise sans perte ni doublon.

---

## Phase 6 — Sécurité, déploiement & documentation

### 6.1 Sécurité
- [ ] HTTPS entre clients, serveurs locaux, serveur central
- [x] Rate limiting sur login
- [x] Expiration de session + protection tentatives répétées
- [ ] 2FA (optionnel, à valider)
- [ ] Collecte minimale données personnelles; contrôle des exports
- [ ] Plan de mise à jour/surveillance des dépendances

### 6.2 Sauvegardes
- [ ] Sauvegardes automatisées, chiffrées/accès restreints, plusieurs générations
- [ ] Procédure de restauration testée avant livraison

### 6.3 Déploiement
- [ ] Procédures : installation, mise à jour, sauvegarde, restauration, synchronisation
- [ ] Monitoring journaux techniques et erreurs de sync

### 6.4 Documentation
- [ ] Schéma conceptuel des données + dictionnaire
- [ ] Documentation utilisateur courte (vendeurs, gérants, responsables centraux)
- [ ] Procès-verbal de recette + liste des limites connues

### 6.5 Version Laravel
- [ ] Valider upgrade Laravel 12 → 13 selon environnement (PHP 8.3+)

---

## Phase 7 — Tests automatisés (transversal)

- [ ] Tests feature par module : stock concurrent, permissions cross-site, remises, transferts, caisse, assurances, sync
- [ ] Tests unitaires règles métier (calculs, conversions, idempotence)
- [x] CI (GitHub Actions) : tests + lint + build

---

## Points à confirmer avec le client (avant démarrage)

- [ ] Clarifier "liens" → suivi numéro de lot sans péremption, ou autre fonctionnalité ?
- [ ] Nombre approximatif de pharmacies, caisses par pharmacie, utilisateurs simultanés
- [ ] Modèle réel de lecteur de codes-barres et d'imprimante de tickets
- [ ] Exemples de cartes/contrats/règles d'assurance et décomptes
- [ ] Exigences légales Guinée : justificatifs, fiscalité, délivrance catégories spécifiques, conservation, protection données
- [ ] Niveau de secours attendu (UPS, serveur de remplacement, procédure temporaire)
- [ ] Éventuelle ouverture future multi-organisations (SaaS) avec abonnements

---

## Ordre recommandé (selon cahier des charges)

1. Cadrage final (points ci-dessus)
2. Phase 0 — Socle sécurité/permissions
3. Phase 1 — Stock/achats/inventaires
4. Phase 2 — Vente/caisse
5. Phase 3 — Assurances
6. Phase 4 — Rapports/exports
7. Phase 5 — Hors ligne/sync
8. Phase 6/7 — Pilote, déploiement, tests, docs

## Mise à jour du 30/10/2026 — écarts constatés & compléments

> Détail complet dans `docs/PLAN_IMPLEMENTATION.md`. Les points ci-dessous complètent le TODO.

### Corrections d'état
- [x] `2.7` « Remplacer les valeurs statiques caisse » : déjà branché sur les props réelles (`Caisses.vue`).
- [-] `2.7` Clôture de caisse : calcul faux (inclut tous les moyens de paiement, ignore les mouvements de caisse).
- [-] `0.1` Autorisation : gates présents mais isolation par pharmacie non garantie (IDOR).
- [-] `1.2` Inventaires : signe d'ajustement libre (perte positive = augmentation de stock).
- [-] `2.3` Annulation : pas de motif/approbateur, dossier assurance non annulé.

### Sécurité (absent du TODO)
- [x] Recalcul serveur des prix (ne jamais faire confiance à `unit_price` client).
- [x] `PharmacyContext` + middleware `pharmacy.scope` + scoping des route-model-bindings.
- [x] Form Requests pour tous les contrôleurs.
- [x] Policies par modèle.
- [x] Rate limiting login + API sync ; en-têtes de sécurité.
- [-] Tests cross-pharmacie (403) ajoutés ; test dédié de prix falsifié toujours absent.

### Design system (absent du TODO)
- [ ] Figer les tokens (`app.css`, `tailwind.config.js`) et documenter.
- [ ] Primitives UI (Button, DataTable, StatusBadge, MoneyInput, Toast, OfflineBanner…).
- [ ] Supprimer toutes les valeurs factices (sidebar, dashboard, topbar, vente, stocks).
- [x] Navigation complète (Catégories, Clients, Assureurs, Audit, Administration).
- [ ] Responsive + accessibilité + impression ticket.

### Offline (préciser la Phase 5)
- [-] PWA : manifest + service worker (Workbox) + fallback hors ligne. Icônes 192/512 et favicon réels, manifeste `fr`, contrôle `npm run pwa:check` vert ; fallback réseau réel non testé hors ligne.
- [x] IndexedDB (Dexie) : snapshot produits/stock/clients/assureurs/caisse + `outbox`.
- [x] `sync_events` serveur (idempotence `client_event_id`) + `POST /api/sync/push` + `GET /api/sync/pull`.
- [ ] Vente hors ligne complète (recherche, panier, remise, assurance, ticket). Corrigé : **non fait**, `Vente.vue` lit le serveur et envoie via `router.post` uniquement ; aucune écriture dans l'outbox Dexie.
- [ ] Caisse et inventaire hors ligne.
- [x] Écran de conflits + indicateur de sync réel.
- [ ] Tests coupure/reprise sans perte ni doublon.

### Qualité
- [-] CI GitHub Actions : Pint, PHPStan, PHPUnit MySQL/SQLite, ESLint, Prettier, typecheck, Vitest, build et contrôle PWA verts localement ; Playwright toujours absent.
- [x] Tests de concurrence sur MySQL (`ConcurrentSaleTest`, deux processus sur le dernier article).

### Mise à jour 31/10/2026 — finalisation livrable V1
- [x] Sidebar : permissions réelles, compteur Dexie, déconnexion, drawer mobile, `no-print`, `aria-label`.
- [x] Topbar : état réseau réel (`useOnline`), compteur en attente, dernière synchro Dexie, menu mobile, bouton vente masqué sans `make_sales`.
- [x] `HandleInertiaRequests` : partage `auth.permissions`, `role`, `activePharmacyId`.
- [x] `SyncController::pull` : sous-requête clients corrigée, filtrage par `version` (`since`), `insurance_rules` inclus.
- [x] `VersionObserver` : incrément `version` sur produits/clients/assureurs/règles/stock.
- [x] `SyncController::process` : handlers `cash.*`, `stock.adjusted`, `inventory.counted`, `sale.cancelled` implémentés (réutilisent services).
- [x] `SaleService::cancel` : annulation centralisée (restock via `StockService`, audit, claim).
- [x] Sync client : `X-XSRF-TOKEN` (cookie fraîchement renouvelé) + `ensureDeviceToken()` (`POST /sync/device`) + boucle idempotent ; démarrée depuis le shell authentifié.
- [x] `DemoSeeder` : idempotent (`updateOrCreate`/`firstOrCreate`), ventes coherentes (totaux, paiements, assurances).
- [x] CRUD éditaire : clients (`PUT /customers/{id}`), assureurs (`PUT /insurers/{id}`), utilisateurs multi-sites (`PUT /users/{id}`).
- [x] Valeurs factices supprimées : dashboard (`+8,1 %`, `-5 résolues`, `14 dossiers`), stocks (`fiability`, achats/transferts en dur, `r.lot`), login (doublon `h1`).
- [x] Build + tests verts après toutes ces corrections ; parcours Vente → Ticket validé en navigateur (Playwright), plus de console error.


## Mise à jour du 02/11/2026 — audit code ↔ TODO

> Vérifié dans le code (routes, policies, services, Dexie, tests `php artisan test` : 29 tests OK, 0 échec, 29 dépréciations).

### Faux positifs corrigés dans les sections ci-dessus
- `Vente hors ligne complète` : passé de `[x]` à `[ ]`.
- `Chaque transaction locale → file` : `[x]` → `[-]` (`enqueueEvent` jamais appelé).
- `PWA` : `[x]` → `[-]` (icônes manquantes).
- `Vérification pharmacy_id à chaque requête` : `[x]` → `[-]` (`guard()` jamais appelé).
- `Tests policies + smoke` : `[x]` → `[-]` (aucun test de policy).
- `CI` : `[x]` → `[-]` (pas de Vitest ni Playwright).
- `Indicateur sync Topbar` : `[ ]` → `[-]` (UI présente, compteur toujours à 0).
- `Ticket` : génération `[ ]` → `[-]` (ticket existant), réimpression `[x]`.

### Déjà corrigé dans le code (à valider par des tests)
- Clôture de caisse : seulement espèces + mouvements (`CashController::expectedAmount`). Pas de test.
- Signe des ajustements : perte/casse forcées négatives (`InventoryController::adjust`). Pas de test.
- Prix serveur et `pharmacy_id` de vente dérivé de la pharmacie active (`SaleController::store`). Pas de test de falsification.
- Annulation : motif, `cancelled_by`, dossier assurance annulé (`SaleService::cancel`). Pas d'approbateur.

### Chantiers à REFAIRE (marqués faits, mal faits)
1. **Vente hors ligne** (Phase 4.4) : brancher `Vente.vue` sur Dexie (produits/stock en lecture, écriture `outbox` via `enqueueEvent`), numéro local, ticket local.
2. **Synchronisation** : sans alimentation de l'outbox, la file, le compteur Topbar et la boucle `pushBatch` ne servent à rien.
3. **Rate limiting API sync** : ~~absent~~ → traité au LOT 1 (`throttle:sync`, clé par device).
4. **Isolation par pharmacie** : ~~aucun test cross-pharmacie~~ → traité au LOT 1 pour ventes, caisses, achats, assurances, transferts, conflits et utilisateurs.
5. **Super-admin codé en dur** : ~~adresse e-mail codée en dur~~ → traité au LOT 1, `is_super_admin` est l'unique source de vérité.
6. **Tests** : dépréciations PDO corrigées et tests de sécurité ajoutés au LOT 0/1 ; restent prix falsifié, remise > plafond, caisse, inventaire, annulation et sync.
7. **PWA** : ~~icônes absentes~~ → traité au LOT 0, icônes réelles et `npm run pwa:check`.
8. **Annulation** : ajouter l'approbateur et un contrôle de double annulation côté UI.

### Chantiers RESTANTS (non commencés)
- Phase 0.2 : masquage UI des actions non autorisées, tests 403.
- Phase 1.1 : historique des prix d'achat ; facture fournisseur (optionnel).
- Phase 1.4 : retours clients (table, motif, approbateur, restock séparé, audit).
- Phase 1.5 : unités et conditionnements (`base_unit`, `sale_unit`, `conversion_factor` existent en base, mais aucune logique de vente).
- Phase 1.6 : lots (`lot_number`), à confirmer avec le client.
- Phase 2.1 : scanner code-barres (auto-focus + Enter), recherche DCI, modification de conditionnement.
- Phase 2.5 : table `payment_methods` + CRUD (aucune migration).
- Phase 2.8 : tests de vente (remise, concurrence, annulation avec lien d'origine).
- Phase 3.3 : rapports par assureur/période, exports des dossiers.
- Phase 4 : rapports et exports (PDF/Excel), tableau de bord réseau, tableau de bord pharmacie.
- Phase 5 : caisse et inventaire hors ligne, auth hors ligne, tests coupure/reprise, retries.
- Design system : `docs/DESIGN_SYSTEM.md` absent ; primitives (`PageHeader`, `DataTable`, `StatusBadge`, `MoneyInput`, `Toast`, `OfflineBanner`) absentes de `components/ui` (seulement les composants shadcn).
- Phase 6 : HTTPS, 2FA, sauvegardes, procédures de déploiement, documentation utilisateur, PV de recette, upgrade Laravel 13.
- Phase 7 : Vitest, Playwright, couverture, concurrence MySQL.

### Hygiène
- Le PDF du cahier des charges a été supprimé par le commit `39bae6f`. Le restaurer : `git checkout 921e619 -- docs/cahier_des_charges_gestion_pharmacies_v1.pdf`.
- `package.json.bak` est à la racine : à supprimer si obsolète.
- `docs/PLAN_IMPLEMENTATION.md` contient les mêmes `[x]` erronés (sections 1.5, 4.2 « rate limiting API sync », 4.4). À aligner sur ce fichier.

## Mise à jour du 02/11/2026 — LOT 0 terminé (baseline reproductible)

> Toutes les preuves ci-dessous ont été exécutées localement sur MySQL 8.4 et PHP 8.5.4.

### Commandes vertes

- `composer test` : **43 tests, 192 assertions**, MySQL `pharmagest_test`, aucune dépréciation.
- `vendor/bin/pint --test` : OK.
- `vendor/bin/phpstan analyse --no-progress --memory-limit=1G` : 0 erreur.
- `npm run lint` : OK.
- `npm run format:check` : OK.
- `npm run typecheck` : OK.
- `npm run build` : OK.
- `npm run pwa:check` : OK, manifeste `fr`, icônes 192/512, service worker.
- Migrations `migrate:fresh` → `migrate:rollback` → `migrate` : OK sur SQLite et MySQL.

### Corrections

- [x] `config/database.php` : constante PDO compatible PHP 8.2+ sans dépréciation.
- [x] `2026_10_30_000001_add_sync_and_security_columns.php` : rollback SQLite corrigé (index retirés avant colonnes).
- [x] `phpunit.xml` : suite principale sur MySQL `pharmagest_test` ; SQLite conservé pour le contrôle des migrations.
- [x] ESLint, Prettier et plugins installés ; scripts `lint`, `lint:fix`, `format`, `format:check`, `typecheck`, `pwa:check`.
- [x] `tsconfig.json` et types Ziggy corrigés ; aucune erreur `vue-tsc`.
- [x] Formatage Prettier mécanique de `resources/`.
- [x] PWA : icônes 192/512, favicon réel, manifeste `fr`, contrôle automatique.
- [x] Fixtures : 8 factories, `Tests\Support\PharmacyScenario`, test multi-sites indépendant de `DemoSeeder`.
- [x] CI consolidée dans `.github/workflows/ci.yml` (MySQL, SQLite, Pint, PHPStan, PHPUnit, lint, format, typecheck, build, PWA) ; YAML validé localement, exécution GitHub non vérifiée dans cet environnement.

### Limites connues du LOT 0

- Le build conserve un warning de chunk `AppLayout` à ~924 Ko ; non bloquant, à traiter lors du découpage UI.
- La base `pharmagest_test` doit exister localement et être documentée dans le README.

## Mise à jour du 02/11/2026 — LOT 1 terminé (sécurité et isolation)

### Mesures appliquées

- [x] `is_super_admin` est l'unique source de vérité ; suppression de l'e-mail codé en dur.
- [x] Super-admin sans affectation : pharmacies accessibles, pharmacie active et partage Inertia cohérents.
- [x] Périmètre de gestion des utilisateurs : listing filtré, cibles hors périmètre masquées, auto-édition interdite.
- [x] Attribution limitée aux pharmacies administrées et aux rôles dont les permissions sont incluses dans celles de l'acteur.
- [x] Policies `UserPolicy` durcies et nouvelle `SyncConflictPolicy` (lecture/résolution par pharmacie).
- [x] `AuditLogger` accepte un acteur et une pharmacie explicites ; vente et synchronisation les fournissent.
- [x] Rate limiting de la synchronisation par appareil/IP (`throttle:sync`).
- [x] Navigation : Clients et Conflits sync masqués sans permission ; actions Utilisateurs masquées selon `can_update`/`can_delete`.

### Tests de sécurité ajoutés

- `tests/Feature/Security/CrossPharmacyIsolationTest.php` : ventes, caisses, achats, assurances, transferts.
- `tests/Feature/Security/UserManagementSecurityTest.php` : listing, attribution, élévation de rôle, super-admin, audit.
- `tests/Feature/Security/SyncConflictAuthorizationTest.php` : conflits par pharmacie et super-admin.
- `tests/Feature/Security/PermissionMatrixTest.php` : vendeur, gérant, responsable régional.
- `tests/Feature/Security/SyncRateLimitTest.php` : middleware de limitation.

### Limites restantes avant le LOT 2

- Les handlers `SyncController::process` ne vérifient pas encore la permission métier de chaque événement ; prévu au LOT 3.
- Les référentiels produits/clients/assureurs restent globaux : décision métier à confirmer.
- La résolution d'un conflit ne rejoue pas encore l'opération métier ; prévu au LOT 3.
- Les tests de concurrence MySQL et les scénarios de vente avancés restent au LOT 2.

## Mise à jour du 02/11/2026 — LOT 2 terminé (règles métier)

> Validation finale : `67 tests, 374 assertions`, MySQL, sans dépréciation. Pint et PHPStan verts.

### Corrections métier vérifiées

- [x] Vente : prix serveur, paiements mixtes, idempotence, stock concurrent et audit testés.
- [x] Remise : plafond par rôle, motif et approbation ; remise totale à 0 GNF bloquée ; plafond absolu de 50 % appliqué aussi aux remises en montant.
- [x] Assurance : la remise globale est répartie proportionnellement entre part client et part assureur.
- [x] Annulation : restock une seule fois, motif, claim annulé, deuxième annulation refusée.
- [x] Stock : pertes/casses forcées négatives, correction signée, stock négatif refusé, comptage inventaire idempotent.
- [x] Stock : double incrément de `version` supprimé dans `StockService`.
- [x] Caisse : verrou sur la pharmacie pour sérialiser deux ouvertures ; théorique espèces + mouvements et écart testés ; mouvement après clôture refusé.
- [x] Achats : réception partielle/totale bornée, idempotente, ligne étrangère refusée ; prix d'achat mis à jour via observer de version.
- [x] Transferts : expédition/réception partielles, bornes, stock source/destination et annulation partielle testés.
- [x] Assurances : transitions verrouillées en transaction, remboursement borné, rejet et audit testés.
- [x] Audit : acteur et pharmacie vérifiés sur vente et ouverture de caisse.
- [x] Concurrence MySQL : deux processus tentent de vendre le dernier article, une seule vente aboutit.

### Fichiers de tests ajoutés

- `tests/Feature/Business/SaleBusinessTest.php`
- `tests/Feature/Business/StockBusinessTest.php`
- `tests/Feature/Business/CashBusinessTest.php`
- `tests/Feature/Business/PurchaseBusinessTest.php`
- `tests/Feature/Business/TransferBusinessTest.php`
- `tests/Feature/Business/InsuranceBusinessTest.php`
- `tests/Feature/Business/AuditTrailBusinessTest.php`
- `tests/Feature/Business/ConcurrentSaleTest.php`
- `tests/Support/concurrent_sale.php`

### Limites restantes

- Un transfert partiellement expédié puis réceptionné peut être clôturé alors que des quantités demandées ne sont pas expédiées : règle métier à confirmer avant modification.
- L'annulation d'une vente ne crée pas encore d'avoir ni de remboursement de caisse distinct (V1.1).
- Les invariants de stock sont garantis par l'application, pas encore par des contraintes `CHECK` en base.
- Les handlers de synchronisation doivent encore appliquer les permissions par événement (LOT 3).
- Les moyens de paiement configurables, retours clients, unités et lots restent hors périmètre LOT 2.

## Mise à jour du 02/11/2026 — LOT 3 terminé (contrat de synchronisation)

> Validation finale : `83 tests, 460 assertions`, MySQL, sans dépréciation. Pint, PHPStan, ESLint, Prettier, typecheck, build et PWA verts.

### Contrat serveur

- [x] Permissions métier par type d'événement : vente, annulation, caisse, stock, inventaire.
- [x] Validation du payload et de `schema_version` avant exécution.
- [x] Types inconnus rejetés et enregistrés, plus aucun `default => null`.
- [x] Résultats explicites : `applied`, `duplicate`, `rejected`, `conflict`, `failed`.
- [x] Hash du payload pour empêcher la réutilisation d'un même `client_event_id` avec un contenu différent.
- [x] Erreurs temporaires PDO conservées en `failed` et rejouables.
- [x] Annulation de vente introuvable, double ouverture de caisse et mouvement sans session transformés en conflits visibles.

### Curseur et données de référence

- [x] Journal `sync_changes` avec curseur global monotone.
- [x] Observers sur produits, clients, assureurs, règles d'assurance et stocks.
- [x] Suppressions/deactivations propagées par tombstones et payload `is_active`.
- [x] Pagination `next_cursor` / `has_more` sur les changements.
- [x] Snapshot initial filtré par pharmacies accessibles.
- [x] Client Dexie bascule sur `sync_cursor`, sans réutiliser l'ancien curseur `sync_version`.
- [x] Jeton d'appareil du pull transmis par en-tête `X-Device-Token`, plus dans l'URL.

### Conflits

- [x] Écran de conflits avec payload local et actions Appliquer / Ajuster / Annuler.
- [x] `applied` rejoue réellement l'événement via les services métier.
- [x] `adjusted` remplace le payload puis rejoue l'événement.
- [x] `cancelled` rejette l'événement sans effet métier.
- [x] Échec de rejeu : conflit maintenu ouvert et erreur affichée.

### Tests ajoutés

- `tests/Feature/Sync/SyncPullContractTest.php`
- `tests/Feature/Sync/SyncPushContractTest.php`
- `tests/Feature/Sync/SyncConflictResolutionTest.php`

### Limites restantes

- Le snapshot initial n'est pas paginé ; seuls les changements le sont.
- La résolution d'un conflit n'est pas encore renvoyée au poste concerné par le pull ; à traiter avec le cycle de vie de l'outbox au LOT 4.
- Les événements `purchase.*`, `transfer.*` et `insurance.status` ne sont pas encore acceptés par la synchronisation offline ; ils sont explicitement rejetés.
- Le jeton du push reste dans le corps POST ; seul le pull utilise l'en-tête.
- Le vrai test de coupure/reprise réseau reste au LOT 5.

## Mise à jour du 02/11/2026 — cohérence des actions frontend

> Validation : `84 tests, 484 assertions`, Pint, PHPStan, ESLint, Prettier, typecheck, build et PWA verts.

### Corrections

- [x] Topbar : « Nouvelle vente » uniquement sur le tableau de bord et avec `make_sales`.
- [x] Topbar : suppression du bouton Notifications inerte.
- [x] Assurances : action locale « Gérer les assureurs » vers `/insurers`.
- [x] Assurances : export du bordereau masqué sans `export_data`.
- [x] Rapports : export CSV masqué sans `export_data`.
- [x] Dashboard : liens Stocks/Achats masqués selon `manage_stock`, `manage_products` et `manage_purchases`.
- [x] Stocks : raccourcis Achats/Transferts masqués sans permission.
- [x] Stocks : actions Modifier / Désactiver réellement disponibles par produit.
- [x] Vente : mode consultation seule sans `make_sales`, avec ventes récentes et accès aux rapports.
- [x] Ticket : retour vers Rapports et bouton Nouvelle vente masqué sans `make_sales`.
- [x] Transferts : boutons Expédier/Réceptionner/Annuler pilotés par les policies serveur.
- [x] Sidebar : sections vides supprimées après filtrage par permissions.
- [x] Pharmacies : libellé « Désactiver » au lieu de « Supprimer », état désactivé affiché.

### Preuve serveur

- `tests/Feature/Security/TransferActionFlagsTest.php` vérifie les flags d’action par pharmacie et par rôle.

### Limite

- Les masquages Vue sont validés par typecheck/build et revue, mais pas encore par des tests de composants ou Playwright. Ces tests arrivent avec le LOT 4/7.

## Mise à jour du 02/11/2026 — LOT 4 terminé (cycle de vie offline)

> Validation : `84 tests backend, 486 assertions` + `4 tests Vitest`, Pint, PHPStan, ESLint, Prettier, typecheck, build et PWA verts.

### Cycle de vie Dexie

- [x] Une base IndexedDB par utilisateur (`pharmagest-user-{id}`), isolation des données et de l'outbox.
- [x] Schéma Dexie v2 avec migration testée : l'outbox survit à la mise à jour.
- [x] Ouverture/fermeture de la base pilotée par l'utilisateur connecté.
- [x] Token d'appareil stocké par utilisateur, avec révocation locale sur réponse 401.
- [x] Ancienne base `pharmagest` conservée mais plus utilisée automatiquement.

### Boucle de synchronisation

- [x] Redémarrage propre au changement de pharmacie.
- [x] Arrêt complet à la déconnexion et au démontage du shell.
- [x] Listener `online` unique, supprimé à l'arrêt.
- [x] Verrou multi-onglets via Web Locks quand disponible.
- [x] Backoff exponentiel après erreur, plafonné à 5 minutes.
- [x] État partagé et réactif `pending`, `syncing`, `lastSync`, `error`, partagé Topbar/Sidebar.
- [x] Acquittement des conflits : le pull renvoie le statut serveur des événements locaux en conflit ou échoués, puis l'outbox est mise à jour.
- [x] Réponse 401 : token local effacé pour permettre un nouvel enregistrement d'appareil.

### PWA

- [x] Suppression du cache runtime des réponses authentifiées `/api/sync/pull`.
- [x] `npm run pwa:check` vérifie aussi que le service worker ne contient pas ce cache.
- [x] Manifeste, icônes et service worker toujours validés.

### Tests frontend

- [x] Vitest + jsdom + fake-indexeddb installés.
- [x] `npm test` ajouté à la CI et au README.
- [x] Test d'isolation des données et outbox entre utilisateurs.
- [x] Test de migration Dexie conservant l'outbox.
- [x] Test des tokens par utilisateur et de leur révocation.
- [x] Test du listener online unique et de l'arrêt complet.

### Limites restantes

- La base historique unique `pharmagest` n'est pas copiée automatiquement vers la base par utilisateur. Aucun outbox réel n'existe encore puisque la vente hors ligne arrive au LOT 5.
- Le shell réellement chargé hors ligne et la mise à jour du service worker ne sont pas encore couverts par Playwright.
- Les tests multi-onglets vérifient le verrou au niveau code, pas encore dans un vrai navigateur.
- La vente hors ligne n'est toujours pas branchée sur Dexie ; c'est le LOT 5.

## Mise à jour du 02/11/2026 — LOT 5 (vente hors ligne)

> Validation : `84 tests backend, 490 assertions` + `8 tests Vitest`, Pint, PHPStan, ESLint, Prettier, typecheck, build et PWA verts.

### Modèle et checkout local

- [x] Dexie v3 : `local_sales`, `local_sale_items`, `local_payments`.
- [x] Checkout atomique : vente, lignes, paiements, stock local et outbox dans une transaction.
- [x] Identifiant `client_request_id` stable et réutilisé après erreur.
- [x] Séquence d'outbox persistée pour préserver l'ordre des opérations.
- [x] Catalogue local filtré par pharmacie et produits actifs.
- [x] Recherche locale par nom, DCI et code-barres.
- [x] Calculs locaux de remise et d'assurance alignés sur les règles serveur.
- [x] Remise nécessitant approbation refusée hors ligne.
- [x] Stock local insuffisant bloqué sans écriture partielle.
- [x] Paramètres de remise descendus par le pull et mis en cache.

### Interface et ticket

- [x] Bascule automatique ligne/hors ligne dans `Vente.vue`.
- [x] Bandeau hors ligne et compteur d'opérations.
- [x] Liste des ventes locales non synchronisées ou à suivre.
- [x] Ticket local imprimable avec statut « en attente », « synchronisé » ou « à régulariser ».
- [x] Le ticket local est conservé après conflit.

### Réconciliation

- [x] ACK `sale.created` enrichi avec `sale_id` et `sale_number`.
- [x] Vente locale passée à `synced` uniquement sur `applied` ou `duplicate`.
- [x] Vente locale passée à `conflict` ou `rejected` avec erreur exploitable.
- [x] Statut serveur des événements locaux renvoyé par le pull.

### Limites restantes

- Pas encore de test Playwright réel de coupure/rétablissement réseau et de double appareil.
- Le shell PWA chargé après rechargement total hors ligne n'est pas validé en navigateur.
- L'authentification hors ligne complète et la session expirée ne sont pas traitées.
- La caisse et l'inventaire hors ligne restent au LOT 6.

## Conventions de mise à jour
- Cocher `[x]` uniquement après validation (tests + build frontend).
- Ajouter une note courte sous la tâche si une décision a été prise (ex. librairie choisie).
- Ne pas supprimer les tâches terminées : garder l'historique.
- Une nouvelle IA doit commencer par lire ce fichier, puis le PDF du cahier des charges.
