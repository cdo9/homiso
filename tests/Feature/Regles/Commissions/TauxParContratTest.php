<?php

use App\Services\CommissionService;

it('applies the rate grid of each contract', function (string $contrat, float $prixVente, float $prixTarif, array $expectedRates) {
    expect(CommissionService::calculTauxCommissionMensuel($contrat, $prixVente, $prixTarif))->toBe($expectedRates);
})->with([
    'telepro-v3 between 10k and 15k' => ['telepro-v3', 12000, 12000, [1.0, 1.0]],
    'telepro-v1 from 20k' => ['telepro-v1', 25000, 25000, [6.0, 6.0]],
    'telepro-v2 from 30k' => ['telepro-v2', 30000, 30000, [11.0, 11.0]],
    'directeur-v1 flat rate' => ['directeur-v1', 50000, 50000, [3.0, 3.0]],
    'directeur-v2 without discount' => ['directeur-v2', 20000, 20000, [9.0, 6.0]],
    'commercial-v1 without discount' => ['commercial-v1', 20000, 20000, [10.0, 6.0]],
    'commercial-v1 from 30k with 20% discount' => ['commercial-v1', 32000, 40000, [8.0, 5.0]],
    'commercial-v1 with 40% discount' => ['commercial-v1', 30000, 50000, [0.0, 0.0]],
    'commercial-v2 with 20% discount' => ['commercial-v2', 50000, 62500, [3.5, 3.5]],
    'idr-senior flat rate' => ['idr-senior', 10000, 10000, [14.0, 14.0]],
    'idr-directeur above 20k' => ['idr-directeur', 25000, 25000, [9.0, 9.0]],
    'unknown contract' => ['inconnu', 20000, 20000, [0.0, 0.0]],
]);

it('returns no rate when there is no tarif', function () {
    expect(CommissionService::calculTauxCommissionMensuel('commercial-v1', 20000, 0))->toBe([0.0, 0.0]);
});

it('identifies RAI sales from their products', function (string $produits, bool $isRai) {
    expect(CommissionService::isVenteRAI($produits))->toBe($isRai);
})->with([
    'ravalement avec isolation' => ['RAVALEMENT-AVEC-ISOLATION;PAC', true],
    'RPE' => ['RPE', true],
    'other products' => ['PAC;ITE_MUR', false],
]);
