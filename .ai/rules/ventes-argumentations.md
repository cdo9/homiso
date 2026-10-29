---
paths:
  - app/Livewire/EditArgumentation.php
  - app/Livewire/RendezVousForm.php
  - app/Livewire/VentesCommissions/**
  - app/Models/Vente.php
  - app/Models/RendezVous.php
  - app/Models/Projet.php
---

# Ventes et argumentations

## Un seul rendez-vous / argumentation par client et par date
Création refusée s'il existe déjà un rendez-vous (ou une argumentation) pour le même client à la même date. Contrôle uniquement à la création, pas à la mise à jour. Voir `RendezVousForm` et `EditArgumentation`.

## Répartition entre commerciaux
Une vente peut être répartie entre jusqu'à 3 commerciaux (`repartition_1..3`) ; la somme doit faire exactement 100 %.

## Vente partagée = actions groupées
Les lignes `ventes` d'un même rendez-vous (`id_rdv`) forment une seule vente : changer le statut, la commande, la commission ou une attente s'applique à toutes les lignes du `id_rdv`.

## Statuts de vente
`ventes.statut` : 2 = confirmée, 3 = annulée.

## Commission activée
Activer la commission d'une vente fixe `date_commission` à la date du jour et `commission = 1` ; la désactiver remet `date_commission = null` et `commission = 0`.

## Prix devis vs prix tarif (À confirmer)
`EditArgumentation` bloque l'enregistrement si `devis_total_ht > tarif_total_ht`, mais le message affiché dit « Le prix devis ne peut pas être inférieur au prix tarif ». Le code et le message se contredisent : faire trancher le client avant toute modification.
