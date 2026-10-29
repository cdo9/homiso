<?php

use App\Services\CommissionService;

it('applies the monthly adjustment of the contract', function (string $contrat, float $expectedAmount) {
    expect(CommissionService::updateMontantCommissionMensuel($contrat, 3000, 10))->toBe($expectedAmount);
})->with([
    'commercial-v3' => ['commercial-v3', 1200.0],
    'commercial-v4' => ['commercial-v4', 600.0],
    'commercial-v5' => ['commercial-v5', 1000.0],
    'commercial-v6' => ['commercial-v6', 400.0],
    'commercial-v7' => ['commercial-v7', 900.0],
    'directeur-v3' => ['directeur-v3', 900.0],
    'telepro-v5' => ['telepro-v5', 1300.0],
    'telepro-v6' => ['telepro-v6', 1000.0],
    'idr-directeur' => ['idr-directeur', 4500.0],
    'contract without adjustment' => ['commercial-v1', 3000.0],
]);

it('never lets the deduction make the commission negative', function () {
    expect(CommissionService::updateMontantCommissionMensuel('commercial-v6', 1000, 10))->toBe(0.0);
});
