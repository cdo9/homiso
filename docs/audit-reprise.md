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
| 5 | `config('services.spothit.from')` pointe vers une config inexistante | 🟠 Moyenne | ❌ À corriger |
| 6 | Envoi des SMS déclenché par une URL au lieu du scheduler | 🟠 Moyenne | ❌ À corriger |
| 7 | Clé Google Maps dans une page de test publique | 🟡 Faible | ⚠️ Fichier ignoré, clé à restreindre |
| 8 | Deux fichiers de lock (npm et pnpm) | 🟡 Faible | ❌ À trancher |
| 9 | Telescope installé en production et activé par défaut | 🟡 Faible | ❌ À vérifier |
| 10 | Code de débogage dans `AppServiceProvider` | 🟡 Faible | ❌ À nettoyer |
| 11 | Anciens fichiers laissés dans `app/` (`_TableauChiffres.php`, `old_Map.php`) | 🟡 Faible | ❌ À supprimer |
| 12 | Paquet abandonné `yoeunes/toastr` en doublon de `php-flasher` | 🟡 Faible | ❌ À retirer |
| 13 | 4 pages en erreur 500 (commissions, gestion-commerciaux, rattrapage, tableau-chiffres) | 🔴 Élevée | ❌ À analyser |
| 14 | Pas de vrais tests (seul le test d'exemple Laravel, en échec) | 🟠 Moyenne | ❌ À construire |

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
$receivers = "06********";
$receivers = "06********,07********";
```

- Ce sont des données personnelles dans le code.
- Changer un destinataire impose de modifier le code et de redéployer.

**À faire :** les déplacer dans le `.env` (`SMS_RECEIVERS_FRIDAY`, `SMS_RECEIVERS_MONTH_END`), dans la config, ou mieux, en base (paramètres de l'application).

---

## 5. Config `services.spothit` inexistante

**Constat :** la commande lit `config('services.spothit.from')`, mais `config/services.php` ne contient pas d'entrée `spothit`. La valeur vaut donc toujours `null`, et l'expéditeur des SMS n'est jamais celui prévu.

**À faire :** ajouter l'entrée `spothit` (voir §3) et la variable `SPOTHIT_FROM` dans le `.env`.

Autres détails dans cette commande :
- `$description = 'Command description'` : jamais renseignée ;
- des `use` inutiles (`Secteur`, `TableauChiffres`, `User`...) ;
- `if ($date->month == 8) return;` : règle métier « pas d'envoi en août » cachée dans le code, sans constante ni config.

---

## 6. Envoi des SMS par URL au lieu du scheduler

**Constat :** la commande `sms:dispatch-planned` existe et gère elle-même les dates (vendredi soir, dernier jour ouvré du mois). Mais elle n'est **pas planifiée** dans `routes/console.php`. Seul `telescope:prune` l'est. L'envoi dépend donc d'un appel externe à `launchSMS.php` (cron OVH qui appelle une URL ?).

**À faire :**

```php
// routes/console.php
Schedule::command('sms:dispatch-planned')->hourly(); // fréquence à valider selon la logique interne
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

**Constat :** `package-lock.json` (npm) et `pnpm-lock.yaml` (pnpm) coexistent. Ils peuvent installer des versions différentes selon l'outil utilisé. Or `package.json` déclare `"packageManager": "pnpm@8.15.3"` : le gestionnaire prévu est donc **pnpm**.

**À faire :** passer à pnpm (`sail pnpm install`), supprimer `package-lock.json` et l'indiquer dans le README.

---

## 9. Telescope en production

**Constat :** `laravel/telescope` est dans `require` (et non `require-dev`), et `TELESCOPE_ENABLED` vaut `true` par défaut. En prod, Telescope enregistre requêtes, sessions et requêtes SQL : c'est lourd, et sensible si l'accès au tableau de bord `/telescope` est mal protégé.

**À faire :** vérifier le `gate` dans `TelescopeServiceProvider`, et mettre `TELESCOPE_ENABLED=false` en prod sauf besoin ponctuel.

---

## 10. Code de débogage dans `AppServiceProvider`

**Constat :** dans `app/Providers/AppServiceProvider.php` :
- `TelescopeServiceProvider` est enregistré deux fois dans `register()` (celui du paquet et celui de l'application) ;
- les macros `ddSql` font un `dd()` : pratiques en débogage, mais à ne jamais laisser appelées dans le code.

- le `View::composer('rendez-vous/confirmation-de-rendez-vous', ...)` vise une vue qui n'existe pas (le fichier s'appelle `confirmations-de-rendez-vous`) : ce code ne s'exécute jamais.

**À faire :** vérifier quel provider Telescope est vraiment nécessaire, chercher les appels à `ddSql` restants, et corriger ou supprimer le composer mort (vérifier d'abord si la vue a besoin de `$sources` / `$poseurs`).

---

## 11. Anciens fichiers dans `app/`

**Constat :** Composer signale deux classes qui ne respectent pas PSR-4. Ce sont des copies de sauvegarde :
- `app/Livewire/TableauChiffre/_TableauChiffres.php`
- `app/Livewire/old_Map.php`

**À faire :** les supprimer (Git conserve l'historique, inutile de garder des copies `old_` / `_`).

---

## 12. Paquet abandonné `yoeunes/toastr`

**Constat :** `yoeunes/toastr` est abandonné, et son remplaçant `php-flasher/flasher-toastr-laravel` est **déjà installé**. Les deux cohabitent.

**À faire :** vérifier qu'aucun code n'utilise encore `yoeunes/toastr`, puis `composer remove yoeunes/toastr`.

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

## 14. Pas de vrais tests

**Constat :** seuls les deux tests d'exemple de Laravel existent. Celui de `tests/Feature/ExampleTest.php` échoue, car `/` redirige vers `/login` (302 au lieu de 200).

**À faire :** remplacer les tests d'exemple par des tests utiles, en commençant par un test « chaque page s'affiche pour un admin » (cela aurait détecté le §13). Les tests utilisent la base MySQL `testing` de Sail.

---

## Ordre d'action recommandé

1. Changer le mot de passe MySQL OVH (§1) et régénérer la clé Spot-Hit (§3)
2. Sortir la clé Spot-Hit et les numéros du code (§3, §4, §5)
3. Supprimer les scripts de `public/` sur le serveur de prod (§2, §7)
4. Mettre en place le scheduler et le cron (§6)
5. Corriger les pages en erreur 500 (§13)
6. Trancher npm/pnpm, vérifier Telescope, faire le ménage (§8 à §12), écrire des tests (§14)
