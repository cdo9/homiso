<?php

use App\Models\User;

it('allows a role to access a page', function (string $role, string $page) {
    $user = User::factory()->make(['id' => 2, 'role' => $role]);

    expect($user->canAccessPage($page))->toBeTrue();
})->with([
    'admin, user management' => ['admin', 'gestion-utilisateurs'],
    'directeur, commissions' => ['directeur', 'commissions'],
    'directeur, blocages' => ['directeur', 'blocages'],
    'commercial, ventes' => ['commercial', 'ventes'],
    'telepro, new contact' => ['telepro', 'nouveau-contact'],
    'resp-prosp, contact stats' => ['resp-prosp', 'stats-contact'],
    'metreur, ventes' => ['metreur', 'ventes'],
    'administratif, poses' => ['administratif', 'poses'],
]);

it('forbids a role from accessing a page', function (string $role, string $page) {
    $user = User::factory()->make(['id' => 2, 'role' => $role]);

    expect($user->canAccessPage($page))->toBeFalse();
})->with([
    'directeur, user management' => ['directeur', 'gestion-utilisateurs'],
    'commercial, commissions' => ['commercial', 'commissions'],
    'commercial, blocages' => ['commercial', 'blocages'],
    'telepro, client listing' => ['telepro', 'listing-client'],
    'telepro, contact stats' => ['telepro', 'stats-contact'],
    'metreur, client listing' => ['metreur', 'listing-client'],
    'administratif, client listing' => ['administratif', 'listing-client'],
]);

it('keeps the catch-up pages accessible to every role except administratif', function (string $role) {
    $user = User::factory()->make(['id' => 2, 'role' => $role]);

    expect($user->canAccessPage('rattrapage'))->toBeTrue();
})->with(['admin', 'directeur', 'commercial', 'telepro', 'metreur']);
