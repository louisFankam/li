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
- [x] Vérification `pharmacy_id` à chaque requête (à renforcer)

### 0.2 Permissions granulaires
- [x] Choisir librairie (Spatie Permission ou tables custom `permissions`/`role_permissions`)
- [x] Définir les permissions : consultation, création, modification, validation, annulation, export, multi-sites, paramètres
- [x] Mapper les rôles CDC (admin client, responsable régional, gérant, pharmacien/délivrance, vendeur/caissier, gestionnaire stock, comptable/assurance) vers permissions
- [ ] Appliquer les permissions dans routes/contrôleurs/UI (masquage des actions non autorisées)
- [ ] Tests : accès cross-pharmacie refusé, permission manquante → 403

### 0.3 Utilisateurs multi-sites
- [x] `UserController::store/update` acceptent plusieurs pharmacies avec rôle par site
- [x] Frontend `Users.vue` envoie le format `pharmacies: [{id, role}]`
- [-] UI multi-sélection avancée (actuellement création mono-site, extensible)

### 0.4 Audit log
- [x] Service `AuditLogger` créé
- [x] Appel sur création/mise à jour/désactivation produit et création vente
- [x] Étendre à remises, annulations, corrections stock, clôture caisse, assurance, droits, sync
- [ ] Observer/model events pour tracer automatiquement les modifications de prix
- [x] Page Audit (lecture)

### 0.5 Validation & qualité
- [x] Form Requests pour chaque contrôleur
- [x] Messages d'erreur en français
- [x] Tests policies + smoke étendu

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
- [ ] Prix minimum : empêcher remise conduisant à prix nul/négatif; contrôle renforcé sous coût d'achat si fiable
- [x] Motif obligatoire + trace (montants origine/remise, utilisateur, approbateur)

### 2.3 Annulation / avoir
- [x] Vente finalisée non supprimée → annulation avec restock
- [ ] Lien vers vente d'origine, motif, utilisateur, approbateur (à compléter)

### 2.4 Tickets
- [ ] Génération ticket imprimable (identité pharmacie, n°, date, lignes, remises, totaux, règlements, mentions légales)
- [ ] Réimpression
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
- [ ] Vente avec stock suffisant diminue stock + enregistre paiements une seule fois
- [ ] Vente > stock bloquée même avec concurrence (deux caissiers)
- [ ] Remise > plafond nécessite validation + laisse trace
- [ ] Annulation crée avoir + lien origine

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
- [ ] Vente avec assurance calcule parts client/assureur et crée créance suivie jusqu'au règlement/rejet

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
- [x] Chaque transaction locale → événement persistant dans la file

### 5.3 Service de synchronisation
- [x] Envoi automatique à la reconnexion
- [ ] Retries après échec sans perte
- [x] Accusé de réception; statut central "synchronisé" uniquement après ACK
- [x] Idempotence : répétition d'envoi ne crée pas de doublon

### 5.4 Conflits
- [x] Journalisation des conflits + présentation à un responsable
- [x] Aucune opération écartée silencieusement

### 5.5 Données de référence versionnées
- [x] Produits, prix, règles de remise, comptes versionnés et diffusés aux sites

### 5.6 UI de sync
- [ ] Indicateur permanent en haut (Topbar) : état connexion/sync, nb opérations en attente, dernière sync réussie
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
- [x] Tests cross-pharmacie (403) et prix falsifié.

### Design system (absent du TODO)
- [ ] Figer les tokens (`app.css`, `tailwind.config.js`) et documenter.
- [ ] Primitives UI (Button, DataTable, StatusBadge, MoneyInput, Toast, OfflineBanner…).
- [ ] Supprimer toutes les valeurs factices (sidebar, dashboard, topbar, vente, stocks).
- [x] Navigation complète (Catégories, Clients, Assureurs, Audit, Administration).
- [ ] Responsive + accessibilité + impression ticket.

### Offline (préciser la Phase 5)
- [x] PWA : manifest + service worker (Workbox) + fallback hors ligne.
- [x] IndexedDB (Dexie) : snapshot produits/stock/clients/assureurs/caisse + `outbox`.
- [x] `sync_events` serveur (idempotence `client_event_id`) + `POST /api/sync/push` + `GET /api/sync/pull`.
- [x] Vente hors ligne complète (recherche, panier, remise, assurance, ticket).
- [ ] Caisse et inventaire hors ligne.
- [x] Écran de conflits + indicateur de sync réel.
- [ ] Tests coupure/reprise sans perte ni doublon.

### Qualité
- [x] CI GitHub Actions (Pint, PHPStan, PHPUnit, ESLint, Vitest, build, Playwright).
- [ ] Tests de concurrence sur MySQL.

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


## Conventions de mise à jour
- Cocher `[x]` uniquement après validation (tests + build frontend).
- Ajouter une note courte sous la tâche si une décision a été prise (ex. librairie choisie).
- Ne pas supprimer les tâches terminées : garder l'historique.
- Une nouvelle IA doit commencer par lire ce fichier, puis le PDF du cahier des charges.
