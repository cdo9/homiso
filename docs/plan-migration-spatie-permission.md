# Plan de migration vers `spatie/laravel-permission`

> Objectif : remplacer le système de rôles maison (`users.role` + drapeaux `directeur` / `telepro` + `User::hasRole()` + `RoleMiddleware` + `User::getAuthorizedPages()`) par les rôles et permissions de Spatie, **sans changer le comportement de l'application**, en plusieurs étapes déployables séparément et réversibles.
>
> Voir aussi : [`pages-et-acces.md`](pages-et-acces.md), qui décrit le comportement actuel à préserver.

---

## 0. Principes

1. **On ne change pas le comportement pendant la migration.** Les bugs et incohérences connus (voir §9) sont corrigés à part, comme décisions métier distinctes.
2. **On commence par un filet de tests.** Les règles d'accès actuelles sont couvertes par `tests/Feature/Regles/AccesDroits/` : ces tests doivent passer avant et après la migration.
3. **On centralise d'abord, on change de moteur ensuite.** Tout le code doit passer par un point unique avant qu'on remplace ce qu'il y a derrière.
4. **On fait tourner les deux systèmes en parallèle** (mode « ombre ») avant de basculer : Spatie calcule, l'ancien système décide, et chaque différence est journalisée.
5. **On garde les colonnes `users.role`, `directeur` et `telepro` jusqu'à la dernière phase.** Elles servent de solution de retour arrière.

---

## 1. État des lieux (le code à migrer)

| Élément | Où | Volume |
|---|---|---|
| `User::hasRole(string $role)` (hiérarchie maison) | `app/Models/User.php` | **72 appels** dans `app/` et `resources/` |
| Tests directs sur `->role` | `EditContact`, `EditArgumentation`, `RelanceListing`, `BlocagesTrait`, `CheckBlocagesAccess`, `Ventes`, vues `nouveau-contact`, `edit-contact`, `edit-argumentation` | ~25 |
| Requêtes SQL sur la colonne `role` | `CommerciauxService` (×4), `Blocages`, `TableauChiffreService`, `ClientForm`, `ClientController`, `EditArgumentation`, `Ventes` (`users.role <> 'admin'`) | ~12 |
| Middleware `role:` (alias → `App\Http\Middleware\RoleMiddleware`) | `routes/web.php` | 6 groupes de routes |
| Visibilité du menu `getAuthorizedPages()` / `canAccessPage()` | `sidebar`, `mobile-menu`, `list-cards` | 3 vues |
| Écriture du rôle | `GestionCommerciaux::updateUserField()` via `User::where()->update()` (requête de masse, **sans événements Eloquent**), `createUser()` | 2 |

### Le piège principal : la sémantique de `hasRole()`

La méthode maison **n'a pas la même sémantique** que celle de Spatie, alors qu'elle porte le même nom :

| Appel actuel | Vrai pour | Avec Spatie, `hasRole()` serait vrai pour |
|---|---|---|
| `hasRole('commercial')` | admin, directeur, commercial | commercial seulement |
| `hasRole('directeur')` | admin, directeur, `directeur = 1` | directeur seulement |
| `hasRole('telepro')` / `hasRole('prospecteur')` | telepro, resp-prosp, `telepro = 1` | telepro seulement |

Un simple `use HasRoles;` casserait donc des droits sans aucun message d'erreur. De plus, le trait Spatie déclare lui aussi `hasRole()`, et ses méthodes internes (`hasAnyRole()`, middlewares) l'appellent. Les deux ne peuvent pas coexister.

**Conséquence :** il faut d'abord **sortir l'application de `hasRole()`** (phase 2), puis seulement installer Spatie (phase 3).

---

## 2. Phases

### Phase 1 — Filet de sécurité (tests de caractérisation)

*Aucun changement en production.*

1. **Réparer `UserFactory`.** Elle utilise `name`, qui n'existe pas dans la table `users` : il faut `nom`, `prenom`, `objectif_ca`, `objectif_rdv`, `type`, etc. On ajoute un état par rôle : `admin()`, `directeur()`, `commercial()`, `telepro()`, `respProsp()`, `administratif()`, `metreur()`, `sansRole()`. On ajoute aussi les états `avecDrapeauDirecteur()` et `avecDrapeauTelepro()`.
2. **`tests/Unit/UserRoleHierarchyTest.php`** : un dataset Pest qui fige la table de vérité complète de l'actuel `hasRole()`, pour chaque rôle et chaque drapeau.
3. **`tests/Feature/RouteAccessTest.php`** : un dataset « route × rôle → OK ou redirection vers `welcome` » qui reprend la matrice de `pages-et-acces.md` (§3 et §4). Il faut neutraliser les blocages dans ces tests, par exemple avec `released = today()` ou en mockant `Blocages`.
4. **`tests/Feature/MenuVisibilityTest.php`** : pour chaque rôle, on vérifie la liste exacte renvoyée par `getAuthorizedPages()`.
5. **`tests/Feature/BlocagesAccessTest.php`** : un admin n'est jamais bloqué, un utilisateur bloqué est redirigé vers `rattrapage`, et les routes de la liste autorisée restent accessibles.

✅ **Critère de sortie :** les tests passent sur le code actuel (`vendor/bin/sail artisan test --compact`). Ils devront rester **verts et inchangés** jusqu'à la phase 6.

---

### Phase 2 — Centraliser les vérifications de rôle (refactor sans changement de comportement)

*Spatie n'est pas encore installé. Le but est que plus aucun code ne lise `->role` ni n'appelle `hasRole()` directement.*

1. **Ajouter des méthodes métier explicites sur `User`.** Elles reproduisent exactement la logique actuelle :

   ```php
   public function isAdmin(): bool              // role = admin
   public function isDirecteur(): bool          // admin, directeur ou drapeau directeur = 1
   public function isVendeur(): bool            // admin, directeur, commercial (= ancien hasRole('commercial'))
   public function isTeleprospecteur(): bool    // telepro, resp-prosp ou drapeau telepro = 1
   public function isResponsableProspection(): bool
   public function isAdministratif(): bool
   public function isMetreur(): bool
   public function isFormateur(): bool
   public function isResponsableDeGroupe(): bool // groupes.id_responsable (reste une donnée, pas un rôle)
   ```

2. **Remplacer les 72 `hasRole('x')`** par la méthode correspondante. Le remplacement est mécanique, à faire fichier par fichier :
   - `hasRole('admin')` → `isAdmin()`
   - `hasRole('directeur')` → `isDirecteur()`
   - `hasRole('commercial')` / `hasRole('vendeur')` → `isVendeur()`
   - `hasRole('telepro')` / `hasRole('prospecteur')` → `isTeleprospecteur()`
   - ⚠️ `EditArgumentation.php:166` : `hasRole('ateleprodmin')` vaut toujours `false`. On garde `false` à l'identique, avec un `@todo`, et on traite la correction en §9.
3. **Remplacer les tests directs sur `->role`.** Exemples :
   - `in_array($user->role, ['directeur','admin'])` → `$user->role === 'admin' || $user->role === 'directeur'`, encapsulé dans une méthode dédiée comme `isAdminOuDirecteurStrict()`. Attention : ici le drapeau `directeur = 1` n'est **pas** pris en compte, contrairement à `isDirecteur()`. Cette différence est à préserver.
   - `$user->role == 'admin'` → `isAdmin()`.
   - `auth()->user()->role == 'responsable'` (vue `nouveau-contact`) → `isRoleResponsable()`.
4. **Renommer l'ancien `hasRole()` en `hasLegacyRole()`** et le marquer `@deprecated`. Après l'étape 2, il ne sert plus qu'à `RoleMiddleware` et aux tests.
5. **Renommer l'alias de middleware `role` en `legacy.role`** dans `bootstrap/app.php` et `routes/web.php`. Cela libère le nom `role` pour Spatie.
6. **Supprimer l'alias mort `page.access`** : la classe `PageAccessMiddleware` n'existe pas.
7. **Faire passer l'écriture du rôle par Eloquent.** Dans `GestionCommerciaux::updateUserField()`, remplacer `User::where('id', $userId)->update(...)` par `User::find($userId)->update(...)`. Sinon, la synchronisation de la phase 3 ne sera jamais déclenchée.

✅ **Critère de sortie :** les tests de la phase 1 sont verts, `grep -rn "hasRole(\|->role\b" app resources` ne remonte plus que `User.php` et `RoleMiddleware`, et Pint passe.

---

### Phase 3 — Installer Spatie en parallèle (mode « ombre »)

*Spatie est installé et alimenté, mais **il ne décide encore de rien**.*

1. **Installer le package** (⚠️ nouvelle dépendance, à valider) :
   ```bash
   vendor/bin/sail composer require spatie/laravel-permission:^6.0
   vendor/bin/sail artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
   ```
   - `config/permission.php` : `teams => false` (les secteurs restent un filtrage de données) et le guard `web`.
   - Lancer la migration publiée. Elle crée les tables `roles`, `permissions`, `model_has_roles`, `model_has_permissions` et `role_has_permissions`.
2. **Ajouter `use HasRoles;` sur `User`.** Il n'y a plus de conflit, puisque l'ancienne méthode s'appelle maintenant `hasLegacyRole()`.
3. **Enregistrer les middlewares Spatie** dans `bootstrap/app.php` : `role`, `permission` et `role_or_permission`. Pour l'instant, ils ne sont utilisés nulle part.
4. **Créer `database/seeders/RolesAndPermissionsSeeder.php`** (idempotent, avec `firstOrCreate`). Il crée les rôles et permissions décrits au §3 et au §4.
5. **Créer une commande `app/Console/Commands/SyncLegacyRolesCommand.php`** (`permissions:sync-legacy {--dry-run}`). Pour chaque utilisateur, **actifs comme inactifs** (177 comptes) :
   - `users.role` → rôle Spatie du même nom ;
   - `directeur = 1` → ajout du rôle `directeur` (3 comptes concernés aujourd'hui) ;
   - `telepro = 1` → ajout du rôle `telepro` (5 comptes, dont 3 commerciaux) ;
   - `role = null` → aucun rôle (2 comptes) ;
   - à la fin, un rapport : nombre de comptes par rôle et liste des cas particuliers.
6. **Garder les deux systèmes synchronisés pendant la transition.** Un `UserObserver::saved()` appelle `syncRoles()` dès que `role`, `directeur` ou `telepro` a changé. Ce point dépend de l'étape 7 de la phase 2.
7. **Mode ombre** : config `permission-migration.mode` = `legacy` | `shadow` | `spatie` (par défaut `legacy`).
   - En `shadow`, chaque méthode `isXxx()` calcule les deux résultats, **renvoie le résultat legacy** et journalise toute différence (`Log::channel('permissions')`).
   - On laisse tourner une à deux semaines en production avec `shadow`, et on vérifie que le journal reste vide.
8. **Ajouter les tests** : le seeder est idempotent, la commande de synchronisation produit les bons rôles pour chaque combinaison rôle × drapeau, et l'observer resynchronise après une modification depuis la gestion des commerciaux.

✅ **Critère de sortie :** les tests sont verts et le journal d'écarts du mode ombre est vide en production.

---

### Phase 4 — Basculer les méthodes `isXxx()` sur Spatie

1. Passer `permission-migration.mode` à `spatie`. Les méthodes s'appuient alors sur Spatie, et **la hiérarchie devient explicite** :
   ```php
   public function isDirecteur(): bool
   {
       return $this->hasAnyRole(['admin', 'directeur']);
   }

   public function isVendeur(): bool
   {
       return $this->hasAnyRole(['admin', 'directeur', 'commercial']);
   }

   public function isTeleprospecteur(): bool
   {
       return $this->hasAnyRole(['telepro', 'resp-prosp']);
   }
   ```
   Les drapeaux sont déjà convertis en rôles par la phase 3.
2. **Charger les rôles en avance (eager load).** Dans les listes qui appellent `isXxx()` pour chaque utilisateur (`Blocages::loadBlocagesParUser()`, `EditArgumentation` sur `$this->commerciaux`, `ArgumentationController`), ajouter `->with('roles')` pour éviter les requêtes N+1.
3. **Retour arrière** : il suffit de repasser le mode à `legacy`. Les colonnes sont toujours à jour grâce à l'observer.

✅ **Critère de sortie :** les tests de la phase 1 sont verts en mode `spatie`. On peut les lancer dans les deux modes avec un dataset sur le mode.

---

### Phase 5 — Routes et menu sur les permissions

1. **Routes** : remplacer `legacy.role:...` par `permission:...` en suivant le tableau du §4. Pour un admin qui doit tout voir, on utilise une règle « super-admin » :
   ```php
   // AppServiceProvider::boot()
   Gate::before(fn (User $user) => $user->hasRole('admin') ? true : null);
   ```
   Cela reproduit le fait qu'aujourd'hui l'admin passe tous les groupes de routes.
2. **Menu** : le menu actuel **ne correspond pas** aux routes (voir §9). Pour ne rien changer, on crée des permissions `menu.*` distinctes des permissions `pages.*`, qui reproduisent exactement `getAuthorizedPages()`. Ensuite :
   - `canAccessPage($route)` devient `$this->can('menu.'.$route)` ;
   - dans les vues, on peut utiliser `@can('menu.ventes')`.

   L'alignement entre menu et routes se fera plus tard, en supprimant simplement les permissions `menu.*` (§9).
3. **Le cas particulier `id = 1`** dans `getAuthorizedPages()` (accès menu étendu) : on attribue directement les permissions concernées à cet utilisateur, avec `givePermissionTo()`, plutôt que de tester l'id.
4. **Supprimer `RoleMiddleware`, `hasLegacyRole()` et `getAuthorizedPages()`.**

✅ **Critère de sortie :** les tests `RouteAccessTest` et `MenuVisibilityTest` sont verts sans modification.

---

### Phase 6 — Nettoyage

1. **Écran de gestion des commerciaux** : le `<select>` du rôle appelle `syncRoles()`. On ajoute des cases à cocher « Directeur » et « Télépro » pour remplacer les drapeaux. Attention : `createUser()` initialise `role => 'vendeur'`, mais ce champ n'est pas validé, donc il n'est pas enregistré, ce qui explique les comptes sans rôle. Il faut imposer le choix d'un rôle à la création.
2. **Requêtes SQL sur `role`** : remplacer `whereNotIn('role', ['administratif'])` par `User::withoutRole('administratif')`, et `whereNotIn('role', ['admin','administratif'])` par `withoutRole([...])`. Faire de même pour `->select(..., 'role', 'directeur', 'telepro')`. Penser à vider le cache `CommerciauxService` au déploiement.
3. **`Ventes.php:461`** : `str_contains($commercial['role'], 'directeur')` doit passer par le rôle Spatie. Le tableau doit alors contenir les rôles chargés.
4. **Mettre à jour les tests** pour créer les utilisateurs avec `->assignRole()`. On peut ajouter des états de factory `afterCreating`.
5. **Après une période de stabilité** (1 à 2 mois), créer une migration qui supprime `users.role`, `users.directeur` et `users.telepro`, avec une méthode `down()` qui recrée et réalimente ces colonnes. Supprimer aussi l'observer, la config de mode et la commande de synchronisation.
6. **Mettre à jour `pages-et-acces.md`.**

---

## 3. Rôles Spatie

| Rôle | Correspondance actuelle |
|---|---|
| `admin` | `role = admin` (super-admin via `Gate::before`) |
| `directeur` | `role = directeur` **ou** `directeur = 1` |
| `commercial` | `role = commercial` |
| `resp-prosp` | `role = resp-prosp` |
| `telepro` | `role = telepro` **ou** `telepro = 1` |
| `administratif` | `role = administratif` |
| `metreur` | `role = metreur` (aucun compte aujourd'hui) |

Ce qui **ne devient pas** un rôle Spatie :
- **Responsable de groupe** (`groupes.id_responsable`) : c'est une relation de données, pas un rôle.
- **Périmètre secteur** (`secteur_commercials`, session `selectedSecteur`) : c'est du filtrage de données, qui reste dans les composants ou pourra passer dans des Policies plus tard.
- `formateur`, `is_animateur`, `nouveau_vendeur` : ce sont des attributs métier. On pourra en faire des rôles plus tard si besoin, mais `hasRole('formateur')` et `hasRole('animateur')` ne sont appelés nulle part aujourd'hui.

Un utilisateur peut avoir **plusieurs rôles** (par exemple `commercial` + `telepro`). C'est ce qui remplace les drapeaux.

---

## 4. Permissions de pages (routes)

Ces permissions reprennent exactement les groupes de `routes/web.php`. Les pages ouvertes à tout utilisateur connecté (`welcome`, `nouveau-contact`, `confirmations-de-rendez-vous`, `rattrapage`, `logout`) n'ont pas besoin de permission.

| Permission | Routes | admin | directeur | commercial | resp-prosp | telepro | administratif |
|---|---|:-:|:-:|:-:|:-:|:-:|:-:|
| `pages.operations` | ventes, argumentations, poses, rendez-vous | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `pages.commercial` | map, listing-client, clients.*, relances, argumentation (create/store/edit), nouveau-rendez-vous, edit-rendez-vous, tableau-chiffres, classement, challenges, stats-produit, anniversaires, parametres, confirmations, comments.store | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| `pages.direction` | commissions, poseurs.*, nouvelle-pose, challenges (create/edit/delete), clients.update-type, blocages | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| `pages.stats-contact` | stats-contact | ✅ | ✅ | ❌ | ✅ | ❌ | ❌ |
| `pages.administration` | gestion-commerciaux, parc-auto + véhicules, secteurs-list, alertes | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |

> Dans un premier temps, on garde ces **permissions par groupe**, pour un mapping 1:1 avec l'existant et un risque minimal. Une fois la migration stabilisée, on pourra les découper finement (`ventes.voir`, `ventes.modifier-toutes`, `blocages.debloquer`, etc.) pour remplacer les `if ($this->isDirecteur)` à l'intérieur des composants.

Les permissions de menu (`menu.<route>`) reprennent à l'identique les listes de `getAuthorizedPages()` pour chaque rôle (voir `User.php`, lignes 179 à 292).

---

## 5. Points de vigilance

- **Cache Spatie** : lancer `vendor/bin/sail artisan permission:cache-reset` à chaque déploiement qui touche le seeder. Les tests doivent aussi réinitialiser le cache dans `beforeEach`.
- **Cache applicatif existant** : `CommerciauxService` (1 h) et `BlocagesTrait` (10 min) mettent en cache des listes construites sur `role`. Il faut les vider à la bascule.
- **Performance** : `hasAnyRole()` déclenche une requête si `roles` n'est pas chargé. Les pages qui bouclent sur des utilisateurs (Blocages, Classement, Tableau des chiffres) doivent faire `->with('roles')`.
- **Requêtes de masse** : `User::where()->update()` et `DB::table('users')->update()` ne déclenchent pas l'observer. Il faut les supprimer ou les remplacer pour tout ce qui touche au rôle (`GestionCommerciaux::saveUser()` utilise `DB::table('users')`).
- **Le middleware `blocages`** teste `$user->role === 'admin'` : il passe à `isAdmin()` en phase 2.
- **Base de test** : MySQL `homiso_testing`. Le seeder des rôles doit être lancé dans les tests (`$this->seed(RolesAndPermissionsSeeder::class)`).

---

## 6. Retour arrière

| Phase | Comment revenir en arrière |
|---|---|
| 1 | Rien à faire (uniquement des tests) |
| 2 | `git revert` (refactor pur) |
| 3 | Mode `legacy`. Les tables Spatie peuvent rester, elles ne sont pas lues |
| 4 | Repasser le mode à `legacy`. Les colonnes sont toujours synchronisées |
| 5 | `git revert` des routes et du menu, mode `legacy` |
| 6 | La migration `down()` recrée les colonnes depuis les rôles Spatie |

---

## 7. Estimation

| Phase | Charge indicative |
|---|---|
| 1. Tests de caractérisation | 1,5 à 2 j |
| 2. Centralisation | 1,5 j |
| 3. Installation + mode ombre | 1 j (+ 1 à 2 semaines d'observation) |
| 4. Bascule | 0,5 j |
| 5. Routes + menu | 1 j |
| 6. Nettoyage | 1 j (+ période de stabilité avant suppression des colonnes) |

---

## 8. Ordre des commits suggéré

1. `test: tests de caractérisation des rôles, routes et menu`
2. `refactor: méthodes isXxx() sur User et remplacement des hasRole()`
3. `refactor: renommage du middleware role en legacy.role`
4. `chore: installation de spatie/laravel-permission`
5. `feat: seeder des rôles et permissions + commande permissions:sync-legacy`
6. `feat: observer de synchronisation + mode ombre`
7. `feat: bascule des vérifications de rôle sur Spatie`
8. `feat: routes et menu sur les permissions Spatie`
9. `chore: suppression de l'ancien système de rôles`

---

## 9. Hors périmètre : corrections à décider séparément

Ces points **changeraient le comportement**. Ils sont donc volontairement exclus de la migration et doivent être tranchés côté métier :

1. **Le menu et les routes ne concordent pas** : `/classement` est masqué pour les directeurs et les commerciaux, `/listing-client` est masqué pour les télépros, `/alertes` n'apparaît pour personne, et le métreur voit « Ventes » sans pouvoir l'ouvrir. Après la phase 5, on corrige en supprimant les permissions `menu.*` et en affichant le menu selon `pages.*`.
2. **`hasRole('ateleprodmin')`** dans `EditArgumentation` : il faut décider s'il s'agissait de `isTeleprospecteur()`.
3. **`isDirecteur()` et le test strict sur la colonne** : `isDirecteur()` prend en compte le drapeau `directeur = 1`, alors que certains écrans (`EditContact`, `RelanceListing`, `EditArgumentation`) testent seulement la colonne `role`. Un utilisateur avec le drapeau peut donc ouvrir une page mais ne peut pas y modifier certains champs. Il faut harmoniser.
4. **Routes AJAX sans `auth`** (`/ajax/commercials-by-secteur`, `/ajax/setSelectedSecteur`) : il faut ajouter `auth`. C'est une correction de sécurité, à faire tout de suite et indépendamment de la migration.
5. **La route `/confirmations` est déclarée deux fois**, et il existe une route parasite `/gestion.gestion-commerciaux`.
