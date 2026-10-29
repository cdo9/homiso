<?php

use App\Models\Source;
use App\Services\CommissionService;
use App\Services\SourceService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    (new ReflectionProperty(SourceService::class, 'sourceList'))->setValue(null, []);
});

it('computes the commission on the CA share for an active source', function () {
    $source = Source::factory()->create();

    expect(CommissionService::calculMontantCommission('commercial-v1', 10000, 20000, (string) $source->id, 10))->toBe(1000.0);
});

it('reduces the base to 70% of the CA share for a passive or telepro source', function (string $contrat, string $groupe) {
    $source = Source::factory()->create(['groupe' => $groupe]);

    expect(CommissionService::calculMontantCommission($contrat, 10000, 20000, (string) $source->id, 10))->toBe(700.0);
})->with([
    'commercial, passive source' => ['commercial-v1', 'passif'],
    'commercial, telepro source' => ['commercial-v1', 'télépro'],
    'telepro, passive source' => ['telepro-v1', 'passif'],
    'directeur, passive source' => ['directeur-v1', 'passif'],
]);

it('computes a commercial commission on the total CA for parrainage telepro and leads', function (int $sourceId) {
    $source = Source::factory()->create(['id' => $sourceId]);

    expect(CommissionService::calculMontantCommission('commercial-v1', 10000, 20000, (string) $source->id, 10))->toBe(2000.0);
})->with([
    'parrainage telepro' => [17],
    'leads' => [18],
]);

it('deducts 100 euros for the apporteur source', function () {
    $source = Source::factory()->create(['id' => 23]);

    expect(CommissionService::calculMontantCommission('commercial-v1', 10000, 20000, (string) $source->id, 10))->toBe(900.0);
});
