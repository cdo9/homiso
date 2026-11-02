@extends('layouts.app')

@php
    $authUser = auth()->user();

    $fieldClass = 'bg-[#F3F3F3] rounded-full px-4 py-2 border border-[#DCDADA] focus:outline-none focus:ring-2 focus:ring-[#77BC23] transition';
    $selectClass = 'text-start h-10 px-3 py-2 bg-[#F3F3F3] rounded-full border border-[#DCDADA] focus:outline-none focus:ring-2 focus:ring-[#77BC23] transition';
    $labelClass = 'text-[#77BC23] font-bold';
    $errorClass = 'text-red-500 text-sm mt-1';

    $profilsClient = ['Foire / salon', 'Artisan', 'Internet', 'Démarchage à domicile', 'Bouche à oreille', 'Famille', 'Magasin'];
    $profilClientActuel = $client?->profil_clients;
    $profilClientEnregistre = match (true) {
        blank($profilClientActuel) => '',
        in_array($profilClientActuel, $profilsClient, true) => $profilClientActuel,
        default => 'Autre',
    };
    $profilClientSelectionne = old('profil_client', $profilClientEnregistre);
    $profilClientAutre = old('profil_client_autre', $profilClientEnregistre === 'Autre' ? $profilClientActuel : '');

    $civilite = old('choix', $client?->genre) ?: 'Mr & Mme';
    $source = old('source', $client?->id_source);
    $projet = old('projet', $client?->projet);
    $typeHabitation = old('type_habitation', $client?->type_habitation) ?: 'Maison individuelle';
    $proprietaire = (string) old('proprietaire', $client?->proprietaire);
    $proprietaire = $proprietaire === '' ? '1' : $proprietaire;
    $commercial = old('commercial', $client ? $client->rentre_par : $authUser->id);

    $peutPartager = $authUser->active == 1
        && ($authUser->role === 'responsable' || $groupes->pluck('id_responsable')->contains($authUser->id));
@endphp

@section('content')
<form action="{{ route('formulaire-contact') }}" method="POST" class="gap-6 p-4 sm:p-10 bg-white rounded-xl shadow-lg max-w-4xl mx-auto items-center">
    @csrf
    <input type="hidden" name="secteur_id" id="secteur_id_hidden" value="{{ session('selectedSecteur') }}">
    @if($client)
        <input type="hidden" name="client_id" value="{{ $client->id }}">
    @endif

    @if(session()->has('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">{{ session('success') }}</div>
    @endif
    @if(session()->has('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">{{ session('error') }}</div>
    @endif

    <div class="flex flex-col p-2 sm:p-6 gap-8">
        {{-- Identité --}}
        <div class="flex flex-row flex-wrap sm:flex-nowrap gap-4">
            <div class="flex flex-col w-full sm:w-1/4">
                <label for="choix" class="{{ $labelClass }}">Civilité<span class="text-orange-500">*</span></label>
                <select name="choix" id="choix" class="{{ $selectClass }}" required>
                    <option value="" disabled hidden>Choisir....</option>
                    <option value="Monsieur" @selected($civilite === 'Monsieur')>Monsieur</option>
                    <option value="Madame" @selected($civilite === 'Madame')>Madame</option>
                    <option value="Mr & Mme" @selected($civilite === 'Mr & Mme')>M. & Mme</option>
                </select>
                @error('choix') <div class="{{ $errorClass }}">{{ $message }}</div> @enderror
            </div>
            <div class="flex flex-col w-full sm:w-1/5">
                <label for="source" class="{{ $labelClass }}">Source<span class="text-orange-500">*</span></label>
                <select name="source" id="source" class="{{ $selectClass }}" required>
                    <option value="" disabled hidden @selected(blank($source))>Choisir....</option>
                    @foreach($sources as $categorie => $liste)
                        <optgroup label="{{ $categorie }}">
                            @foreach($liste as $slug => $nom)
                                <option value="{{ $slug }}" @selected($source == $slug)>{{ $nom }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                @error('source') <div class="{{ $errorClass }}">{{ $message }}</div> @enderror
            </div>
            <div class="flex flex-col w-full sm:w-1/4">
                <label for="nom" class="{{ $labelClass }}">Nom<span class="text-orange-500">*</span></label>
                <input type="text" name="nom" id="nom" value="{{ old('nom', $client?->nom) }}" class="{{ $fieldClass }}" required>
                @error('nom') <div class="{{ $errorClass }}">{{ $message }}</div> @enderror
            </div>
            <div class="flex flex-col w-full sm:w-1/4">
                <label for="prenom" class="{{ $labelClass }}">Prénom</label>
                <input type="text" name="prenom" id="prenom" value="{{ old('prenom', $client?->prenom) }}" class="{{ $fieldClass }}">
                @error('prenom') <div class="{{ $errorClass }}">{{ $message }}</div> @enderror
            </div>
            <input type="hidden" name="type" id="type" value="{{ old('type', $client?->type ?? 0) }}">
        </div>

        {{-- Adresse --}}
        <x-address-search>
            <input type="hidden" name="gps_lat" id="gps_lat">
            <input type="hidden" name="gps_lng" id="gps_lng">
        </x-address-search>
        <div class="flex flex-row flex-wrap sm:flex-nowrap gap-4">
            <div class="flex flex-col w-full sm:w-1/2">
                <label for="adresse" class="{{ $labelClass }}">Adresse<span class="text-orange-500">*</span></label>
                <input type="text" name="adresse" id="adresse" value="{{ old('adresse', $client?->adresse) }}" class="{{ $fieldClass }}">
                @error('adresse') <div class="{{ $errorClass }}">{{ $message }}</div> @enderror
            </div>
            <div class="flex flex-col w-full sm:w-1/4">
                <label for="code_postal" class="{{ $labelClass }}">Code Postal<span class="text-orange-500">*</span></label>
                <input type="text" name="code_postal" id="code_postal" value="{{ old('code_postal', $client?->cp) }}" class="{{ $fieldClass }}" required pattern="[0-9]{5}" inputmode="numeric" maxlength="5" data-digits-only>
                @error('code_postal') <div class="{{ $errorClass }}">{{ $message }}</div> @enderror
            </div>
            <div class="flex flex-col w-full sm:w-1/4">
                <label for="ville" class="{{ $labelClass }}">Ville<span class="text-orange-500">*</span></label>
                <input type="text" name="ville" id="ville" value="{{ old('ville', $client?->ville) }}" class="{{ $fieldClass }}" required>
                @error('ville') <div class="{{ $errorClass }}">{{ $message }}</div> @enderror
            </div>
        </div>

        {{-- Téléphones --}}
        <div class="flex flex-row flex-wrap sm:flex-nowrap gap-4">
            <div class="flex flex-col w-full sm:w-1/2">
                <label for="telephone1" class="{{ $labelClass }}">Téléphone 1<span class="text-orange-500">*</span></label>
                <input type="tel" name="telephone1" id="telephone1" value="{{ old('telephone1', $client?->telephone1) }}" class="{{ $fieldClass }}" required inputmode="numeric" pattern="[0-9]*" maxlength="16" data-digits-only>
                @error('telephone1') <div class="{{ $errorClass }}">{{ $message }}</div> @enderror
            </div>
            <div class="flex flex-col w-full sm:w-1/2">
                <label for="telephone2" class="{{ $labelClass }}">Téléphone 2</label>
                <input type="tel" name="telephone2" id="telephone2" value="{{ old('telephone2', $client?->telephone2) }}" class="{{ $fieldClass }}" data-digits-only>
                @error('telephone2') <div class="{{ $errorClass }}">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="flex flex-col w-full">
            <label for="commentaire" class="{{ $labelClass }}">Commentaire</label>
            <textarea name="commentaire" id="commentaire" class="bg-[#F3F3F3] rounded-xl px-4 py-2 border border-[#DCDADA] focus:outline-none focus:ring-2 focus:ring-[#77BC23] transition h-24 resize-y">{{ old('commentaire', $client?->debrief) }}</textarea>
            @error('commentaire') <div class="{{ $errorClass }}">{{ $message }}</div> @enderror
        </div>

        {{-- Habitude d'achat --}}
        <div class="flex flex-col w-full">
            <label for="profil_client" class="{{ $labelClass }}">Habitude d'achat<span class="text-orange-500">*</span></label>
            <select name="profil_client" id="profil_client" class="{{ $fieldClass }}" required>
                <option value="" disabled hidden @selected(blank($profilClientSelectionne))>Choisir l'habitude d'achat...</option>
                @foreach([...$profilsClient, 'Autre'] as $profil)
                    <option value="{{ $profil }}" @selected($profilClientSelectionne === $profil)>{{ $profil }}</option>
                @endforeach
            </select>
            <input type="text" name="profil_client_autre" id="profil_client_autre" value="{{ $profilClientAutre }}" placeholder="Précisez si autre..." class="{{ $fieldClass }} mt-2" @if($profilClientSelectionne === 'Autre') required @else style="display: none;" @endif>
            @error('profil_client') <div class="{{ $errorClass }}">{{ $message }}</div> @enderror
            @error('profil_client_autre') <div class="{{ $errorClass }}">{{ $message }}</div> @enderror
        </div>

        {{-- Projet --}}
        <div class="flex flex-row flex-wrap sm:flex-nowrap gap-4">
            <div class="flex flex-col w-full sm:w-1/2">
                <label for="projet" class="{{ $labelClass }}">Projet<span class="text-orange-500">*</span></label>
                <select name="projet" id="projet" class="{{ $fieldClass }}" required>
                    <option value="" disabled hidden @selected(blank($projet))>Choisir....</option>
                    @foreach($priorities as $categorie => $priorites)
                        <optgroup label="{{ $categorie }}">
                            @foreach($priorites as $priorite)
                                <option value="{{ $priorite }}" @selected($projet == $priorite)>{{ $priorite }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                @error('projet') <div class="{{ $errorClass }}">{{ $message }}</div> @enderror
            </div>
            <div class="flex flex-col w-full sm:w-1/2">
                <label for="type_habitation" class="{{ $labelClass }}">Type d'habitation<span class="text-orange-500">*</span></label>
                <select name="type_habitation" id="type_habitation" class="{{ $fieldClass }}" required>
                    <option value="" disabled hidden>Choisir....</option>
                    <option value="Appartement" @selected($typeHabitation === 'Appartement')>Appartement</option>
                    <option value="Maison individuelle" @selected($typeHabitation === 'Maison individuelle')>Maison individuelle</option>
                </select>
                @error('type_habitation') <div class="{{ $errorClass }}">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="flex flex-row flex-wrap sm:flex-nowrap gap-4">
            <div class="flex flex-col w-full sm:w-1/2">
                <label for="date_anniversaire" class="{{ $labelClass }}">Date d'anniversaire</label>
                <input type="date" name="date_anniversaire" id="date_anniversaire" value="{{ old('date_anniversaire', $client?->date_anniversaire?->format('Y-m-d')) }}" class="{{ $fieldClass }}">
                @error('date_anniversaire') <div class="{{ $errorClass }}">{{ $message }}</div> @enderror
            </div>
            <div class="flex flex-col w-full sm:w-1/2">
                <label for="proprietaire" class="{{ $labelClass }}">Propriétaire<span class="text-orange-500">*</span></label>
                <select name="proprietaire" id="proprietaire" class="{{ $fieldClass }}" required>
                    <option value="" disabled hidden>Choisir....</option>
                    <option value="1" @selected($proprietaire === '1')>Oui</option>
                    <option value="0" @selected($proprietaire === '0')>Non</option>
                </select>
                @error('proprietaire') <div class="{{ $errorClass }}">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="flex flex-row flex-wrap sm:flex-nowrap gap-4">
            <div class="flex flex-col w-full sm:w-1/2">
                <label for="profession_monsieur" class="{{ $labelClass }}">Profession (Monsieur)</label>
                <input type="text" name="profession_monsieur" id="profession_monsieur" value="{{ old('profession_monsieur', $client?->profession) }}" class="{{ $fieldClass }}">
                @error('profession_monsieur') <div class="{{ $errorClass }}">{{ $message }}</div> @enderror
            </div>
            <div class="flex flex-col w-full sm:w-1/2">
                <label for="profession_madame" class="{{ $labelClass }}">Profession (Madame)</label>
                <input type="text" name="profession_madame" id="profession_madame" value="{{ old('profession_madame', $client?->profession2) }}" class="{{ $fieldClass }}">
                @error('profession_madame') <div class="{{ $errorClass }}">{{ $message }}</div> @enderror
            </div>
        </div>

        {{-- Suivi commercial --}}
        <div class="flex flex-row flex-wrap sm:flex-nowrap gap-4">
            <div class="flex flex-col w-full sm:w-1/3">
                <label for="date_relance" class="{{ $labelClass }}">Date de relance</label>
                <input type="date" name="date_relance" id="date_relance" value="{{ old('date_relance', $client?->date_relance?->format('Y-m-d') ?? $nextNov20) }}" class="{{ $fieldClass }}">
                @error('date_relance') <div class="{{ $errorClass }}">{{ $message }}</div> @enderror
            </div>
            <div class="flex flex-col w-full {{ $authUser->role === 'admin' ? 'sm:w-1/3' : 'sm:w-1/2' }}">
                <label for="commercial" class="{{ $labelClass }}">Commercial</label>
                <select name="commercial" id="commercial" class="{{ $selectClass }}">
                    <option value="" disabled hidden>Choisir....</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}" @selected($commercial == $user->id)>{{ $user->display_name }}</option>
                    @endforeach
                </select>
                @error('commercial') <div class="{{ $errorClass }}">{{ $message }}</div> @enderror
            </div>
            @if($peutPartager)
                <div class="flex flex-col w-full sm:w-1/3">
                    <label for="partage_avec" class="{{ $labelClass }}">Partagé avec...</label>
                    @if($users->isNotEmpty())
                        <select name="partage_avec" id="partage_avec" class="{{ $selectClass }}">
                            <option value="" disabled selected hidden>Choisir un commercial...</option>
                            @foreach($users->where('id', '!=', $authUser->id) as $user)
                                <option value="{{ $user->id }}" @selected(old('partage_avec', $client?->partage_avec) == $user->id)>{{ $user->display_name }}</option>
                            @endforeach
                        </select>
                    @else
                        <p class="text-gray-500">Aucun commercial.</p>
                    @endif
                    @error('partage_avec') <div class="{{ $errorClass }}">{{ $message }}</div> @enderror
                </div>
            @endif
        </div>

        <div class="flex flex-row w-full justify-center">
            <button type="submit" class="cursor-pointer bg-[#F4A12C] text-white font-bold py-3 px-8 rounded-full hover:bg-[#e8911c] transition duration-300 shadow-md w-full">Suivant</button>
            @unless($client)
                <button type="submit" name="create_rdv_after" value="1" class="cursor-pointer bg-[#F4A12C] text-white font-bold py-3 px-8 rounded-full hover:bg-[#e8911c] transition duration-300 shadow-md w-full">Nouveau RDV</button>
            @endunless
        </div>
    </div>

    {{-- Historique des commentaires --}}
    <div class="flex flex-col w-full mt-8 gap-4">
        <label class="{{ $labelClass }} mb-2">Tableau liste des commentaires</label>
        @forelse($comments as $comment)
            <div class="bg-[#F3F3F3] rounded-xl shadow p-4 flex flex-col sm:flex-row sm:items-center gap-2 border border-[#DCDADA]">
                <div class="flex flex-col sm:w-1/5">
                    <span class="text-xs text-gray-500 font-semibold">Date</span>
                    <span class="text-sm text-[#77BC23] font-bold">{{ \Carbon\Carbon::parse($comment->date)->format('d/m/Y H:i') }}</span>
                </div>
                <div class="flex flex-col sm:w-1/5">
                    <span class="text-xs text-gray-500 font-semibold">Utilisateur</span>
                    <span class="text-sm font-bold">{{ $comment->client_prenom }} {{ $comment->client_nom }}</span>
                </div>
                <div class="flex-1 flex flex-col">
                    <span class="text-xs text-gray-500 font-semibold">Commentaire</span>
                    <span class="text-sm">{{ $comment->commentaire }}</span>
                </div>
            </div>
        @empty
            <p class="text-gray-500">Aucun commentaire.</p>
        @endforelse
    </div>
</form>

@if($client)
    <form action="{{ route('formulaire-contact') }}" method="POST" class="gap-6 p-4 sm:p-10 bg-[#F8FFF3] rounded-xl shadow-lg max-w-4xl mx-auto items-center mt-8 border-2 border-[#77BC23]">
        @csrf
        <input type="hidden" name="client_id" value="{{ $client->id }}">
        <div class="flex flex-col w-full gap-4">
            <h2 class="text-xl font-bold text-[#77BC23]">Ajouter un nouveau commentaire</h2>
            <div class="flex flex-col w-full">
                <textarea name="nouveau_commentaire" id="nouveau_commentaire" class="bg-white rounded-xl px-4 py-2 border border-[#DCDADA] focus:outline-none focus:ring-2 focus:ring-[#77BC23] transition h-24 resize-y" placeholder="Écrivez votre commentaire ici..."></textarea>
                @error('nouveau_commentaire') <div class="{{ $errorClass }}">{{ $message }}</div> @enderror
            </div>
            <div class="flex flex-row w-full justify-end">
                <button type="submit" name="ajouter_commentaire" value="1" class="cursor-pointer bg-[#77BC23] text-white font-bold py-2 px-6 rounded-full hover:bg-[#69a41f] transition duration-300 shadow-md">Ajouter le commentaire</button>
            </div>
        </div>
    </form>
@endif

<script>
document.querySelectorAll('[data-digits-only]').forEach((input) => {
    input.addEventListener('input', () => {
        input.value = input.value.replace(/[^0-9]/g, '');
    });
});

(function initProfilClientAutre() {
    const select = document.getElementById('profil_client');
    const inputAutre = document.getElementById('profil_client_autre');

    select.addEventListener('change', () => {
        const isAutre = select.value === 'Autre';
        inputAutre.style.display = isAutre ? '' : 'none';
        inputAutre.required = isAutre;
        if (!isAutre) {
            inputAutre.value = '';
        }
    });
})();
</script>
@endsection
