<?php

namespace App\Services;

use App\Models\User;

class CommissionService
{
    /**
     * Compute monthly commission rates (standard and RAI) for a user based on totals.
     * This is a simplified, configurable logic that can be adapted later.
     *
     * @return array [tauxStandard, tauxRAI]
     */
    public static function calculTauxCommissionMensuel(?string $contrat, float $prix_vente, float $prix_tarif): array
    {
        $taux = 0.0;  // Taux normal
        $tauxRAI = 0.0; // Taux ventes "ravalement avec isolation"

        // Aucune donnée tarif => taux 0
        if ($prix_tarif <= 0) return [0.0, 0.0];

        // Calcul de la remise en % selon legacy
        $remise = self::calculRemise($prix_vente, $prix_tarif);

        switch ($contrat) {
            case 'telepro-v3':
                if     ($prix_vente < 10000) $taux = 0.5;
                elseif ($prix_vente < 15000) $taux = 1.0;
                elseif ($prix_vente < 20000) $taux = 2.0;
                else                         $taux = 3.0;
                break;

            case 'telepro-v2':
            case 'telepro-v5':
            case 'telepro-v6':
                $taux = 10.0;
                if ($prix_vente >= 30000) $taux = 11.0;
                break;

            case 'telepro-v1':
            case 'telepro-v4':
                if     ($prix_vente < 10000) $taux = 1.0;
                elseif ($prix_vente < 15000) $taux = 2.0;
                elseif ($prix_vente < 20000) $taux = 4.0;
                else                         $taux = 6.0;
                break;

            case 'directeur-v1':
                $taux = 3.0;
                break;

            case 'directeur-v2':
            case 'directeur-v3':
                $taux = 9.0;
                if ($prix_vente >= 30000) $taux = 10.0;

                if     ($remise >= 30) $taux -= 9;
                elseif ($remise >= 29) $taux -= 8;
                elseif ($remise >= 27) $taux -= 7;
                elseif ($remise >= 25) $taux -= 6;
                elseif ($remise >= 23) $taux -= 5;
                elseif ($remise >= 21) $taux -= 4;
                elseif ($remise >= 19) $taux -= 3;
                elseif ($remise >= 17) $taux -= 2;
                elseif ($remise >= 15) $taux -= 1;

                $tauxRAI = 6.0;
                if ($prix_vente >= 30000) $tauxRAI = 7.0;

                if     ($remise >= 31) $tauxRAI -= 7;
                elseif ($remise >= 30) $tauxRAI -= 6;
                elseif ($remise >= 27) $tauxRAI -= 5;
                elseif ($remise >= 24) $tauxRAI -= 4;
                elseif ($remise >= 21) $tauxRAI -= 3;
                elseif ($remise >= 18) $tauxRAI -= 2;
                elseif ($remise >= 15) $tauxRAI -= 1;
                $tauxRAI = max(0.0, $tauxRAI);
                break;

            case 'commercial-v2':
                if     ($prix_vente >= 95000) $taux = 7.0;
                elseif ($prix_vente >= 60000) $taux = 6.0;
                elseif ($prix_vente >= 50000) $taux = 5.0;
                elseif ($prix_vente >= 45000) $taux = 4.0;
                elseif ($prix_vente >= 40000) $taux = 3.0;
                elseif ($prix_vente >= 35000) $taux = 2.0;
                elseif ($prix_vente >= 30000) $taux = 1.0;
                else                           $taux = 0.0;

                if     ($remise <= 15) $decote = 0.0;
                elseif ($remise <= 17) $decote = 0.1;
                elseif ($remise <= 19) $decote = 0.2;
                elseif ($remise <= 21) $decote = 0.3;
                elseif ($remise <= 22) $decote = 0.4;
                elseif ($remise <= 24) $decote = 0.5;
                elseif ($remise <= 25) $decote = 0.6;
                elseif ($remise <= 27) $decote = 0.7;
                elseif ($remise <= 28) $decote = 0.8;
                elseif ($remise <= 29) $decote = 0.9;
                else                    $decote = 1.0;

                $taux *= (1 - $decote);
                break;

            case 'commercial-v1':
            case 'commercial-v3':
            case 'commercial-v4':
            case 'commercial-v5':
            case 'commercial-v6':
            case 'commercial-v7':
            case 'resp-prosp':
                $taux = 10.0;
                if ($prix_vente >= 30000) $taux = 11.0;

                if     ($remise >= 31) $taux -= 11;
                elseif ($remise >= 30) $taux -= 10;
                elseif ($remise >= 29) $taux -= 9;
                elseif ($remise >= 28) $taux -= 8;
                elseif ($remise >= 27) $taux -= 7;
                elseif ($remise >= 25) $taux -= 6;
                elseif ($remise >= 23) $taux -= 5;
                elseif ($remise >= 21) $taux -= 4;
                elseif ($remise >= 19) $taux -= 3;
                elseif ($remise >= 17) $taux -= 2;
                elseif ($remise >= 15) $taux -= 1;
                $taux = max(0.0, $taux);

                $tauxRAI = 6.0;
                if ($prix_vente >= 30000) $tauxRAI = 7.0;

                if     ($remise >= 31) $tauxRAI -= 7;
                elseif ($remise >= 30) $tauxRAI -= 6;
                elseif ($remise >= 27) $tauxRAI -= 5;
                elseif ($remise >= 24) $tauxRAI -= 4;
                elseif ($remise >= 21) $tauxRAI -= 3;
                elseif ($remise >= 18) $tauxRAI -= 2;
                elseif ($remise >= 15) $tauxRAI -= 1;
                $tauxRAI = max(0.0, $tauxRAI);
                break;

            case 'idr-commercial':
                $taux = 11.0;
                break;
            case 'idr-senior':
                $taux = 14.0;
                break;
            case 'idr-directeur':
                $taux = 7.0;
                if ($prix_vente > 20000) $taux = 9.0;
                break;

            default:
                // Unknown contract: keep 0
                break;
        }

        // Si aucun taux RAI particulier, tauxRAI = taux
        if ($tauxRAI == 0.0) $tauxRAI = $taux;
        return [round($taux, 2), round($tauxRAI, 2)];
    }

    /**
     * Compute commission amount for a given sale piece.
     */
    public static function calculMontantCommission(?string $contrat, float $caPart, float $caTotal, string $source, float $taux): float
    {
        $contrat = (string)$contrat;
        $source = (string)$source;

        // Legacy homiso rules: passive sources => 70% of CA base
        if (self::isSourcePassive($source)) {
            $caPart *= 0.7;
        }

        $amount = 0.0;

        // Directors, telepros, resp-prosp: always percentage of the (possibly reduced) CA part
        if (in_array($contrat, ['resp-prosp'], true)
            || str_contains($contrat, 'telepro')
            || in_array($contrat, ['directeur-v1', 'directeur-v2', 'directeur-v3'], true)) {
            $amount = ($taux / 100.0) * $caPart;
        }
        // Commercials: special exceptions on source
        elseif (str_contains($contrat, 'commercial')) {
            // If parrainage télépro or leads, use total sale amount for base
            if ($source == 17 /*Parrainage Télépro*/ || $source == 18 /* leads */) {
                $amount = ($taux / 100.0) * $caTotal;
            } else {
                $amount = ($taux / 100.0) * $caPart;
            }
        } else {
            // Default behavior if contract not recognized
            $amount = ($taux / 100.0) * $caPart;
        }

        // Deduct 100€ for sources including 'apporteur' (apporteur d'affaire)
        if ($source == 23 /* Apporteur */) {
            $amount -= 100.0;
        }

        return round($amount, 2);
    }

    /**
     * Optional monthly adjustment hook.
     */
    public static function updateMontantCommissionMensuel(?string $contrat, float $montant, float $taux): float
    {
        $c = (string)$contrat;
        switch ($c) {
            case 'commercial-v3':
                $montant = max(0.0, $montant - 1800);
                break;
            case 'commercial-v5':
                $montant = max(0.0, $montant - 2000);
                break;
            case 'commercial-v4':
                $montant = max(0.0, $montant - 2400);
                break;
            case 'commercial-v6':
                $montant = max(0.0, $montant - 2600);
                break;
            case 'commercial-v7':
                $montant = max(0.0, $montant - 2100);
                break;
            case 'directeur-v3':
                $montant = max(0.0, $montant - 2100);
                break;
            case 'telepro-v5':
                $montant = max(0.0, $montant - 1700);
                break;
            case 'telepro-v6':
                $montant = max(0.0, $montant - 2000);
                break;
            case 'idr-directeur':
                $montant = $montant + 1500;
                break;
            default:
                // no monthly adjustment
                break;
        }
        return round($montant, 2);
    }

    public static function calculMarge(float $caTotalHt, float $tarifTotalHt): float
    {
        // Legacy: marge = CA total - (Tarif total * 0.85)
        return round($caTotalHt - ($tarifTotalHt * 0.85), 2);
    }

    public static function calculRemise(float $ca, float $tarif): float
    {
        if ($tarif <= 0) return 0.0;
        $discount = 1.0 - ($ca / $tarif);
        return round(max(0.0, $discount) * 100.0, 2);
    }

    /**
     * Determine if a sale is considered RAI based on rendez-vous produits list.
     * RAI = Ravalement Avec Isolation (includes ITE and other isolation products)
     */
    public static function isVenteRAI(?string $produits): bool
    {
        if (!$produits) return false;

        $haystack = is_array($produits) ? implode(';', $produits) : (string)$produits;
        $haystack = strtoupper($haystack);

        // RAI product patterns based on legacy logic
        $raiPatterns = [
            /*'ITE_', // Isolation Thermique Extérieure
            'RAVALEMENT',
            'ISOLATION',*/
            'RAVALEMENT-AVEC-ISOLATION',
            'RPE'
        ];

        foreach ($raiPatterns as $pattern) {
            if (stripos($haystack, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine passive source (reduced base for commission) using legacy mapping.
     */
    public static function isSourcePassive(?string $source): bool
    {
        if (!$source) return false;
        $map = SourceService::createSourceLst();
        $idsPassif = array_keys($map['passif']);
        $idsTelepro = array_keys($map['télépro']); 
        $allIds = array_merge($idsPassif, $idsTelepro);

        return in_array((int)$source, $allIds, true);
    }

    /**
     * Flatten the ProjectService priorities list to key=>label mapping.
     */
    public static function listPrioritiesFlat(): array
    {
        $flat = [];
        $groups = ProjectService::listPriorities();
        foreach ($groups as $group => $items) {
            foreach ($items as $key => $label) {
                $flat[$key] = $label;
            }
        }
        return $flat;
    }

    /**
     * Should mask commission data for the current user.
     * If user is not admin/directeur and it is not their own line, hide.
     */
    public static function shouldMaskCommission(User $currentUser, ?int $commercialId, string $dimension = 'montant'): bool
    {
        if ($currentUser->hasRole('admin') || $currentUser->hasRole('directeur')) return false;
        if ($commercialId && $currentUser->id === $commercialId) return false;
        return true;
    }

    /**
     * Helper to compute per-vente share values if blank.
     */
    public static function computeShare(float $total, int $repartition): float
    {
        return round(($total * max(0, $repartition)) / 100.0, 2);
    }
}
