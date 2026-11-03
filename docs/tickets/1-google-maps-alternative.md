# Ticket #1 : Passer de Google Maps à une alternative gratuite

> Branche : `feature/1-google-maps-alternative`
> Estimation : 4 à 5 jours

## Description

Remplacer Google Maps (payant) par des services gratuits :
- **Leaflet** pour la carte
- **IGN Géoplateforme** pour les fonds de carte (plan + satellite)
- **API Adresse (BAN)** pour l'autocomplétion et le géocodage

**Résultat attendu :** plus aucun appel à l'API Google, et la clé API est supprimée. Tout le reste fonctionne comme avant : vue satellite, création de client depuis la carte, remplissage de l'adresse et des coordonnées GPS, utilisation sur mobile.

## Usages de Google à remplacer

| Endroit | Usage Google | Statut |
|---|---|---|
| `/nouveau-contact` (`contact/nouveau-contact.blade.php`) | Widget `places.Autocomplete` | ✅ Remplacé par `<x-address-search>` |
| `/map` : champ « Rechercher une adresse » dans la barre de filtres | `places.SearchBox` | ⏳ À faire |
| `/map` : clic sur le marqueur « Nouveau client » | `Geocoder` (géocodage inverse) | ⏳ À faire |
| `/map` : modification de l'adresse dans le formulaire | `Geocoder` | ⏳ À faire |
| `/map` : carte, marqueurs, popups, zones | Maps JavaScript API | ⏳ À faire (Leaflet + IGN) |
| `app/Livewire/EditContact.php` | Places API côté serveur | ❓ Probablement du code mort (route `relances.contact.edit` commentée) : à confirmer |
| `config/services.php`, `.env.example` | Clé `GOOGLE_MAPS_API_KEY` | ⏳ À supprimer en fin de ticket |

Les liens Google Calendar (`Poses`, `RendezVous`…) ne sont pas concernés : ils n'utilisent pas l'API Maps.

## Fait

- Composant réutilisable de recherche d'adresse `<x-address-search>` : un seul fichier, `resources/views/components/address-search.blade.php` (HTML + script `@once`). Les props `*Field` sont les id des champs à remplir, compatible `wire:model`. Il émet un événement `address-selected` (lat, lng) pour recentrer une carte.
- Livewire et Alpine.js chargés sur toutes les pages (`@livewireStyles` / `@livewireScripts` dans `layouts/app.blade.php`).
- `/nouveau-contact` : branché sur `<x-address-search>`, vue nettoyée (classes factorisées, `@selected`, logique regroupée, code mort retiré).
- Test : `tests/Feature/NouveauContactTest.php` (rendu en création et en édition).

Changements de comportement apportés par le nettoyage de `/nouveau-contact` :
- La **civilité** saisie n'est plus perdue quand le formulaire d'un client existant est refusé à la validation.
- Un client **sans habitude d'achat** n'affiche plus « Autre » sélectionné, mais « Choisir… ».

## Décisions en attente

- `EditContact` : page encore utilisée ou non ?
- Validation de Leaflet comme dépendance front.

## Reste à faire

- [ ] `/map` : remplacer la SearchBox par `<x-address-search>` (écouter `address-selected` pour recentrer la carte et poser le marqueur)
- [ ] `/map` : remplacer le `Geocoder` par l'API Adresse (`/search` et `/reverse`)
- [ ] `/map` : passer la carte sur Leaflet + IGN (marqueurs, popups, formulaire « Nouveau client », zones, chargement des points au déplacement, préférences `mapType`)
- [ ] `/map` : rapatrier les icônes `maps.google.com/mapfiles/` dans `public/`
- [ ] `/map` : supprimer le champ inutilisé `#pac-input`
- [ ] Décider du sort d'`EditContact` (supprimer ou remettre en service avec `<x-address-search>`)
- [ ] Supprimer `GOOGLE_MAPS_API_KEY` de `config/services.php` et `.env.example`, puis désactiver la clé dans Google Cloud Console

## Points d'attention

- Leaflet est une nouvelle dépendance front (à valider).
- Le formulaire dans la popup de la carte est le passage le plus délicat à reproduire.
- Les préférences enregistrées (`mapType` = `hybrid`, `roadmap`…) doivent être converties vers les couches Leaflet.
- Le formulaire « Nouveau client » de `/map` enregistre via `ClientController::storeNouveauContact` (AJAX), comme `/nouveau-contact`, avec le contrôle de distance GPS (`user_lat`, `user_lng`, `user_acc`), les alertes et la détection de doublon. Il faut garder ces champs lors de la migration.

## Hors ticket : à créer en tickets séparés

- **Sécuriser l'édition d'un contact** (prioritaire) : `/nouveau-contact?client_id=X` permet à n'importe quel commercial de modifier n'importe quel client, ce qui contredit la règle « Édition d'un contact » (`.ai/rules/acces-droits.md`), pourtant appliquée dans `EditContact`. La corriger change ce que voient certains utilisateurs : à valider.
- **Refonte de « Nouveau contact » en Livewire** : extraire d'abord la logique d'enregistrement de `storeNouveauContact` dans une classe commune (partagée avec `/map`), puis migrer la page. Pas fait dans ce ticket.
- **Renommage progressif en anglais** (vues, routes, modèles, BDD), par lots : vues et classes d'abord, routes ensuite (listes de pages par rôle dans `User::getAuthorizedPages()`, redirections), tables et colonnes en dernier. Prévoir un glossaire métier avant (Client/Customer, RendezVous/Appointment, Pose/Installation…).
