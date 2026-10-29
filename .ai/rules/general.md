---
paths:
  - '**'
---

# Règles métier : procédure

## Vérifier les règles métier à chaque modification
Avant de modifier du code, lire les fichiers de `.ai/rules` qui couvrent les fichiers touchés ET ceux du même domaine métier (une règle de commission peut être implémentée dans un service, un composant Livewire et une vue Blade).
Si la modification contredit une règle : ne pas la contourner, s'arrêter et demander validation. Si elle change volontairement une règle, mettre à jour la règle dans le même commit.
En fin de tâche, indiquer quelles règles ont été vérifiées et si l'une d'elles a été modifiée.

## Règles marquées « À confirmer »
Comportement constaté dans le code hérité, pas encore validé par le client. Ne pas le modifier sans validation, mais signaler toute incohérence.
