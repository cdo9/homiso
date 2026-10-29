---
paths:
  - app/Models/User.php
  - app/Http/Middleware/**
  - app/Livewire/**
  - routes/web.php
---

# Accès et droits

## Hiérarchie des rôles
`User::hasRole()` : `admin` valide `directeur` et `commercial` ; `directeur` (ou `users.directeur = 1`) valide `commercial`. `resp-prosp` et `users.telepro = 1` valident `telepro`. Détail complet : `docs/pages-et-acces.md`.
Test : `tests/Feature/Regles/AccesDroits/HierarchieDesRolesTest.php`

## Pages autorisées par rôle
La liste des pages accessibles par rôle est dans `User::getAuthorizedPages()`. Toute nouvelle page doit y être ajoutée explicitement pour chaque rôle concerné. Le rôle `administratif` n'a que `ventes`, `argumentations`, `rendez-vous`, `poses` : il n'a pas les pages « toujours accessibles » (`rattrapage`…).
Test : `tests/Feature/Regles/AccesDroits/PagesAutoriseesParRoleTest.php`

## Édition d'un contact ou d'une argumentation
Un utilisateur qui n'est ni `directeur` ni `admin` ne peut éditer un client (ou l'argumentation de ce client) que s'il en est l'auteur (`clients.rentre_par`) ou s'il lui a été partagé (`clients.partage_avec`). Sinon 403. Voir `EditContact::checkPermissions()` et `EditArgumentation::checkPermissions()`.
Test : `tests/Feature/Regles/AccesDroits/EditionContactArgumentationTest.php`

## Modification d'une vente
Valider/annuler une vente, basculer le statut commande ou commission : réservé au commercial de la vente (`ventes.id_commercial`) et aux directeurs. Lever une « attente » sur une vente : admin uniquement. Voir `VentesCommissions/Ventes.php`.
Test : `tests/Feature/Regles/AccesDroits/ModificationVenteTest.php`

## Confidentialité des commissions
Un utilisateur qui n'est ni admin ni directeur ne voit que ses propres montants de commission ; ceux des autres sont masqués (`CommissionService::shouldMaskCommission()`).
Test : `tests/Feature/Regles/AccesDroits/ConfidentialiteCommissionsTest.php`

## Blocages
Un utilisateur bloqué (`Blocages::hasBlocageQuelconque()`), hors admin, est redirigé vers `rattrapage` ; seules `rattrapage`, `nouveau-contact`, `confirmations-de-rendez-vous`, `confirmations`, `logout`, `welcome` restent accessibles. Un déblocage (`users.released`) n'est valable que le jour même. Voir `CheckBlocagesAccess`.
Test : `tests/Feature/Regles/AccesDroits/BlocagesTest.php`

## Utilisateur id = 1 (À confirmer)
L'utilisateur `id = 1` a accès à toutes les pages, codé en dur dans `getAuthorizedPages()`. Hérité de l'ancien code.
Test : `tests/Feature/Regles/AccesDroits/UtilisateurId1Test.php`
