<?php

use App\Models\Secteur;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(LazilyRefreshDatabase::class);

it('sends the planned SMS from Homiso at each slot', function (string $date) {
    Http::preventStrayRequests();
    Http::fake(['www.spot-hit.fr/*' => Http::response(['resultat' => 1])]);
    Secteur::factory()->create();

    $this->artisan('sms:dispatch-planned', ['--date' => $date]);

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => $request['expediteur'] === 'Homiso');
})->with([
    'business day at 8am' => ['2026-10-26 08:00'],
    'friday evening' => ['2026-10-23 20:00'],
    'last business day at 8pm' => ['2026-09-30 20:00'],
]);

it('sends no SMS outside the slots', function (string $date) {
    Http::preventStrayRequests();
    Http::fake(['www.spot-hit.fr/*' => Http::response(['resultat' => 1])]);
    Secteur::factory()->create();

    $this->artisan('sms:dispatch-planned', ['--date' => $date]);

    Http::assertNothingSent();
})->with([
    'saturday at 8am' => ['2026-10-31 08:00'],
    'business day afternoon' => ['2026-10-27 14:00'],
    'thursday evening' => ['2026-10-22 20:00'],
]);
