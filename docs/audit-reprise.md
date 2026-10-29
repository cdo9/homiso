# Audit de reprise du projet Homiso

> Date : 27/10/2026
> Contexte : reprise du projet après l'ancien développeur.

Ce document liste les problèmes **restant à traiter** dans le code récupéré, leur gravité et ce qu'il faut faire pour chacun.
**Aucun secret n'est recopié ici** : les valeurs sont remplacées par `***`.

---

## Résumé

| # | Problème | Gravité | Statut |
|---|---|---|---|
| 1 | Mot de passe MySQL de prod en clair dans `public/import.php` | 🔴 Critique | ⚠️ Fichier ignoré, **mot de passe à changer** |
| 2 | Scripts d'administration accessibles publiquement dans `public/` | 🔴 Élevée | ⚠️ Fichiers ignorés, à supprimer du serveur |
| 3 | Clé API Spot-Hit (SMS) écrite en dur dans une commande | 🔴 Élevée | ❌ À corriger, **clé à régénérer** |
| 4 | Numéros de téléphone écrits en dur dans la commande SMS | 🟠 Moyenne | ❌ À corriger |
| 5 | Code mort et règles cachées dans la commande SMS (`$sender` inutilisé, pas d'envoi en août) | 🟡 Faible | ❌ À nettoyer |
| 6 | Envoi des SMS déclenché par une URL au lieu du scheduler | 🟠 Moyenne | ❌ À corriger |
| 7 | Clé Google Maps dans une page de test publique | 🟡 Faible | ⚠️ Fichier ignoré, clé à restreindre |
| 8 | Deux fichiers de lock (npm et pnpm) | 🟡 Faible | ✅ npm retenu |
| 9 | Telescope dans `require` : chargé en prod seulement si `APP_ENV=local` | 🟡 Faible | ❌ À vérifier (`APP_ENV` de prod) |
| 10 | Macros de débogage et `View::composer` mort dans `AppServiceProvider` | 🟡 Faible | ❌ À nettoyer |
| 11 | Anciens fichiers laissés dans `app/` (`_TableauChiffres.php`, `old_Map.php`) | 🟡 Faible | ❌ À supprimer |
| 12 | Paquets inutiles : `yoeunes/toastr` (paquet vide) et `ovh/ovh` (jamais utilisé) | 🟡 Faible | ✅ Retirés |
| 13 | 4 pages en erreur 500 (commissions, gestion-commerciaux, rattrapage, tableau-chiffres) | 🔴 Élevée | ❌ À analyser |
| 14 | Tests : règles métier couvertes, mais pas encore de test « chaque page s'affiche » ; test d'exemple Laravel en échec | 🟠 Moyenne | ⚠️ En cours |
| 15 | 5 erreurs PHPStan restantes (méthode inexistante, import manquant, retour manquant...) | 🟡 Faible | ❌ À corriger |
| 16 | Contrôle prix devis / prix tarif : le code et le message d'erreur se contredisent | 🟠 Moyenne | ❌ À faire trancher par le client |
| 17 | Faute de frappe `hasRole('ateleprodmin')` : `isTelepro` toujours faux dans l'argumentation | 🟡 Faible | ❌ À corriger |
| 18 | Le rôle `administratif` n'a pas accès à `rattrapage` | 🟡 Faible | ❌ À faire confirmer |
| 19 | Calcul des commissions dupliqué dans 3 endroits | 🟠 Moyenne | ❌ À factoriser |
| 20 | `StatsContactsServices` ne respecte pas le suffixe `Service` | 🟡 Faible | ❌ À renommer |

---

## 1. Mot de passe de la base de prod dans `public/import.php`

**Constat :**

```php
system("cat dumpSQL.sql | mysql --host=***.mysql.db --user=*** --password=*** ***");
```

- Les identifiants MySQL de **production** sont écrits en clair.
- Le fichier est dans `public/`, donc **accessible par URL** : n'importe qui qui l'appelle relance l'import de `dumpSQL.sql` dans la base de prod.
- `system()` lance une commande shell depuis le web : une très mauvaise pratique.

**À faire :**
1. **Changer le mot de passe MySQL** dans l'espace client OVH, puis mettre à jour le `.env` du serveur.
2. Supprimer `import.php` (et `dumpSQL.sql` s'il est présent) **du serveur de production**.
3. Pour importer un dump : `mysql ... < dump.sql` en SSH, ou phpMyAdmin depuis l'espace OVH.

> Fichier ajouté au `.gitignore` : il ne sera pas poussé. Mais le mot de passe a circulé, **il faut donc le considérer comme compromis**.

---

## 2. Scripts d'administration publics dans `public/`

Tout ce qui est dans `public/` peut être appelé depuis un navigateur, **sans authentification**.

| Fichier | Ce qu'il fait | Risque | Remplacement |
|---|---|---|---|
| `migration.php` | `php artisan migrate` | Modifie le schéma de la base de prod à la demande de n'importe qui | `php artisan migrate --force` en SSH / au déploiement |
| `clear.php` | Vide les caches config/route/view/cache/event | Ralentit le site, permet un petit DoS | `php artisan optimize:clear` |
| `check.php` | `dd()` de l'utilisateur connecté et de toute la session | Fuite de données de session | Telescope, ou `php artisan tinker` |
| `launchSMS.php` | `sms:dispatch-planned` | N'importe qui peut déclencher l'envoi des SMS (coût, spam) | Scheduler Laravel + cron (voir §6) |
| `testSMS.php` | Exactement la même chose que `launchSMS.php` | Doublon | À supprimer |

Petit détail : `launchSMS.php` et `testSMS.php` affichent « Caches vidés ! », un copier-coller de `clear.php`.

**Pourquoi ces scripts existent :** l'hébergement OVH mutualisé n'avait probablement pas d'accès SSH. L'ancien développeur lançait donc les commandes artisan en appelant des URLs. Le fichier `artisan` lui-même était d'ailleurs absent du projet.

**À faire :**
1. Supprimer ces fichiers **du serveur de production**.
2. Activer le SSH sur l'hébergement OVH (offres Pro et supérieures) pour lancer les commandes `php artisan` normalement.
3. Pour les tâches récurrentes, passer par le scheduler et un cron OVH (voir §6).

> Fichiers ajoutés au `.gitignore` en attendant leur suppression définitive.

---

## 3. Clé API Spot-Hit en dur dans `app/Console/Commands/DispatchPlannedSms.php`

**Constat :**

```php
$apiKey = "***";
$baseUrl = "https://www.spot-hit.fr/api/envoyer/sms";
```

Ce fichier fait partie du code applicatif : la clé est présente dans le commit initial (`e69cd31`). Une fois poussée, **elle reste dans l'historique Git**, même après correction.

**À faire :**

```php
// config/services.php
'spothit' => [
    'key'  => env('SPOTHIT_API_KEY'),
    'from' => env('SPOTHIT_FROM'),
],

// DispatchPlannedSms.php
$apiKey = config('services.spothit.key');
```

Puis **régénérer la clé** dans l'espace Spot-Hit : l'ancienne doit être considérée comme compromise. Les variables `SPOTHIT_API_KEY` et `SPOTHIT_FROM` sont déjà prévues dans `.env.example`.

---

## 4. Numéros de téléphone en dur

**Constat :** les destinataires sont écrits directement dans la commande :

```php
$receivers = "06********";            // rapport de 8h et résultats du vendredi
$receivers = "06********,07********"; // résultats de fin de mois
```

- Ce sont des données personnelles dans le code.
- Changer un destinataire impose de modifier le code et de redéployer.

**À faire :** les déplacer dans le `.env` (`SMS_RECEIVERS_DAILY`, `SMS_RECEIVERS_FRIDAY`, `SMS_RECEIVERS_MONTH_END`), dans la config, ou mieux, en base (paramètres de l'application).

---

## 5. Code mort et règles cachées dans la commande SMS

**Constat :** dans `DispatchPlannedSms.php` :
- `$sender = config('services.spothit.from')` lit une config qui n'existe pas, mais **la variable n'est jamais utilisée** : l'expéditeur est écrit en dur dans l'appel (`'expediteur' => 'Homiso'`). Les SMS partent donc bien avec l'expéditeur « Homiso ». Même chose pour `$smsLong` et `$smsLongNbr`, déclarées mais inutilisées ;
- `$description = 'Command description'` : jamais renseignée ;
- `if ($date->month == 8) return;` : règle métier « pas d'envoi en août » cachée dans le code, sans constante ni config ;
- le rapport de 8h exclut le secteur 10 en dur (`in_array($secteur->id, [10])`) ;
- les chiffres sont calculés au nom de l'utilisateur `id 1`, codé en dur ;
- la commande renvoie la réponse JSON de Spot-Hit au lieu d'un code de sortie (`self::SUCCESS`).

**À faire :** lors de la sortie de la clé (§3), lire l'expéditeur depuis `services.spothit.from` (valeur par défaut `Homiso`) et supprimer les variables mortes. Faire confirmer la règle d'août et l'exclusion du secteur 10 (questions à l'ancien développeur), puis les rendre explicites (constantes ou config).

---

## 6. Envoi des SMS par URL au lieu du scheduler

**Constat :** la commande `sms:dispatch-planned` existe et gère elle-même les dates : rapport de mapping à 8h les jours ouvrés, résultats le vendredi à 20h et le dernier jour ouvré du mois à 20h. Mais elle n'est **pas planifiée** dans `routes/console.php`. Seul `telescope:prune` l'est. L'envoi dépend donc d'un appel externe à `launchSMS.php` (cron OVH qui appelle une URL ?).

**À faire :**

```php
// routes/console.php
Schedule::command('sms:dispatch-planned')->hourly(); // la commande ne fait quelque chose qu'à 8h et 20h
```

Puis un seul cron OVH (Hébergement → Tâches planifiées) :

```
php /homez.xxx/www/artisan schedule:run
```

À vérifier : la configuration actuelle des tâches planifiées dans l'espace OVH, pour ne pas envoyer les SMS en double pendant la transition.

---

## 7. Clé Google Maps dans `public/test_google_maps.html`

**Constat :** une page de test laissée en production, avec une clé API Google Maps.

Une clé Maps est forcément visible côté navigateur. Le risque, c'est une clé **non restreinte**, que n'importe qui peut réutiliser à tes frais.

**À faire :**
1. Supprimer la page de test du serveur.
2. Dans Google Cloud Console : restreindre la clé aux référents HTTP du domaine Homiso et aux seules API utilisées.

---

## 8. Deux fichiers de lock

**Constat :** `package-lock.json` (npm) et `pnpm-lock.yaml` (pnpm) coexistent. Ils peuvent installer des versions différentes selon l'outil utilisé. Or `package.json` déclare `"packageManager": "pnpm@8.15.3"` : le gestionnaire prévu était donc **pnpm**.

**Fait :** npm retenu (habitude de l'équipe, et pnpm n'apporte rien sur 7 dépendances). `pnpm-lock.yaml` et le champ `packageManager` ont été supprimés.

---

## 9. Telescope en production

**Constat :** `laravel/telescope` est dans `require` (et non `require-dev`), et `TELESCOPE_ENABLED` vaut `true` par défaut. Mais le paquet est exclu de l'auto-découverte (`dont-discover` dans `composer.json`) et `AppServiceProvider` ne l'enregistre que si `APP_ENV=local`. En prod, Telescope n'est donc **pas chargé**, sauf si la prod tourne par erreur en `APP_ENV=local`. Dans ce cas, il enregistrerait toutes les requêtes, sessions et requêtes SQL. Le `gate` de `TelescopeServiceProvider` (liste d'e-mails vide) refuserait quand même l'accès à `/telescope` à tout le monde.

Effet de bord : la seule tâche du scheduler, `telescope:prune`, n'existe pas hors `local`.

**À faire :** vérifier `APP_ENV` (et `APP_DEBUG`) dans le `.env` de prod. Déplacer Telescope en `require-dev` et retirer `telescope:prune` du scheduler, ou le conditionner à l'environnement.

---

## 10. Code de débogage dans `AppServiceProvider`

**Constat :** dans `app/Providers/AppServiceProvider.php` :
- les macros `ddSql` font un `dd()` : pratiques en débogage. Les deux seuls appels (`ListingClient.php`) sont commentés ;
- le `View::composer('rendez-vous/confirmation-de-rendez-vous', ...)` vise une vue qui n'existe pas (le fichier s'appelle `confirmations-de-rendez-vous`) : ce code ne s'exécute jamais.

**À faire :** supprimer le composer mort, ou corriger le nom de la vue si elle a besoin de `$sources` / `$poseurs` (à vérifier d'abord).

> Les deux enregistrements Telescope dans `register()` (provider du paquet + provider de l'application) ne sont pas un doublon : c'est la configuration standard de Laravel pour réserver Telescope au local.

---

## 11. Anciens fichiers dans `app/`

**Constat :** Composer signale deux classes qui ne respectent pas PSR-4. Ce sont des copies de sauvegarde :
- `app/Livewire/TableauChiffre/_TableauChiffres.php`
- `app/Livewire/old_Map.php`

Aucun autre fichier ne les référence.

**À faire :** les supprimer (Git conserve l'historique, inutile de garder des copies `old_` / `_`).

---

## 12. Paquets inutiles

**Constat :**
- `yoeunes/toastr` v3 est un paquet **vide** : il ne contient aucun code et dépend seulement de `php-flasher/flasher-toastr-laravel`, déjà requis directement. Le helper `toastr()` utilisé dans les contrôleurs vient de `php-flasher`.
- `ovh/ovh` n'est utilisé nulle part : l'ancien envoi de SMS par OVH ne subsiste qu'en lignes commentées dans `DispatchPlannedSms.php`. C'est Spot-Hit qui est utilisé.

**Fait :** `composer remove yoeunes/toastr ovh/ovh`, et lignes OVH commentées supprimées de `DispatchPlannedSms.php`.

---

## 13. Pages en erreur 500

**Constat :** en local, connecté en admin avec les données de prod, ces pages plantent (le code d'origine donne les mêmes erreurs) :

| Page | Erreur |
|---|---|
| `/commissions` | `Method App\Livewire\VentesCommissions\Ventes::loadData does not exist` |
| `/gestion-commerciaux` | `Undefined variable $u` dans `livewire/gestion-commerciaux.blade.php` |
| `/rattrapage` | `Object of class Livewire\...\Redirector could not be converted to int` |
| `/tableau-chiffres` | `Undefined array key ""` dans `livewire/tableau-chiffre/main-tableau.blade.php` |

**À faire :** vérifier si ces pages plantent aussi en prod (certaines erreurs peuvent dépendre de l'utilisateur ou des données), puis les corriger une par une.

---

## 14. Tests

**Constat :** les règles métier documentées dans `.ai/rules` ont chacune leur test dans `tests/Feature/Regles/`, et `tests/Unit/ArchTest.php` vérifie les conventions de structure du code. Il manque encore un test « chaque page s'affiche ». Le test d'exemple `tests/Feature/ExampleTest.php` échoue toujours, car `/` redirige vers `/login` (302 au lieu de 200).

**À faire :** remplacer le test d'exemple par un test « chaque page s'affiche pour un admin » (cela aurait détecté le §13). Les tests utilisent la base MySQL `testing` de Sail.

---

## 15. Erreurs PHPStan restantes

**Constat :** Larastan (niveau 0, voir `phpstan.neon`) signale 5 erreurs, qui font échouer l'étape PHPStan de `ci-cd.sh`. Les 54 fausses alertes de relation ont déjà été réglées en typant les relations des modèles. Deux des cinq sont dans du code qui n'est jamais exécuté :

| Fichier | Erreur | Impact réel |
|---|---|---|
| `app/Http/Controllers/RendezVousController.php:89` | `SourceService::listPriorities()` n'existe pas (la classe n'a que `createSourceLst()` et `getSourceName()`) | **Aucun aujourd'hui** : `create()` n'est reliée à aucune route (`/nouveau-rendez-vous` affiche directement la vue). Plantage assuré si quelqu'un la branche |
| `app/Livewire/Client/ClientForm.php:160` | `DB::table()` sans `use Illuminate\Support\Facades\DB` | **Aucun aujourd'hui** : le composant n'est intégré dans aucune vue (seul `old_Map.php`, lui-même mort, lui envoie des événements). Plantage assuré s'il est utilisé |
| `app/Http/Controllers/CommentController.php:40` | `store()` ne renvoie rien quand `type` et `commentaire` sont tous les deux vides | Faible : `POST /comments` répond 200 avec un corps vide au lieu d'une réponse JSON. Le JavaScript qui l'appelle peut mal le gérer |
| `app/Livewire/VentesCommissions/Ventes.php:106` | `User::where('id', …)->get()->first()` | Charge une collection pour un seul utilisateur. Et si l'id n'existe pas, `->toArray()` sur `null` plante |
| `app/Livewire/VentesCommissions/Ventes.php:513` | Même code dupliqué | Idem |

**À faire :**
- `ClientForm` et `RendezVousController::create()` : vérifier qu'ils sont bien morts, puis les supprimer (avec la vue `livewire/client/client-form.blade.php` et `old_Map.php`, §11), ou les corriger s'ils doivent servir.
- `CommentController::store()` : renvoyer une réponse JSON explicite (une erreur de validation si les deux champs sont vides).
- `Ventes.php` : remplacer par `User::findOrFail($this->commercial)` (ou `find()` avec gestion du `null`), et factoriser les deux occurrences.

---

## 16. Contrôle prix devis / prix tarif contradictoire

**Constat :** dans `app/Livewire/EditArgumentation.php` (`save()`), l'enregistrement est refusé si `devis_total_ht > tarif_total_ht`, mais le message affiché dit « Le prix devis ne peut pas être **inférieur** au prix tarif ». Soit la condition est inversée, soit le message est faux.

**À faire :** demander au client quelle est la vraie règle (devis jamais au-dessus du tarif, ou jamais en dessous ?), puis corriger le code ou le message. Le comportement actuel est figé par `tests/Feature/Regles/Ventes/PrixDevisTarifTest.php` : mettre à jour la règle (`.ai/rules/ventes-argumentations.md`) et le test en même temps.

---

## 17. Faute de frappe sur le rôle télépro dans l'argumentation

**Constat :** dans `EditArgumentation::mount()`, `$this->isTelepro = $user->hasRole('ateleprodmin')`. Ce rôle n'existe pas : `isTelepro` vaut donc toujours `false`, même pour un téléprospecteur.

**À faire :** vérifier dans la vue `livewire/edit-argumentation.blade.php` ce que `isTelepro` affiche ou masque, puis remplacer par `hasRole('telepro')`. Attention, la correction peut changer ce que voient les télépros : à faire valider.

---

## 18. Le rôle `administratif` n'a pas accès à `rattrapage`

**Constat :** dans `User::getAuthorizedPages()`, tous les rôles reçoivent les pages « toujours accessibles » (`rattrapage`, `horaire`, `formulaire-contact`, `planning-telepro`, `confirmations`), sauf `administratif`, dont la liste est **remplacée** au lieu d'être complétée (`$page_enable = [...]` au lieu de `array_merge`).

**À faire :** faire confirmer si c'est voulu. Si non, utiliser `array_merge` comme pour les autres rôles et mettre à jour `PagesAutoriseesParRoleTest`.

---

## 19. Calcul des commissions dupliqué

**Constat :** les formules de commission sont dans `app/Services/CommissionService.php`, mais une partie est recopiée dans `app/Livewire/VentesCommissions/Ventes.php` et dans `resources/views/livewire/ventes-commissions/vente.blade.php` (« legacy logic »). Une correction faite à un endroit peut être oubliée ailleurs, et les tests ne couvrent que le service.

**À faire :** faire appeler `CommissionService` par le composant et la vue, puis supprimer les copies. Les tests `tests/Feature/Regles/Commissions/` protègent les formules pendant ce travail.

---

## 20. `StatsContactsServices` mal nommé

**Constat :** `app/Services/StatsContactsServices.php` est la seule classe du dossier sans le suffixe `Service`. Elle est exclue de la règle correspondante dans `tests/Unit/ArchTest.php`.

**À faire :** la renommer en `StatsContactsService` (2 usages : `MapController.php` et `old_Map.php`, ce dernier étant à supprimer, §11), puis retirer l'exception de l'`ArchTest`.

---

## Ordre d'action recommandé

1. Changer le mot de passe MySQL OVH (§1) et régénérer la clé Spot-Hit (§3)
2. Sortir la clé Spot-Hit et les numéros du code, nettoyer la commande SMS (§3, §4, §5)
3. Supprimer les scripts de `public/` sur le serveur de prod (§2, §7)
4. Mettre en place le scheduler et le cron (§6)
5. Corriger les pages en erreur 500 (§13)
6. Faire trancher le client sur le prix devis/tarif et l'accès `administratif` (§16, §18)
7. Vérifier Telescope, faire le ménage (§9 à §11, §17, §20), corriger les erreurs PHPStan (§15), compléter les tests (§14)
8. Factoriser le calcul des commissions (§19)
