# Homiso — Pages du site, rôles et droits d'accès

> Document généré à partir du code (`routes/web.php`, `app/Http/Middleware`, `app/Models/User.php`, composants Livewire) et de la base de données (état au 27/10/2026).

---

## 1. Les rôles

Le rôle d'un utilisateur est stocké dans `users.role`. Trois drapeaux complètent ce rôle : `users.directeur`, `users.telepro` et `users.formateur`. Il existe aussi un rôle implicite, **responsable de groupe** : c'est un utilisateur dont l'id figure dans `groupes.id_responsable`.

| Rôle (`users.role`) | Rôle métier | Actifs | Inactifs |
|---|---|---|---|
| `admin` | Administrateur (accès complet) | 1 | 0 |
| `directeur` | Directeur commercial (gère un ou plusieurs secteurs) | 6 | 7 |
| `commercial` | Commercial / vendeur terrain | 12 | 116 |
| `resp-prosp` | Responsable de la téléprospection | 1 | 3 |
| `telepro` | Téléprospecteur | 8 | 20 |
| `administratif` | Service administratif (ventes, poses, consultation) | 1 | 0 |
| `metreur` | Métreur (prévu dans le code) | 0 | 0 |
| *(null)* | Comptes sans rôle | 0 | 2 |

### Hiérarchie des rôles (`User::hasRole()`)

Le middleware `role:` s'appuie sur `hasRole()`, et un rôle « supérieur » y valide aussi les rôles inférieurs :

| Rôle testé | Utilisateurs qui le valident |
|---|---|
| `admin` | `admin` |
| `directeur` | `admin`, `directeur`, ou n'importe quel utilisateur avec `directeur = 1` |
| `commercial` / `vendeur` | `admin`, `directeur`, `commercial` |
| `telepro` / `prospecteur` | `telepro`, `resp-prosp`, ou n'importe quel utilisateur avec `telepro = 1` |
| `resp-prosp` | `resp-prosp` |
| `administratif` | `administratif` |
| `metreur` | `metreur` |

### Les trois niveaux de contrôle

1. **Route** (middleware `role:` dans `routes/web.php`) : c'est le vrai contrôle d'accès. Un utilisateur non autorisé est redirigé vers l'accueil avec un message d'erreur.
2. **Menu** (`User::getAuthorizedPages()` + `canAccessPage()` dans `components/sidebar.blade.php`) : ce niveau décide seulement si le lien apparaît dans la barre latérale. Il ne bloque rien.
3. **Données** (dans chaque composant Livewire) : ce niveau filtre ce que l'utilisateur voit sur la page.
   - **Admin / administratif** : toutes les données.
   - **Directeur** : les données des secteurs qui lui sont rattachés (`secteur_commercials`, secteur choisi en session `selectedSecteur`).
   - **Responsable de groupe** : les données des commerciaux de son groupe (`groupes`).
   - **Commercial / télépro** : uniquement ses propres données (`clients.rentre_par` ou `clients.partage_avec`, `rendez_vous.id_commercial` ou `rendez_vous.partage_avec`, `ventes.id_commercial*`).

### Les blocages (middleware `blocages`)

Le middleware `CheckBlocagesAccess` s'applique à presque toutes les pages. Les admins n'y sont jamais soumis. Pour les autres, si un **blocage** est actif et que le compte n'a pas été débloqué aujourd'hui (`users.released`), l'utilisateur est renvoyé vers `/rattrapage`. Seules ces pages restent alors accessibles : `rattrapage`, `nouveau-contact`, `confirmations(-de-rendez-vous)`, `logout`, `welcome`.

Deux causes de blocage sont actives aujourd'hui :

- **Écarts RDV / argumentations** : il existe un RDV confirmé et passé (`projets`, depuis le 01/04/2026) qui n'a pas d'argumentation correspondante (`rendez_vous`) à la même date.
- **Indicateurs chantiers** : sur les poses terminées depuis plus de 15 jours (depuis le 01/01/2026), la visite chantier, l'avis client ou l'avis Facebook n'atteint pas 100 %.

Ces deux blocages ne concernent pas les télépros ni les prospecteurs. D'autres blocages existent dans le code mais sont commentés : RDV à confirmer, prospections, entretiens annuels, évaluations des nouveaux vendeurs.

---

## 2. Vocabulaire des données

| Table | Signification métier |
|---|---|
| `clients` | Contacts et clients (≈ 166 000). `type` : 1 = contact, 2 = client. Champs clés : `rentre_par` (qui l'a saisi), `partage_avec`, `secteur` |
| `projets` | **Rendez-vous planifiés** (prise de RDV, confirmation, annulation) |
| `rendez_vous` | **Argumentations**, c'est-à-dire le compte rendu d'un RDV effectué chez le client |
| `ventes` | Ventes signées (jusqu'à 3 commerciaux, financement, commissions) |
| `poses` / `pose_details` | Chantiers d'installation qui suivent une vente |
| `poseurs` | Équipes de pose |
| `relances` | Relances clients et argumentations |
| `challenges` | Challenges commerciaux |
| `objectifs`, `objectif_annuel_commercial` | Objectifs CA et RDV des commerciaux |
| `secteurs`, `secteur_commercials`, `groupes` | Organisation géographique et équipes |
| `parc_auto`, `parc_km` | Véhicules de société et relevés kilométriques et carburant |
| `alerts` | Alertes générées (par exemple à la création d'un contact depuis la carte) |
| `prospections*` | Prospection terrain par commune |
| `evaluations` | Évaluations des nouveaux vendeurs |
| `tracking`, `comments`, `clients_history` | Historique, commentaires, traçabilité |

---

## 3. Les pages

Légende : ✅ accès autorisé par la route, ❌ accès refusé. **(menu)** signifie que le lien apparaît dans la barre latérale.

### 3.1 Pages accessibles à tout utilisateur connecté

Middleware : `auth` + `blocages`, sans contrôle de rôle.

| URL | Nom de route | Rôle de la page |
|---|---|---|
| `/` et `/list-cards` | `welcome`, `list-cards` | **Tableau de bord d'accueil** sous forme de cartes : RDV à confirmer, relances à venir (30 jours), poses du jour, anniversaires clients, nouveaux arrivants, véhicules (échéances), alertes non lues. Le contenu dépend du rôle |
| `/nouveau-contact` | `nouveau-contact` | Saisie d'un nouveau contact (fiche client et foyer) **(menu)** |
| `/confirmations-de-rendez-vous` | `confirmations-de-rendez-vous` | Liste des RDV (`projets`) à confirmer ou annuler. Le commercial voit ses RDV, le directeur et l'admin voient tout **(menu)** |
| `/rattrapage` | `rattrapage` | Page affichée en cas de blocage : liste ce qu'il faut régulariser (argumentations manquantes, indicateurs chantiers) |
| `/dashboard` | `dashboard` | Page vide de test (« Bienvenue sur votre dashboard ») |
| `/logout` | `logout` | Déconnexion |

Ces routes ne demandent que `auth`, sans contrôle de rôle ni de blocage. Ce sont des appels AJAX ou des formulaires :

- `/map-points`, `/map-points-count`, `/getMarkerDetailContent` : données de la carte.
- `POST /formulaire-contact` : création d'un contact depuis la carte.
- `/clients/autocomplete` : autocomplétion des clients.

### 3.2 Pages opérationnelles

Route : `role:directeur,admin,commercial,resp-prosp,telepro,administratif`.

| URL | Rôle de la page | Admin | Directeur | Commercial | Resp-prosp / Télépro | Administratif |
|---|---|---|---|---|---|---|
| `/ventes` | **Ventes & commissions** : liste des ventes, modification (statut, financement), lien vers les commissions. Le commercial ne voit que ses ventes et ne peut modifier que les siennes. Certaines actions sont réservées à l'admin | ✅ menu | ✅ menu | ✅ menu | ✅ menu | ✅ menu |
| `/argumentations` | Liste des **argumentations** (comptes rendus de RDV) avec filtres par commercial et par période. Périmètre : tout (administratif), le secteur (directeur), le groupe (responsable) ou soi-même | ✅ menu | ✅ menu | ✅ menu | ✅ menu | ✅ menu |
| `/rendez-vous` | Liste des **RDV planifiés** (`projets`) avec le même découpage de périmètre | ✅ menu | ✅ menu | ✅ menu | ✅ menu | ✅ menu |
| `/poses` | Suivi des **chantiers de pose** : dates, poseur, indicateurs visite, avis client et avis Facebook | ✅ menu | ✅ menu | ✅ menu | ✅ menu | ✅ menu |

### 3.3 Pages commerciales et de prospection

Route : `role:directeur,admin,commercial,resp-prosp,telepro`. **L'administratif n'y a pas accès.**

| URL | Rôle de la page | Admin | Directeur | Commercial | Resp-prosp / Télépro |
|---|---|---|---|---|---|
| `/map` | **Carte** des contacts et clients (géolocalisation), création de contact depuis un point de la carte | ✅ menu | ✅ menu | ✅ menu | ✅ menu |
| `/nouveau-rendez-vous` | Prise d'un **nouveau RDV** pour un client (`projets`) | ✅ menu | ✅ menu | ✅ menu | ✅ menu |
| `/rendez-vous/{id}/edit` | Modification d'un RDV planifié | ✅ | ✅ | ✅ | ✅ |
| `/argumentation` (GET/POST) | Saisie d'une **nouvelle argumentation** après un RDV | ✅ menu | ✅ menu | ✅ menu | ✅ menu |
| `/relances` | **Relances** par onglets : contacts, clients, argumentations, financements, avec filtres par secteur et par dates | ✅ menu | ✅ menu | ✅ menu | ✅ menu |
| `/relances/argumentation/{rdvId}/edit` | Modification d'une argumentation depuis les relances | ✅ | ✅ | ✅ | ✅ |
| `/listing-client` | **Listing des clients et contacts** avec recherche et filtres. Le commercial ne voit que ses clients. Le directeur choisit un vendeur de son secteur. L'admin voit tout | ✅ menu | ✅ menu | ✅ menu | ✅ *(masqué du menu)* |
| `/clients/{id}`, `/clients/search` | Fiche client et recherche | ✅ | ✅ | ✅ | ✅ |
| `/tableau-chiffres` | **Tableau des chiffres** en trois onglets : *Chiffres* (CA, ventes, objectifs), *RDV*, *Argus* (argumentations). Le directeur et l'admin choisissent le commercial | ✅ menu | ✅ menu | ✅ menu | ✅ menu |
| `/classement` | **Classement des commerciaux** (CA, ventes) par mois et par année | ✅ menu | ✅ *(masqué du menu)* | ✅ *(masqué du menu)* | ✅ |
| `/challenges` | Liste des **challenges** commerciaux. Les boutons de gestion n'apparaissent que pour l'admin | ✅ menu | ✅ menu | ✅ menu | ✅ |
| `/stats-produit` | Statistiques par produit. Le directeur peut choisir le commercial (lien commenté dans le menu) | ✅ | ✅ | ✅ | ✅ |
| `/anniversaires` | Clients dont c'est l'anniversaire aujourd'hui (lien commenté dans le menu) | ✅ | ✅ | ✅ | ✅ |
| `/parametre` | **Paramètres du compte** (changement de mot de passe) | ✅ | ✅ | ✅ | ✅ |
| `POST /comments` | Ajout d'un commentaire sur un client ou un RDV | ✅ | ✅ | ✅ | ✅ |

### 3.4 Pages de direction

Route : `role:directeur,admin`. Elles sont aussi accessibles à tout utilisateur qui a `directeur = 1`.

| URL | Rôle de la page |
|---|---|
| `/commissions` | Calcul et affichage des **commissions** d'un commercial pour un mois donné (accès depuis la page Ventes) |
| `/blocages` | **Supervision des blocages** de chaque commercial (écarts RDV/argus, indicateurs chantiers) et déblocage ponctuel **(menu)** |
| `/poseurs`, `/poseurs/create`, `/poseurs/{id}/edit` | **Gestion des poseurs** (création, modification, archivage) **(menu)** |
| `/nouvelle-pose/{id?}` | Création ou modification d'une **pose** rattachée à une vente **(menu : admin uniquement)** |
| `/create-challenge`, `/formulaire-challenge`, `/delete-challenge/{id}` | Création, modification et suppression des **challenges** |
| `PUT /clients/{id}/update-type` | Passage d'un contact en client (ou l'inverse) |

### 3.5 Statistiques contacts

Route : `role:directeur,admin,resp-prosp`.

| URL | Rôle de la page |
|---|---|
| `/stats-contact` | **Statistiques de saisie de contacts** par utilisateur et par période, pour piloter la téléprospection **(menu)** |

### 3.6 Pages d'administration

Route : `role:admin`. Le middleware `blocages` ne s'y applique pas.

| URL | Rôle de la page |
|---|---|
| `/gestion-commerciaux` | **Gestion des utilisateurs** : création, activation et désactivation, rôle, secteurs, objectifs annuels, mot de passe, gestion des **groupes** et de leur responsable **(menu)** |
| `/secteurs-list` | Liste des **secteurs** et choix de leur couleur sur la carte **(menu)** |
| `/parc-auto` | **Parc automobile & carburant** : véhicules, attributions, relevés kilométriques **(menu)** |
| `/formulaire-auto-new`, `/modifier-vehicule/{id}`, `/supprimer-vehicule/{id}` | Création, modification et suppression d'un véhicule |
| `/alertes` | **Alertes** : consultation, marquage « vu » et suppression *(n'apparaît jamais dans le menu, voir §5)* |

---

## 4. Synthèse par rôle

| Page | Admin | Directeur | Commercial | Resp-prosp | Télépro | Administratif |
|---|:-:|:-:|:-:|:-:|:-:|:-:|
| Accueil, nouveau contact, confirmations RDV | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Ventes, argumentations, rendez-vous, poses | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Carte, nouveau RDV, nouvelle argumentation, relances | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| Listing clients, fiche client | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| Tableau des chiffres, classement, challenges | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| Stats contacts | ✅ | ✅ | ❌ | ✅ | ❌ | ❌ |
| Commissions, blocages, poseurs, poses (création), gestion des challenges | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| Gestion des utilisateurs, secteurs, parc auto, alertes | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |

Le rôle `metreur` n'apparaît dans aucun groupe de routes. Un métreur ne peut donc ouvrir que les pages de la §3.1, alors que le menu lui affiche « Ventes ».

---

## 5. Incohérences relevées

Ces points ont été relevés pendant l'analyse. Ils ne sont pas corrigés dans ce document.

1. **Routes AJAX sans authentification** :
   - `/ajax/commercials-by-secteur/{id}` renvoie la liste des commerciaux (nom, prénom, statut actif) à n'importe quel visiteur.
   - `/ajax/setSelectedSecteur/{id}` n'exige pas non plus d'être connecté.
2. **Le menu et les routes ne concordent pas** :
   - `/classement` est accessible aux directeurs et aux commerciaux mais masqué dans leur menu.
   - `/listing-client` est accessible aux télépros mais masqué.
   - `/alertes` n'est présente dans aucune liste de `getAuthorizedPages()`, donc même l'admin ne la voit pas dans le menu.
   - Le métreur voit « Ventes » dans le menu sans pouvoir l'ouvrir.
3. **Route `/confirmations` déclarée deux fois** : une fois sans rôle et une fois avec rôle. La seconde déclaration écrase la première, ce qui donne des règles différentes de `/confirmations-de-rendez-vous`, qui reste ouverte à tous.
4. **Middleware fantôme** : l'alias `page.access` pointe vers `App\Http\Middleware\PageAccessMiddleware`, une classe qui n'existe pas.
5. **Route parasite `/gestion.gestion-commerciaux`** : c'est un doublon de `/gestion-commerciaux` avec `blocages:ecarts,aconfirmer`, un paramètre que le middleware n'utilise pas.
6. **Faute de frappe dans `EditArgumentation`** : `hasRole('ateleprodmin')` renvoie toujours `false`. Le contrôle télépro de cette page ne fonctionne donc jamais.
7. **`/supprimer-vehicule/{id}` en POST** : la suppression se fait directement en SQL dans le fichier de routes, sans transaction.
8. **`/dashboard`** : page de démonstration qui n'est plus utilisée.
