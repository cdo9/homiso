# Questions pour l'ancien développeur

> Préparé le 27/10/2026, après l'import du projet et une première installation en local.
> Les questions sont classées par priorité : les premières bloquent toute intervention sur la prod.
> Les renvois `audit §n` pointent vers [`audit-reprise.md`](audit-reprise.md).
> Les questions auxquelles le code ou le dump de prod répondent ont été retirées ; voir la section « Déjà résolu » en fin de document.

---

## 1. Accès et passation (bloquant)

- [ ] Existe-t-il un **dépôt Git d'origine** (GitHub, GitLab, Bitbucket) avec l'historique ? On a reçu le code sans `.git`. Pourtant `qodana.yaml` mentionne un « CI/CD pipeline » : où tournait-il ?
- [ ] Quels **accès** faut-il nous transmettre ? Espace client OVH, FTP/SSH, phpMyAdmin, Spot-Hit, Google Cloud (clé Maps), noms de domaine.
- [ ] Où se trouve le **`.env` de production** ? Qui d'autre que toi en a une copie ? Quelles valeurs y ont **`APP_ENV`** et **`APP_DEBUG`** ? (Si `APP_ENV=local`, Telescope est chargé et tout est journalisé.)
- [ ] Le mot de passe MySQL de prod est dans `public/import.php` (audit §1). Peut-on le changer sans casser autre chose : un script, un outil, une autre appli qui l'utiliserait ?
- [ ] La clé API Spot-Hit est en dur dans `DispatchPlannedSms.php`. Peut-on la **régénérer** côté Spot-Hit sans impact ailleurs ?
- [ ] Le fichier `artisan` manquait dans ce qu'on a reçu. Manque-t-il **autre chose** ? Des fichiers présents sur le serveur mais pas dans l'archive ?

## 2. Serveur

- [ ] Quelle **offre d'hébergement OVH** exactement (Perso, Pro, Performance, VPS) ? Le **SSH** est-il disponible ?
- [ ] Quelle **version de PHP** tourne réellement pour le site ? `.ovhconfig` dit 8.3, `qodana.yaml` dit 8.2, phpMyAdmin tourne en 8.4.
- [ ] Le dossier racine du site pointe-t-il bien sur **`public/`** ? Ou sur la racine du projet, ce qui exposerait `.env`, `vendor/`, etc. ?
- [ ] Y a-t-il **plusieurs environnements** (prod, préprod/staging), ou seulement la prod ?
- [ ] Consultes-tu les **logs** de prod de temps en temps ? Y a-t-il des erreurs récurrentes connues ?
- [ ] Y a-t-il des **sauvegardes** de la base (OVH automatiques, manuelles) ? Où, et à quelle fréquence ?
- [ ] Quel est le **domaine** de prod ? Le HTTPS est-il géré par OVH (Let's Encrypt) ?

## 3. Déploiement

- [ ] Comment se fait une **mise en production**, étape par étape ? FTP à la main, Git pull, outil ?
- [ ] `composer install` est-il lancé **sur le serveur**, ou `vendor/` (et `bootstrap/cache/`) sont-ils envoyés par FTP ? Avec ou sans `--no-dev` ?
- [ ] Les **assets front** (`public/build`) sont-ils compilés en local puis envoyés ? Avec **npm ou pnpm** ? Les deux lock files existent (audit §8).
- [ ] Les **migrations** sont-elles lancées via `public/migration.php` (audit §2) ? La table `migrations` de prod montre seulement 3 lots (le dernier : `2026_07_29_100308_add_dates_to_parc_auto`), donc c'est fait à la main, occasionnellement.
- [ ] `public/clear.php` **vide** les caches mais n'en génère aucun. Les caches (`config:cache`, `route:cache`, `view:cache`) sont-ils générés en prod d'une autre façon, ou jamais ?
- [ ] Combien de temps le site est-il coupé pendant un déploiement ? Y a-t-il un mode maintenance ?

## 4. Tâches automatiques et SMS

- [ ] Qu'est-ce qui appelle **`launchSMS.php`** ? Le code n'envoie quelque chose qu'à **8h** (jours ouvrés) et **20h** (vendredi, dernier jour ouvré du mois), donc il est forcément appelé au moins toutes les heures. Un cron OVH qui appelle l'URL ?
- [ ] Qui reçoit les **3 SMS** (rapport de mapping de 8h, résultats du vendredi soir, résultats de fin de mois) ? Les numéros sont **en dur** dans le code (audit §4). Qui doit pouvoir les changer ?
- [ ] Pourquoi **pas d'envoi en août** (`if ($date->month == 8) return;`) ? C'est une règle métier, ou un bricolage ponctuel ?
- [ ] Le rapport de 8h **exclut le secteur 10** (`in_array($secteur->id, [10])`). Pourquoi ?

## 5. Base de données

> ✅ **Confirmé :** l'appli Laravel est une réécriture d'une ancienne appli PHP. Les 22 migrations datées du `2025_05_22_132422` ont été générées à partir de la base existante (`reliese/laravel`).

> ✅ **Confirmé :** l'ancienne appli ne tourne plus et ne partage pas la base. Seule l'appli Laravel utilise la base de prod.

- [ ] Le code de l'ancienne appli est-il **archivé quelque part**, au cas où on aurait besoin de comprendre une règle métier d'origine ?
- [ ] La procédure stockée **`remap_clients`** (réaffecte les clients à un secteur selon leurs coordonnées GPS) existe en prod mais **n'est appelée nulle part dans le code**. Qui la lance, quand, et pourquoi ?
- [ ] La migration `database/migrations/after/2025_10_12_141918_delete_source_field_in_client.php` (suppression de `clients.source`) **n'a pas été appliquée en prod** : la colonne existe toujours dans le dump. Pourquoi l'avoir mise de côté ? Faut-il l'appliquer un jour ?
- [ ] Y a-t-il des modifications faites **directement en base** (phpMyAdmin) qui n'existent pas en migration ?

## 6. Métier et utilisateurs

> Les droits techniques de chaque rôle se lisent dans `User::hasRole()` et `User::getAuthorizedPages()`. Les questions portent sur le **sens métier**, pas sur le code.

- [ ] À quoi correspond chaque **rôle** dans l'entreprise : `admin`, `directeur`, `commercial`, `resp-prosp`, `telepro`, `administratif`, `metreur`, `responsable` ? Les rôles `metreur` et `responsable` sont-ils encore attribués ?
- [ ] Les drapeaux `directeur` et `telepro` donnent les droits du rôle correspondant en plus de la colonne `role`. Dans quels cas réels un utilisateur cumule-t-il (ex. un commercial avec `directeur = 1`) ? Que représentent `junior`, `prospecteur` et `type` (ils ne sont pas utilisés pour les droits) ?
- [ ] L'utilisateur **id 1** a un accès complet codé en dur, et les SMS calculent les chiffres en son nom. Qui est-ce ?
- [ ] Quel est le **parcours principal** d'un commercial et d'une télépro au quotidien ? Quelles pages sont les plus utilisées ?
- [ ] **Secteurs, commissions, tableau des chiffres** : le calcul se lit dans le code (`TableauChiffreService`, `VentesCommissions`). Y a-t-il des **règles ou exceptions** appliquées à la main, hors de l'appli, ou des cas que le code gère mal ?

## 7. Bugs et dette connus

- [ ] Ces pages plantent en local avec les données de prod (audit §13). Plantent-elles **aussi en prod** ?
  - `/commissions` : `Ventes::loadData does not exist`
  - `/gestion-commerciaux` : `Undefined variable $u`
  - `/rattrapage` : `Redirector could not be converted to int`
  - `/tableau-chiffres` : `Undefined array key ""`
- [ ] Y a-t-il des **bugs connus**, des fonctionnalités à moitié finies, ou des demandes client en attente ?
- [ ] Y a-t-il des parties du code dont tu n'étais **pas satisfait**, ou que tu aurais refaites ?

---

## Déjà résolu (réponses trouvées dans le code ou le dump)

| Question retirée | Réponse |
|---|---|
| Expéditeur des SMS (`services.spothit.from` vaut `null`) | Sans objet : `$sender` n'est jamais utilisé, l'envoi met `'expediteur' => 'Homiso'` en dur. (Audit §5 corrigé.) |
| Le paquet `ovh/ovh` sert-il encore ? | Non : aucun usage, seulement des lignes commentées. Retiré (audit §12). |
| Le scheduler (`schedule:run`) est-il branché ? | Sans importance : la seule tâche est `telescope:prune`, et Telescope n'est chargé qu'en `APP_ENV=local` (`AppServiceProvider`). |
| Un worker de file (`queue:work`) tourne-t-il ? | Inutile : aucun job n'est mis en file dans le code (les `dispatch()` sont des événements Livewire). |
| Colonnes héritées de `users` (`activation_hash`, `failed_logins`...) | Inutilisées : elles n'apparaissent que dans `$fillable` / `$casts` du modèle. |
| Les mots de passe ont-ils été migrés ? (`$2y$10$` et `$2y$12$`) | Les deux sont du bcrypt, que Laravel vérifie nativement. Il ré-encode en coût 12 à la connexion, donc les `$2y$10$` sont des comptes qui ne se sont pas reconnectés depuis la migration. |
| Les modèles sont-ils régénérés avec reliese ? | Non, en pratique : ils contiennent du code écrit à la main (`Searchable`, relations, méthodes) et `base_files` vaut `false`. Une régénération écraserait tout. On peut les modifier à la main. |
| Le dossier `after/` a-t-il été appliqué en prod ? | Non : `clients.source` existe toujours dans le dump (la question restante porte sur le pourquoi, §5). |
| `_TableauChiffres.php` et `old_Map.php` peuvent-ils être supprimés ? | Oui : aucune référence ailleurs dans le code. |
| `yoeunes/toastr` ou `php-flasher` ? | `toastr()` vient de `php-flasher/flasher-toastr`. `yoeunes/toastr` v3 n'est qu'un ancien paquet enveloppe, non découvert par Laravel. Retiré (audit §12). |
| Telescope est-il accessible en prod sur `/telescope` ? | Non, si `APP_ENV` ≠ `local` : le provider n'est pas enregistré, et la gate (liste d'e-mails vide) refuserait de toute façon tout le monde. La vraie question devient la valeur d'`APP_ENV` (§1). |
| `bootstrap/cache/` envoyé avec des paquets de dev ? | L'archive reçue ne contenait que `bootstrap/cache/.gitignore`. La question est intégrée à celle sur `composer install` (§3). |
