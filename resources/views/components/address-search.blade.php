{{--
    Recherche d'adresse via l'API Adresse (Base Adresse Nationale) : gratuite, sans clé.
    Les props *Field sont les id des champs à remplir à la sélection d'une adresse.
    Les champs remplis reçoivent un événement "input", donc wire:model fonctionne.
--}}
@props([
    'id' => 'adresse_search',
    'label' => 'Rechercher une adresse',
    'placeholder' => 'Indiquez un lieu',
    'addressField' => 'adresse',
    'postcodeField' => 'code_postal',
    'cityField' => 'ville',
    'latField' => 'gps_lat',
    'lngField' => 'gps_lng',
    'labelClass' => 'text-[#77BC23] font-bold',
    'inputClass' => 'bg-[#F3F3F3] rounded-full px-4 py-2 border border-[#DCDADA] focus:outline-none focus:ring-2 focus:ring-[#77BC23] transition',
])

<div
    {{ $attributes->class('relative flex flex-col w-full') }}
    data-address-search
    data-address-field="{{ $addressField }}"
    data-postcode-field="{{ $postcodeField }}"
    data-city-field="{{ $cityField }}"
    data-lat-field="{{ $latField }}"
    data-lng-field="{{ $lngField }}"
    wire:ignore
>
    <label for="{{ $id }}" class="{{ $labelClass }}">{{ $label }}</label>
    <input type="text" id="{{ $id }}" placeholder="{{ $placeholder }}" autocomplete="off" class="{{ $inputClass }}" data-address-search-input>
    <ul class="hidden absolute top-full left-0 right-0 z-50 mt-1 bg-white border border-[#DCDADA] rounded-xl shadow-lg max-h-60 overflow-auto" data-address-search-results></ul>
    {{ $slot }}
</div>

@once
<script>
(() => {
    const API_URL = 'https://data.geopf.fr/geocodage/search';
    const MIN_QUERY_LENGTH = 3;
    const DEBOUNCE_MS = 250;

    const fillField = (id, value) => {
        const field = id ? document.getElementById(id) : null;
        if (!field) {
            return;
        }
        field.value = value;
        field.dispatchEvent(new Event('input', { bubbles: true }));
    };

    const initAddressSearch = (container) => {
        const input = container.querySelector('[data-address-search-input]');
        const list = container.querySelector('[data-address-search-results]');
        const targets = container.dataset;

        let debounceTimer = null;
        let controller = null;
        let features = [];

        const hideSuggestions = () => {
            list.innerHTML = '';
            list.classList.add('hidden');
            features = [];
        };

        const selectFeature = (feature) => {
            const properties = feature.properties;
            const [lng, lat] = feature.geometry.coordinates;

            // Pour une commune seule, "name" contient le nom de la ville : on ne remplit pas l'adresse
            fillField(targets.addressField, properties.type === 'municipality' ? '' : properties.name);
            fillField(targets.postcodeField, properties.postcode || '');
            fillField(targets.cityField, properties.city || '');
            fillField(targets.latField, lat);
            fillField(targets.lngField, lng);

            input.value = properties.label;
            hideSuggestions();
            container.dispatchEvent(new CustomEvent('address-selected', { bubbles: true, detail: { lat, lng, properties } }));
        };

        const renderSuggestions = () => {
            list.innerHTML = '';
            list.classList.toggle('hidden', features.length === 0);

            features.forEach((feature) => {
                const item = document.createElement('li');
                item.textContent = feature.properties.label;
                item.className = 'px-4 py-2 text-sm cursor-pointer hover:bg-[#F3F3F3]';
                item.addEventListener('mousedown', (event) => {
                    event.preventDefault();
                    selectFeature(feature);
                });
                list.appendChild(item);
            });
        };

        const fetchSuggestions = async (query) => {
            controller?.abort();
            controller = new AbortController();

            const params = new URLSearchParams({ index: 'address', autocomplete: '1', limit: '5', q: query });

            try {
                const response = await fetch(`${API_URL}?${params}`, { signal: controller.signal });
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                features = (await response.json()).features || [];
                renderSuggestions();
            } catch (error) {
                if (error.name !== 'AbortError') {
                    console.warn("Recherche d'adresse impossible :", error);
                    hideSuggestions();
                }
            }
        };

        input.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            const query = input.value.trim();
            if (query.length < MIN_QUERY_LENGTH) {
                hideSuggestions();
                return;
            }
            debounceTimer = setTimeout(() => fetchSuggestions(query), DEBOUNCE_MS);
        });

        input.addEventListener('keydown', (event) => {
            // Entrée sélectionne la première suggestion au lieu de soumettre le formulaire
            if (event.key === 'Enter' && features.length > 0) {
                event.preventDefault();
                selectFeature(features[0]);
            }
            if (event.key === 'Escape') {
                hideSuggestions();
            }
        });

        input.addEventListener('blur', hideSuggestions);
    };

    const initAddressSearches = () => {
        document.querySelectorAll('[data-address-search]:not([data-address-search-ready])').forEach((container) => {
            container.dataset.addressSearchReady = '';
            initAddressSearch(container);
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAddressSearches);
    } else {
        initAddressSearches();
    }
    document.addEventListener('livewire:navigated', initAddressSearches);
})();
</script>
@endonce
