---
paths:
  - app/Console/Commands/DispatchPlannedSms.php
---

# SMS automatiques

## Pas d'envoi en août
Aucun SMS planifié n'est envoyé pendant le mois d'août.
Test : `tests/Feature/Regles/Sms/PasEnvoiEnAoutTest.php`

## Créneaux d'envoi
SMS envoyés le vendredi soir, le dernier jour ouvré du mois et à 8 h les jours ouvrés (récapitulatifs). Expéditeur : « Homiso ».
Test : `tests/Feature/Regles/Sms/CreneauxEnvoiTest.php`
