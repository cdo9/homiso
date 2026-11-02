<?php

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

it('renders the new contact form with default values and without Google Maps', function () {
    $this->actingAs(User::factory()->create())
        ->withSession(['selectedSecteur' => 1])
        ->get(route('nouveau-contact'))
        ->assertOk()
        ->assertSee('<option value="Mr & Mme" selected>', false)
        ->assertSee('<option value="Maison individuelle" selected>', false)
        ->assertSee('<option value="1" selected>Oui</option>', false)
        ->assertSee('Nouveau RDV')
        ->assertSee('data-address-search', false)
        ->assertSee('livewire.js', false)
        ->assertDontSee('maps.googleapis.com');
});

it('prefills the form with the existing client values', function () {
    $client = Client::factory()->create([
        'proprietaire' => '0',
        'profil_clients' => 'Salon de l\'habitat',
        'type_habitation' => 'Appartement',
    ]);

    $this->actingAs(User::find($client->rentre_par))
        ->withSession(['selectedSecteur' => 1])
        ->get(route('nouveau-contact', ['client_id' => $client->id]))
        ->assertOk()
        ->assertSee('value="'.e($client->nom).'"', false)
        ->assertSee('<option value="0" selected>Non</option>', false)
        ->assertSee('<option value="Appartement" selected>', false)
        ->assertSee('<option value="Autre" selected>', false)
        ->assertSee('value="'.e('Salon de l\'habitat').'"', false)
        ->assertDontSee('Nouveau RDV');
});
