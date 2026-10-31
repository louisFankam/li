# PharmaGest — Plan d'implémentation complet

> Document de référence pour mener l'application jusqu'à une version exploitable en pharmacie, puis jusqu'au périmètre complet du cahier des charges.
> Dernière mise à jour : 30/10/2026.
> Documents liés : `docs/TODO.md` (suivi des tâches), cahier des charges PDF (à restaurer, voir §1.4).

---

## 0. Résumé exécutif

### Objectif

Livrer **V1.0 — Pilote exploitable** :

- tous les modules déjà présents fonctionnent réellement (pas de valeurs factices) ;
- design cohérent sur toutes les pages ;
- backend robuste, validé et sécurisé (isolation par pharmacie, prix serveur, permissions, audit) ;
- **fonctionnement hors connexion** pour le cœur du métier : vente, stock (lecture), caisse, inventaire ;
- synchronisation fiable, sans perte ni doublon, au retour du réseau.

Puis **V1.1** (compléments métier) et **V2** (réseau multi-sites / industrialisation).

### Principe directeur

> **On ne construit pas l'offline sur un socle faux.**
> L'ordre est : hygiène → sécurité/isolation → correction métier → design → offline → qualité → déploiement.

### Décision d'architecture majeure (offline)

Deux options ont été évaluées :

| Option | Description | Avantages | Inconvénients |
|---|---|---|---|
| **A. Nœud local + central** (CDC) | 1 serveur Laravel/MySQL par pharmacie sur le LAN + serveur central | Très robuste, pas de dépendance Internet pour vendre | Coût matériel, infra centrale, sync serveur-à-serveur lourde |
| **B. PWA offline-first** (recommandée pour V1) | Le navigateur cache l'app + les données (IndexedDB) + file d'événements | Pas de matériel supplémentaire, fonctionne même si le serveur tombe, réutilisable pour V2 | Complexité front, auth hors ligne, conflits à gérer |

**Recommandation : B pour V1.0, puis convergence vers A en V2.**
Le modèle d'événements (`outbox` locale + `sync_events` serveur) conçu pour la PWA est **exactement** celui qui servira au nœud local → central en V2. On ne jette donc rien.

### Estimation globale

Hypothèse : **1 développeur fullstack + 0,5 QA**, rythme 5 j/semaine.

| Lot | Durée | Cumul |
|---|---|---|
| Phase 0 — Hygiène & baseline | 0,5 sem | S1 |
| Phase 1 — Sécurité & isolation | 1,5 sem | S2–S3 |
| Phase 2 — Correction métier | 2 sem | S3–S5 |
| Phase 3 — Design system & UI | 2 sem | S5–S7 |
| Phase 4 — Offline & sync (V1.0) | 3,5 sem | S7–S11 |
| Phase 5 — Tests & CI | 1 sem | S11–S12 |
| Phase 6 — Déploiement & docs (V1.0) | 1 sem | S12–S13 |
| **V1.0 livrable** | **~13 semaines** | **S13** |
| V1.1 — Compléments métier | 3–4 sem | S17 |
| V2 — Réseau / industrialisation | 6–8 sem | S25 |

> Les estimations sont indicatives et supposent les décisions client du §9 tranchées. Elles doublent si le périmètre offline inclut l'authentification hors ligne complète dès V1.0.

---

## 1. État des lieux vérifié (30/10/2026)

### 1.1 Ce qui fonctionne réellement

| Élément | Statut | Preuve |
|---|---|---|
| Stack Laravel 12 + Vue 3 + Inertia + TS + Tailwind | ✅ | `composer.json`, `package.json` |
| Auth (login, reset, vérification email), register désactivé | ✅ | `routes/auth.php`, tests Auth |
| Schéma cœur (pharmacies, produits, stock, ventes, caisse, assurances, transferts, achats, audit) | ✅ | `database/migrations/2026_10_28_164240_create_core_schema.php` |
| Permissions par rôle + gates | ✅ | `app/Support/Permissions.php`, `AppServiceProvider` |
| Modules Pharmacies, Catégories, Fournisseurs, Produits, Utilisateurs, Clients, Assureurs | ✅ CRUD | contrôleurs + pages |
| Ventes (items, paiements, remise, assurance, décrément stock verrouillé) | ✅ basique | `SaleController` |
| Transferts (création, expédition, réception, annulation) | ✅ basique | `TransferController` |
| Achats (commande, réception totale/partielle) | ✅ basique | `PurchaseController` |
| Inventaires (comptage + ajustements) | ✅ basique | `InventoryController` |
| Caisses (ouverture, mouvements, clôture) | ✅ basique | `CashController` |
| Assurances (synthèse, dossiers, statuts) | ✅ basique | `InsuranceController` |
| Audit (lecture) | ✅ partiel | `AuditController`, `AuditLogger` |
| Rapports ventes + export CSV | ✅ basique | `ReportController` |
| Tests | ✅ 29 tests / 82 assertions passent | `php artisan test` |
| Build frontend | ✅ OK (~49 s) | `npm run build` |

### 1.5 État au 31/10/2026 — finalisation livrable V1

- Sidebar : permissions réelles, compteur Dexie, déconnexion, drawer mobile, `no-print`, `aria-label` — ✅.
- Topbar : réseau réel, pending/last-sync Dexie, menu mobile, bouton vente masqué sans permission — ✅.
- Vente : aperçu serveur (`POST /vente/preview`), panier borné au stock, remise + motif, paiements multiples, `client_request_id`, désactivation tant que le reste à encaisser ≠ 0 — ✅.
- Ticket : formatage GNF, impression, auto-print `?print=1` — ✅.
- Annulation : `SaleService::cancel` (restock via `StockService`, audit, annul dossier assurance) — ✅.
- Achats/Transferts/Caisses/Assurances/Rapports/Audit : handlers partiels et exports consolidés — ✅.
- Hors ligne/sync : pull versionné, observers version, handlers push (cash/stock/inventaire/sale.cancelled), device token, boucle sync — ✅ pull corrigé.
- Seed demo : idempotent et cohérent — ✅.
- Valeurs fictives éliminées (dashboard, stocks, login, topbar, vente) — ✅.
- Tests : 29 tests / 85 assertions OK · Build OK · aucun console error Playwright après migration sync — ✅.

### 1.2 Écarts entre le TODO et le code

| TODO | Réalité |
|---|---|
| `2.7 Caisse [x]` | Cycle présent, mais **calcul de clôture faux** (inclut tous les moyens de paiement, ignore les mouvements de caisse) |
| `0.1 Autorisation [x]` | Gates présents, mais **aucune isolation par pharmacie** sur les modèles liés (IDOR) |
| `1.2 Inventaires [x]` | Mouvement créé, mais **signe libre** : une perte positive augmente le stock |
| `2.3 Annulation [x]` | Restock OK, mais **pas de motif/approbateur** ni annulation du dossier assurance |
| `2.7 « valeurs statiques caisse » [ ]` | En fait **déjà branché** sur les props réelles (`Caisses.vue`) |
| `4.1 Filtres période [x]` | Fait |

### 1.3 Dette immédiate

- **Tout le travail récent est non commité** (4 contrôleurs + 4 pages + services + support non trackés, 17 fichiers modifiés). Risque de perte.
- **PDF du cahier des charges supprimé** du working tree (récupérable via git).
- Fichier parasite `_tmp_186159_...` (0 octet).
- Aucun Form Request, aucune Policy.
- Aucun service worker / manifest / IndexedDB / file de synchronisation : **l'offline n'existe pas du tout**.
- Nombreuses valeurs codées en dur (sidebar, dashboard, topbar, vente).
- Navigation incomplète (pas de liens Catégories, Clients, Assureurs, Audit, Administration ; clé `achats` dupliquée).

### 1.4 Prérequis avant de commencer

```bash
# 1. Sécuriser le travail en cours
git add -A && git commit -m "wip: modules achats/inventaires/rapports/audit + permissions"

# 2. Restaurer le cahier des charges
git checkout -- docs/cahier_des_charges_gestion_pharmacies_v1.pdf

# 3. Supprimer le fichier parasite
rm -f _tmp_186159_047ff88e76b51cd0edd255cb8674e744
```

---

## 2. Décisions d'architecture (ADR)

| ID | Décision | Justification |
|---|---|---|
| ADR-01 | **PWA offline-first** pour V1.0 (service worker + IndexedDB + outbox), convergence vers nœud local en V2 | Coût maîtrisé, vendre même si serveur/Internet indisponible, modèle d'événements réutilisable |
| ADR-02 | **Le serveur est autoritaire** sur les prix, remises, stocks, permissions | Empêche la falsification côté client |
| ADR-03 | **Isolation par pharmacie systématique** : scope global + scoping des route-model-bindings + vérification d'accès | Corrige les IDOR actuels |
| ADR-04 | **Design system unique** basé sur les tokens existants + primitives Vue réutilisables | Cohérence visuelle, vélocité, accessibilité |
| ADR-05 | **Montants en entiers GNF** (`bigInteger`), jamais de float | Exactitude comptable |
| ADR-06 | **Audit par observers** sur tous les modèles critiques + événements métier explicites | Traçabilité exhaustive |
| ADR-07 | **Idempotence par `client_event_id` (UUID)** sur toute mutation | Offline sans doublon |
| ADR-08 | **Tests : PHPUnit (feature) + Vitest (composants) + Playwright (E2E)** | Couvre backend, UI et offline |
| ADR-09 | **CI GitHub Actions** : lint + Pint + tests + build + E2E | Qualité continue |
| ADR-10 | **Migrations additives**, une par lot fonctionnel | Déploiement sans downtime |

---

## 3. Design system & cohérence UI

### 3.1 Tokens (déjà présents, à figer)

- Fichiers : `resources/css/app.css`, `tailwind.config.js`.
- Couleurs : `primary #0e9f6e`, `dark #0c2b21`, `background #f3f7f4`, `border #e2ece5`, `danger`, `warning`, `success`, `info`, `gold`.
- Typo : `Inter` (corps), `DM Sans` (titres).
- Rayons : `sm 6px`, `md 10px`, `lg 14px`, `xl 20px`.
- **Règle** : aucun hex inline dans les pages, uniquement les classes/tokens.

### 3.2 Primitives à créer / standardiser

Dossier cible : `resources/js/components/ui/` + `resources/js/components/domain/`.

| Composant | Rôle |
|---|---|
| `PageHeader` | Titre, sous-titre, actions, fil d'Ariane |
| `Button` | Variantes primary / secondary / ghost / danger / outline, tailles, état loading |
| `Card`, `StatCard` | Cartes et indicateurs |
| `DataTable` | Tri, pagination, état vide, chargement, lignes cliquables |
| `StatusBadge` | Statuts métier normalisés (payé, annulé, reçu, rejeté…) |
| `FormField`, `Input`, `Select`, `MoneyInput`, `SearchInput` | Formulaires + validation inline |
| `Modal`, `ConfirmDialog` | Confirmations d'actions sensibles |
| `Toast` | Succès / erreur / avertissement |
| `Tabs`, `Pagination`, `Skeleton`, `EmptyState`, `Alert` | Structure de page |
| `OfflineBanner`, `SyncIndicator` | État réseau / synchronisation |
| `MoneyText`, `DateText` | Formatage GNF et dates FR |

### 3.3 Règles de layout

- **Un seul layout** : `AppLayout` → `AppSidebarLayout` → `Topbar` + contenu.
- Sidebar **pilotée par les données** : pharmacie active réelle, utilisateur réel, rôle réel, compteur de sync réel, liens filtrés par permission.
- Topbar : statut réseau réel (online/offline), dernière synchro réelle, sélecteur de pharmacie fonctionnel (y compris « Toutes »).
- Responsive : sidebar en drawer < 1024 px, tableaux scrollables, formulaires en colonne.
- Accessibilité : labels associés, focus visible, navigation clavier, contrastes AA, `aria-*`.
- Impression : feuille `@media print` dédiée aux tickets.

### 3.4 Suppression des valeurs factices (liste exhaustive)

| Fichier | À remplacer |
|---|---|
| `AppSidebar.vue` | `KM5`, `Pharmacie KM5`, `Kaloum`, `Aïcha Diallo`, `128 tickets`, `68%`, bouton Synchroniser inerte |
| `Topbar.vue` | « En ligne » statique, « Synchro --:-- » |
| `Dashboard.vue` | `+12,4 %`, `+8,1 %`, `-5 résolues`, `14 dossiers`, graphe 7 jours factice, boutons Réassort / Commander inertes |
| `Vente.vue` | « Hors-ligne prêt » statique, calcul assurance 80 % en dur, avatar `AD`, catégories en dur |
| `Stocks.vue` | `Stock KM5`, `KM5 → Ratoma` |
| `Users.vue` | Sélection mono-site uniquement (backend multi-site déjà prêt) |

---

## 4. Sécurité & robustesse backend

### 4.1 Matrice de permissions (cible)

| Permission | Admin | Resp. régional | Gérant | Pharmacien | Vendeur | Gest. stock | Comptable |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| `view_dashboard` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `make_sales` | ✅ | | ✅ | ✅ | ✅ | | |
| `apply_discount` | ✅ | | ✅ | ✅ | ✅ | | |
| `approve_discount` | ✅ | ✅ | ✅ | | | | |
| `cancel_sale` | ✅ | ✅ | ✅ | | | | |
| `manage_stock` | ✅ | ✅ | ✅ | ✅ | | ✅ | |
| `manage_inventory` | ✅ | | ✅ | | | ✅ | |
| `manage_purchases` | ✅ | | ✅ | | | ✅ | |
| `manage_transfers` | ✅ | ✅ | ✅ | | | ✅ | |
| `manage_cash` | ✅ | | ✅ | | ✅ | | ✅ |
| `manage_insurance` | ✅ | ✅ | ✅ | ✅ | | | ✅ |
| `view_reports` | ✅ | ✅ | ✅ | ✅ | | | ✅ |
| `export_data` | ✅ | ✅ | ✅ | | | | ✅ |
| `manage_users` | ✅ | | ✅ | | | | |
| `manage_pharmacies` | ✅ | | | | | | |
| `manage_audit` | ✅ | ✅ | | | | | |

> À valider avec le client (le CDC peut préciser des variantes).

### 4.2 Contrôles obligatoires

1. **Prix serveur** : ne jamais accepter `unit_price` du client ; recharger `products.sale_price` et vérifier les remises.
2. **Isolation pharmacie** :
   - `PharmacyContext` (service) : pharmacie active + liste des sites accessibles.
   - Global scope sur les modèles multi-sites (`Sale`, `StockLevel`, `StockMovement`, `Purchase`, `CashSession`, `InsuranceClaim`, `Transfer`).
   - Route-model-binding scopé : `->scopeBindings()` + `whereIn('pharmacy_id', accessibleIds)`.
   - Middleware `pharmacy.scope` sur les routes métier.
3. **Form Requests** pour chaque contrôleur (messages en français).
4. **Policies** par modèle + `authorizeResource`.
5. **Transactions + verrous** (`lockForUpdate`) sur stock, caisse, réceptions, transferts.
6. **Contraintes DB** : unicités (`sale_number`, `client_event_id`), index de performance, `check` sur quantités.
7. **Idempotence** : `client_event_id` sur ventes/annulations/caisse/ajustements.
8. **Rate limiting** : login (5/min/IP), API sync (par device).
9. **Session** : durée, régénération, `secure`/`http_only`/`same_site`.
10. **Audit** : observers sur `Sale`, `SaleItem`, `Product`, `StockLevel`, `Purchase`, `Transfer`, `CashSession`, `InsuranceClaim`, `User`, `Pharmacy`, `Discount`.
11. **Gestion d'erreurs métier** : exceptions dédiées → 422 avec message FR (pas de 500).
12. **En-têtes de sécurité** : CSP, HSTS, X-Frame-Options, Referrer-Policy.

### 4.3 Failles actuelles à corriger (priorité 1)

| # | Faille | Fichier | Correction |
|---|---|---|---|
| S1 | Prix falsifiable | `SaleController::store` | Recalcul serveur depuis `products.sale_price` |
| S2 | Vente cross-pharmacie | `SaleController::store` | `pharmacy_id` dérivé du contexte + `canAccessPharmacy` |
| S3 | IDOR achats/caisse/assurances/transferts | contrôleurs | Scoping route-model-binding + policy |
| S4 | Signe d'ajustement libre | `InventoryController::adjust` | Signe forcé selon `type` (perte/casse = négatif) |
| S5 | Clôture caisse fausse | `CashController::close` | Espèces uniquement + mouvements + une session ouverte |
| S6 | Annulation sans trace / claim non annulé | `SaleController::cancel` | Motif, approbateur, annulation du claim, audit |
| S7 | Réception achat non bornée | `PurchaseController::receive` | Item ∈ achat, `reçu ≤ commandé`, verrou |
| S8 | `sale_number` collision | `SaleController::store` | UUID / séquence atomique |
| S9 | Remise non tracée | `SaleController::store` | Plafond par rôle, approbation, motif, audit |
| S10 | Transferts non scopés | `TransferController` | Policies + statuts + bornes |

---

## 5. Plan par phases

### Phase 0 — Hygiène & baseline (0,5 semaine)

**Objectif** : partir d'une base propre, versionnée, testée et buildable.

- [ ] P0.1 Commit du travail en cours + restauration du PDF + suppression du fichier parasite.
- [ ] P0.2 `.env.example` complet + `README` de démarrage (PHP, MySQL, Node, commandes).
- [ ] P0.3 Nettoyage Pint / ESLint / Prettier ; script `composer lint` et `npm run lint`.
- [ ] P0.4 CI GitHub Actions minimale : `pint --test`, `php artisan test`, `npm run build`.
- [ ] P0.5 Ajouter `phpstan`/`larastan` (niveau 5) et corriger les erreurs bloquantes.
- [ ] P0.6 Ajouter `vite-plugin-pwa` (préparation) + `pinia` + `dexie` aux dépendances.

**Critères de sortie** : CI verte, build OK, tests OK, working tree propre.

**Fichiers** : `.github/workflows/ci.yml`, `composer.json`, `package.json`, `README.md`.

---

### Phase 1 — Sécurité & isolation (1,5 semaine)

**Objectif** : aucun utilisateur ne peut lire/écrire les données d'une autre pharmacie ; les prix et permissions sont serveur.

- [ ] P1.1 `app/Services/PharmacyContext.php` : pharmacie active, sites accessibles, rôle.
- [ ] P1.2 Middleware `app/Http/Middleware/EnsurePharmacyScope.php` + alias `pharmacy.scope`.
- [ ] P1.3 Scoping des route-model-bindings (`Sale`, `Purchase`, `CashSession`, `InsuranceClaim`, `Transfer`) + global scopes.
- [ ] P1.4 Form Requests : `StoreSaleRequest`, `CancelSaleRequest`, `StorePurchaseRequest`, `ReceivePurchaseRequest`, `StoreInventoryRequest`, `AdjustStockRequest`, `OpenCashRequest`, `CloseCashRequest`, `CashMovementRequest`, `StoreTransferRequest`, `ShipTransferRequest`, `ReceiveTransferRequest`, `UpdateClaimRequest`, `StoreUserRequest`, `UpdateUserRequest`.
- [ ] P1.5 Policies : `SalePolicy`, `PurchasePolicy`, `CashSessionPolicy`, `TransferPolicy`, `InsuranceClaimPolicy`, `ProductPolicy`, `UserPolicy`, `PharmacyPolicy`.
- [ ] P1.6 Prix serveur : `SaleService` recalcule sous-total, remise, total, parts assurance.
- [ ] P1.7 Remise : plafonds par rôle (`config/discounts.php`), motif obligatoire, approbation, trace audit.
- [ ] P1.8 Rate limiting login + API sync ; durcissement session (`config/session.php`).
- [ ] P1.9 En-têtes de sécurité (`app/Http/Middleware/SecurityHeaders.php`).
- [ ] P1.10 Tests : cross-pharmacie 403, prix falsifié ignoré, permission manquante 403, remise > plafond.

**Critères de sortie** :
- Un vendeur de la pharmacie A ne peut ni voir ni modifier une vente/achat/caisse/transfert/dossier de la pharmacie B (403/404).
- Un prix envoyé à 1 GNF est ignoré et remplacé par le prix catalogue.
- Tous les cas passent en tests.

**Fichiers clés** : `app/Services/PharmacyContext.php`, `app/Policies/*`, `app/Http/Requests/*`, `app/Services/SaleService.php`, `routes/web.php`.

---

### Phase 2 — Correction métier des modules existants (2 semaines)

**Objectif** : les modules actuels deviennent fiables et traçables.

#### 2.1 Vente
- [ ] P2.1.1 Recalcul serveur complet (sous-total, remise %, remise montant, total).
- [ ] P2.1.2 Contrôle `payments == client_part` (tolérance 0), paiement mixte.
- [ ] P2.1.3 Assurance : parts client/assureur, fallback 80/20, règle produit/catégorie.
- [ ] P2.1.4 `sale_number` robuste : `SALE-{site}-{YYYYMMDD}-{ULID}`.
- [ ] P2.1.5 Annulation : motif obligatoire, approbateur, restock, annulation du claim, audit, anti-double annulation.
- [ ] P2.1.6 Ticket : données complètes exposées pour impression (V1.0 inclut le ticket).
- [ ] P2.1.7 Tests : vente OK, stock insuffisant (422), concurrence, remise, annulation.

#### 2.2 Stock & inventaire
- [ ] P2.2.1 `StockService` centralisé (entrée, sortie, ajustement, transfert, réception).
- [ ] P2.2.2 Signe forcé : `perte`/`casse` négatifs, `correction` signé explicite.
- [ ] P2.2.3 Invariants : stock jamais négatif, mouvement toujours lié à une référence.
- [ ] P2.2.4 Seuils d'alerte réels (dashboard, page stocks).
- [ ] P2.2.5 Tests : perte, casse, correction, inventaire, concurrence.

#### 2.3 Caisse
- [ ] P2.3.1 Une seule session ouverte par pharmacie (contrainte + validation).
- [ ] P2.3.2 `expected = fond + paiements espèces + entrées − retraits`.
- [ ] P2.3.3 Clôture : écart, notes, audit, impossibilité de modifier après clôture.
- [ ] P2.3.4 Page caisse : totaux réels, mouvements listés, historique.
- [ ] P2.3.5 Tests : clôture correcte, double ouverture refusée, mouvement après clôture refusé.

#### 2.4 Achats
- [ ] P2.4.1 Réception bornée (`reçu ≤ commandé`, item ∈ achat), verrou.
- [ ] P2.4.2 Statuts : brouillon → envoyée → partiellement reçue → reçue / annulée.
- [ ] P2.4.3 Historique des prix d'achat par produit/fournisseur.
- [ ] P2.4.4 (Option) Facture fournisseur + statut de paiement.

#### 2.5 Transferts
- [ ] P2.5.1 Scoping + policies.
- [ ] P2.5.2 Statuts : brouillon → validé → expédié → partiellement reçu → reçu / annulé.
- [ ] P2.5.3 Annulation d'un transfert expédié = restock contrôlé.
- [ ] P2.5.4 Réception bornée (`reçu ≤ expédié`).
- [ ] P2.5.5 Tests : expédition, réception partielle, annulation.

#### 2.6 Assurances
- [ ] P2.6.1 Cycle de vie : à facturer → envoyé → remboursé / rejeté / annulé.
- [ ] P2.6.2 Règlements : montant reçu, référence, date, notes.
- [ ] P2.6.3 Annulation de vente → claim annulé.
- [ ] P2.6.4 Totaux réels par assureur/pharmacie/période.
- [ ] P2.6.5 Tests : calcul des parts, règlement, rejet.

#### 2.7 Audit
- [ ] P2.7.1 Observers sur tous les modèles critiques.
- [ ] P2.7.2 Événements explicites : remise, annulation, ajustement, clôture, assurance, droits, sync.
- [ ] P2.7.3 Page audit : filtres (action, utilisateur, pharmacie, période).
- [ ] P2.7.4 Tests : chaque action critique produit une ligne d'audit.

**Critères de sortie** : scénarios de comptoir complets acceptés, aucune erreur 500 métier, audit exhaustif.

---

### Phase 3 — Design system & UI cohérente (2 semaines)

**Objectif** : aucune page ne « détonne », aucune valeur factice, expérience homogène desktop/mobile.

- [ ] P3.1 Documenter les tokens dans `docs/DESIGN_SYSTEM.md`.
- [ ] P3.2 Créer les primitives du §3.2.
- [ ] P3.3 Layout dynamique : sidebar (pharmacie, utilisateur, rôle, permissions, sync réelle), topbar (réseau, synchro), drawer mobile.
- [ ] P3.4 Refonte page par page :
  - [ ] Dashboard (indicateurs réels, graphe réel, alertes, ventes).
  - [ ] Vente (recherche, scanner, panier, remise, assurance, paiement, ticket).
  - [ ] Stocks (recherche, filtres, pagination, seuils, marge réelle).
  - [ ] Achats, Transferts, Inventaires.
  - [ ] Assurances, Caisses, Rapports.
  - [ ] Utilisateurs (multi-site complet), Pharmacies, Catégories, Clients, Assureurs, Audit.
- [ ] P3.5 États systématiques : chargement, vide, erreur, hors ligne, succès (toasts).
- [ ] P3.6 Confirmations pour actions sensibles (annulation, clôture, suppression).
- [ ] P3.7 Accessibilité + responsive + impression ticket.
- [ ] P3.8 Tests : Vitest sur primitives + Playwright smoke sur chaque page.

**Critères de sortie** : `grep` des valeurs factices = 0, cohérence visuelle validée, mobile utilisable, accessibilité de base.

---

### Phase 4 — Offline & synchronisation (3,5 semaines) — V1.0

**Objectif** : vendre, encaisser et ajuster le stock sans réseau, puis synchroniser sans perte ni doublon.

#### 4.1 Fondations PWA
- [ ] P4.1.1 `manifest.webmanifest` (nom, icônes, thème, `display: standalone`).
- [ ] P4.1.2 Service worker (Workbox) :
  - précache de l'app shell (JS/CSS/fonts) ;
  - `NetworkFirst` pour les navigations avec fallback cache ;
  - `CacheFirst` pour les assets ;
  - page de fallback hors ligne.
- [ ] P4.1.3 Détection réseau : `navigator.onLine` + heartbeat `/api/health`.
- [ ] P4.1.4 Bandeau `OfflineBanner` + `SyncIndicator`.

#### 4.2 Couche de données locale
- [ ] P4.2.1 Schéma Dexie (`resources/js/offline/db.ts`) :
  - `products`, `stock_levels`, `customers`, `insurers`, `insurance_rules`, `cash_session`, `outbox`, `meta`.
- [ ] P4.2.2 `syncClient` : toute mutation passe par lui (online → POST immédiat ; offline → outbox).
- [ ] P4.2.3 Snapshot initial après login + rafraîchissement périodique.

#### 4.3 Contrat serveur
- [ ] P4.3.1 Migration `sync_events` :
  ```text
  id, client_event_id (unique), site_id, device_id, user_id,
  type, payload (json), schema_version, occurred_at, received_at,
  status (pending|applied|duplicate|conflict|rejected),
  error, attempts, processed_at, timestamps
  ```
- [ ] P4.3.2 Migration `devices` (token hashé, pharmacie, utilisateur, dernière activité, révocable).
- [ ] P4.3.3 `POST /api/sync/push` : batch, idempotent, résultat par événement.
- [ ] P4.3.4 `GET /api/sync/pull?since=<version>` : données de référence versionnées (`version` bigint sur tables de référence).
- [ ] P4.3.5 Handlers d'événements : `sale.created`, `sale.cancelled`, `cash.opened`, `cash.closed`, `cash.movement`, `stock.adjusted`, `inventory.counted`, `purchase.received`, `transfer.shipped`, `transfer.received`, `insurance.status`.
- [ ] P4.3.6 Journal `sync_conflicts` + page « Conflits ».
- [ ] P4.3.7 Rate limiting + authentification device.

#### 4.4 Vente hors ligne (cœur)
- [ ] P4.4.1 Recherche produit depuis IndexedDB (nom, DCI, code-barres).
- [ ] P4.4.2 Panier, quantités, remise (règles mises en cache), assurance (règles mises en cache).
- [ ] P4.4.3 Décrément stock local optimiste + contrôle de disponibilité locale.
- [ ] P4.4.4 Numéro de vente local `SALE-{site}-{date}-{ULID}`.
- [ ] P4.4.5 Ticket imprimable local.
- [ ] P4.4.6 Écriture dans `outbox` + statut « en attente de synchro ».

#### 4.5 Caisse & stock hors ligne
- [ ] P4.5.1 Ouverture/clôture/mouvements de caisse en file.
- [ ] P4.5.2 Inventaire et ajustements en file.
- [ ] P4.5.3 Lecture stock/catalogue hors ligne (snapshot).

#### 4.6 Authentification hors ligne
- [ ] P4.6.1 Session persistante (durée étendue, `remember`) pour V1.0.
- [ ] P4.6.2 (V1.1) Déverrouillage par PIN + device token signé, expiration 12 h, révocation.
- [ ] P4.6.3 Verrouillage automatique après inactivité.

#### 4.7 Résolution de conflits
- [ ] P4.7.1 Données de référence : **serveur gagne** (version).
- [ ] P4.7.2 Ventes : append-only ; si stock insuffisant au moment de l'application → événement `conflict` + vente locale « à régulariser ».
- [ ] P4.7.3 Caisse : événement tardif sur session clôturée → conflit visible.
- [ ] P4.7.4 Aucune opération supprimée silencieusement (CDC 5.4).
- [ ] P4.7.5 Écran de résolution (gérant) : appliquer, annuler, ajuster.

#### 4.8 Tests offline
- [ ] P4.8.1 Playwright : coupure réseau → 5 ventes → retour réseau → sync → aucun doublon.
- [ ] P4.8.2 Playwright : deux onglets/devices, ordre des événements.
- [ ] P4.8.3 PHPUnit : `push` idempotent (rejeu = duplicate), conflit stock, permissions device.
- [ ] P4.8.4 Test de dérive d'horloge (occurred_at dans le futur/passé).

**Critères de sortie (CDC 5.7)** :
- Coupure Internet → ventes locales → retour → sync sans doublon.
- Aucune vente perdue, aucune opération écartée silencieusement.
- L'UI indique en permanence l'état réseau, le nombre d'opérations en attente et la dernière synchro.

---

### Phase 5 — Qualité, tests & CI (1 semaine, transversal)

- [ ] P5.1 Tests feature par module (vente, stock, caisse, achats, transferts, assurances, permissions, sync).
- [ ] P5.2 Tests de concurrence (deux caissiers, même produit) sur MySQL.
- [ ] P5.3 Tests unitaires : calculs remise/assurance/conversion, idempotence.
- [ ] P5.4 Tests composants Vitest (primitives, formulaires).
- [ ] P5.5 E2E Playwright : login, vente, annulation, clôture caisse, achat, transfert, assurance, offline.
- [ ] P5.6 CI complète : Pint, PHPStan, PHPUnit, ESLint, Vitest, build, Playwright.
- [ ] P5.7 Seuil de couverture (ex. 70 % backend sur les services critiques).
- [ ] P5.8 Revue de sécurité OWASP (checklist §4.2).

**Critères de sortie** : CI verte sur `main`, couverture minimale, aucun test instable.

---

### Phase 6 — Déploiement, exploitation & docs (1 semaine) — V1.0

- [ ] P6.1 Environnements : local, recette, production.
- [ ] P6.2 Script d'installation d'un nœud pharmacie (PHP, MySQL, Nginx, Node build, migrations, seed admin).
- [ ] P6.3 HTTPS (Let's Encrypt / certificat interne), HSTS.
- [ ] P6.4 Queue worker + scheduler (Supervisor/systemd), logs rotatifs.
- [ ] P6.5 Sauvegardes : DB + fichiers, chiffrées, rétention, **test de restauration**.
- [ ] P6.6 Monitoring : uptime, erreurs applicatives, erreurs de sync, alertes.
- [ ] P6.7 Procédures : installation, mise à jour, rollback, sauvegarde, restauration, sync.
- [ ] P6.8 Documentation : architecture, dictionnaire de données, guide utilisateur (vendeur/gérant/responsable), runbook admin.
- [ ] P6.9 Recette : scénarios métier, PV de recette, liste des limites connues.
- [ ] P6.10 Pilote : formation, jeu de données réel, accompagnement.

**Critères de sortie** : installation reproductible, restauration testée, utilisateurs formés, PV signé.

---

## 6. V1.1 — Compléments métier (3–4 semaines)

Ces fonctions du CDC ne bloquent pas le pilote mais sont nécessaires à l'exploitation complète.

### 6.1 Retours clients
- [ ] Table `sale_returns` + `sale_return_items` (vente d'origine, motif, utilisateur, approbateur).
- [ ] Restitution d'argent et remise en stock = **actions distinctes** et contrôlées.
- [ ] Permission dédiée + audit.

### 6.2 Unités & conditionnements
- [ ] Choix de l'unité vendable (boîte/plaquette/etc.) selon produit.
- [ ] Conversion via `conversion_factor` (stock en unité de base).
- [ ] Ventes fractionnées autorisées par produit uniquement.

### 6.3 Tickets & impressions
- [ ] Ticket réglementaire Guinée (identité pharmacie, n°, date, lignes, remises, totaux, règlements, mentions).
- [ ] Réimpression + format imprimante à confirmer.
- [ ] Test sur imprimante réelle.

### 6.4 Moyens de paiement configurables
- [ ] Table `payment_methods` (espèces, mobile money, carte, autre) + CRUD.
- [ ] Paiement mixte piloté par les méthodes actives.

### 6.5 Lots (si confirmé client)
- [ ] `lot_number` sur réception et mouvements (sans péremption si non demandé).
- [ ] Traçabilité par lot dans les rapports.

### 6.6 Rapports & exports avancés
- [ ] Rapports : ventes, remises, stock, achats, assurances, caisses, transferts, activité utilisateurs.
- [ ] Exports CSV/PDF avec métadonnées (période, pharmacie, date de génération, filtres).
- [ ] Tableau de bord central : réseau, sites, alertes, état de sync.

### 6.7 Paramètres
- [ ] Paramètres pharmacie (identité, logo, mentions ticket).
- [ ] Paramètres remises/assurances.
- [ ] Gestion des devices et révocation.

---

## 7. V2 — Réseau & industrialisation (6–8 semaines)

- [ ] V2.1 Nœud local par pharmacie + serveur central (infra à budgéter).
- [ ] V2.2 Sync serveur-à-serveur réutilisant `sync_events` (ACK, retries, idempotence).
- [ ] V2.3 Consolidation multi-sites : ventes, stocks, assurances, caisses.
- [ ] V2.4 Résolution de conflits centralisée + écran responsable.
- [ ] V2.5 Données de référence versionnées diffusées aux sites.
- [ ] V2.6 Transferts inter-sites non confirmés tant que sync incomplète.
- [ ] V2.7 2FA (option), politique de mot de passe, expiration de session.
- [ ] V2.8 Sauvegardes automatisées multi-générations + PRA.
- [ ] V2.9 Monitoring central + tableau de bord de sync.
- [ ] V2.10 Documentation d'exploitation complète.

---

## 8. Qualité — Definition of Done

Une tâche n'est `[x]` que si **tous** les critères sont remplis :

1. Code revu et mergé.
2. Tests unitaires/feature ajoutés et verts.
3. `npm run build` OK.
4. `pint --test` + `eslint` OK.
5. Aucune valeur codée en dur introduite.
6. Permissions vérifiées côté serveur **et** masquage UI.
7. Audit ajouté si action critique.
8. Documentation mise à jour (`TODO.md`, plan, README).
9. Test manuel du parcours utilisateur.
10. Pour l'offline : test coupure/rétablissement réseau.

---

## 9. Risques & mitigations

| Risque | Impact | Mitigation |
|---|---|---|
| Complexité Inertia + offline | Élevé | Isoler la vente offline dans un « îlot SPA » (Pinia + Dexie) ; le reste en lecture cache |
| Conflits de stock offline | Élevé | Ventes append-only + validation serveur + écran de résolution, jamais de suppression silencieuse |
| Authentification hors ligne | Élevé | Session persistante V1.0, device token + PIN V1.1, révocation |
| Perte de données locales | Élevé | Outbox IndexedDB persistante + export de secours + sauvegardes serveur |
| Estimations optimistes | Moyen | Découper V1.0 / V1.1 / V2, livrer par incréments |
| Décisions client non tranchées | Moyen | Blocage explicite des tâches concernées (§10) |
| Matériel (lecteur code-barres, imprimante) | Moyen | Tests sur matériel réel avant pilote |
| Sécurité multi-sites | Élevé | Tests cross-site obligatoires à chaque PR |

---

## 10. Décisions à confirmer avec le client

- [ ] « Liens » = numéro de lot sans péremption, ou autre fonctionnalité ?
- [ ] Nombre de pharmacies, caisses par pharmacie, utilisateurs simultanés.
- [ ] Modèle de lecteur de codes-barres et d'imprimante de tickets.
- [ ] Exemples de cartes/contrats/règles d'assurance et décomptes.
- [ ] Exigences légales Guinée (justificatifs, fiscalité, catégories, conservation, données personnelles).
- [ ] Niveau de secours attendu (UPS, serveur de remplacement, procédure temporaire).
- [ ] Ouverture future multi-organisations (SaaS) ?
- [ ] Politique de remise par rôle (seuils exacts).
- [ ] Format réglementaire du ticket.

---

## 11. Correspondance avec `docs/TODO.md`

Le TODO reste le **tracker** ; ce plan est la **feuille de route**. Correspondance :

| TODO | Plan |
|---|---|
| Phase 0 — Stabilisation | Phases 0 + 1 |
| Phase 1 — Stock/achats/inventaires | Phase 2.2 + 2.4 |
| Phase 2 — Vente/caisse | Phase 2.1 + 2.3 + 6.3 + 6.4 |
| Phase 3 — Assurances | Phase 2.6 + 6.6 |
| Phase 4 — Rapports/dashboards | Phase 3.4 + 6.6 |
| Phase 5 — Hors ligne/sync | Phase 4 + 7 |
| Phase 6 — Sécurité/déploiement/docs | Phases 1 + 6 + 7 |
| Phase 7 — Tests/CI | Phase 5 |
| (absent du TODO) | Phase 3 — Design system |
| (absent du TODO) | Phase 1 — Form Requests/Policies/isolation |
| (absent du TODO) | Phase 4 — PWA/IndexedDB/outbox |

---

## 12. Première semaine — actions concrètes

1. Commit + restauration PDF + suppression du fichier parasite.
2. Créer la branche `chore/baseline`.
3. Mettre en place la CI minimale.
4. Implémenter `PharmacyContext` + scoping + premier test cross-pharmacie.
5. Corriger le prix serveur dans `SaleController` + test de falsification.
6. Corriger le signe des ajustements de stock + test.
7. Corriger la clôture de caisse + test.
8. Ouvrir les issues/tâches du plan dans le tracker.

> À l'issue de cette semaine : socle sécurisé, tests verts, CI en place. On peut alors attaquer le design et l'offline sereinement.
