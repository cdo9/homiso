---
paths:
  - '**'
---

# Règles métier : procédure

## Vérifier les règles métier à chaque modification
Avant de modifier du code, lire les fichiers de `.ai/rules` qui couvrent les fichiers touchés ET ceux du même domaine métier (une règle de commission peut être implémentée dans un service, un composant Livewire et une vue Blade).
Si la modification contredit une règle : ne pas la contourner, s'arrêter et demander validation. Si elle change volontairement une règle, mettre à jour la règle dans le même commit.
Chaque règle a son test dans `tests/Feature/Regles/` (référencé sous la règle). Avant de terminer une tâche, lancer `vendor/bin/sail artisan test --compact tests/Feature/Regles tests/Unit/ArchTest.php` : un test en échec = une règle cassée. Ne jamais modifier un test de règle pour le faire passer sans validation explicite.
Toute nouvelle règle enregistrée doit être accompagnée de son test.
En fin de tâche, indiquer quelles règles ont été vérifiées et si l'une d'elles a été modifiée.

## Règles marquées « À confirmer »
Comportement constaté dans le code hérité, pas encore validé par le client. Ne pas le modifier sans validation, mais signaler toute incohérence.
