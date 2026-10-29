---
paths:
  - app/Services/CommissionService.php
  - app/Services/TableauChiffreService.php
  - app/Livewire/VentesCommissions/**
  - app/Livewire/Stats/**
  - app/Livewire/TableauChiffre/**
  - app/Livewire/EditArgumentation.php
  - resources/views/livewire/ventes-commissions/**
---

# Commissions et calculs

## Source unique de calcul
Les formules sont dans `CommissionService`. Une partie est dupliquée dans `VentesCommissions/Ventes.php` et `vente.blade.php` (« legacy logic ») : toute modification d'une formule doit être reportée partout, ou mieux, la duplication remplacée par un appel au service.

## Marge et remise
Marge = CA HT − (tarif HT × 0,85). Remise (%) = (1 − CA / tarif) × 100, jamais négative, 0 si tarif ≤ 0. Valeurs arrondies puis castées en entier pour l'affichage.
Test : `tests/Feature/Regles/Commissions/MargeEtRemiseTest.php`

## Taux de commission par contrat
Le taux dépend de `users.contrat` (`telepro-v1..v6`, `commercial-v1..v7`, `directeur-v1..v3`, `resp-prosp`, `idr-*`), du prix de vente et de la remise (décote par paliers). Les ventes RAI (produits `RAVALEMENT-AVEC-ISOLATION` ou `RPE`) ont un taux distinct. Taux jamais négatif. Barèmes : `CommissionService::calculTauxCommissionMensuel()`.
Test : `tests/Feature/Regles/Commissions/TauxParContratTest.php`

## Base de calcul selon la source
Sources passives et télépro : base = 70 % du CA. Commercial avec source 17 (parrainage télépro) ou 18 (leads) : base = CA total de la vente. Source 23 (apporteur d'affaires) : −100 € sur la commission.
Test : `tests/Feature/Regles/Commissions/BaseSelonSourceTest.php`

## Ajustement mensuel par contrat
Déduction mensuelle fixe, plancher à 0 : `commercial-v3` −1800, `v4` −2400, `v5` −2000, `v6` −2600, `v7` −2100, `directeur-v3` −2100, `telepro-v5` −1700, `telepro-v6` −2000. `idr-directeur` : +1500.
Test : `tests/Feature/Regles/Commissions/AjustementMensuelTest.php`
