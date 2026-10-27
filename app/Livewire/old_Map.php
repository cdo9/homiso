<?php

namespace App\Livewire;

use App\Models\Client;
use App\Models\ClientHistory;
use App\Models\Comment;
use App\Models\Source;
use App\Models\User;
use App\Models\Vente;
use App\Services\ProjectService;
use Illuminate\Http\Request;
use Livewire\Component;
use App\Services\StatsContactsServices;

class Map extends Component
{
    public $commercials;
    public $priorities;
    public $sources;
    public $zones;
    public $selectedCommercial = 0;
    public $selectedPriority = '';
    public $date1;
    public $date2;

    // ClientForm integration properties
    public $showClientForm = false;

    public function mount(Request $request)
    {
        // Récupère les commerciaux actifs
        $this->commercials = User::where('active', 1)->get();

        // Service pour lister les projets
        $this->priorities = ProjectService::listPriorities();

        // Récupère les sources depuis la base de données
        $this->sources = Source::all();

        $secteurs = \DB::table('secteurs')->get();

        $this->zones = $secteurs->filter(function($secteur) {
            return !is_null($secteur->coords);
        })->map(function($secteur) {
            return [
                'name' => $secteur->nom,
                'color' => $secteur->couleur,
                'coords' => $this->parseWktPolygon($secteur->coords),
            ];
        })->values()->toArray();

        // Initialize filter values from request
        $this->selectedCommercial = $request->query('commercial', 0);
        $this->selectedPriority = $request->query('priority', '');
        $this->date1 = $request->query('date1');
        $this->date2 = $request->query('date2');
    }

    public function getMapPoints(Request $request)
    {
        // Start output buffering to capture any unexpected output
        ob_start();

        try {
            // Validate the request parameters
            $validated = $request->validate([
                'topLeftLat' => 'required|numeric',
                'topLeftLng' => 'required|numeric',
                'bottomRightLat' => 'required|numeric',
                'bottomRightLng' => 'required|numeric',
                'commercial' => 'nullable|numeric',
                'priority' => 'nullable|string',
                'date1' => 'nullable|date',
                'date2' => 'nullable|date',
                'zoom' => 'nullable|numeric',
                'secteur' => 'required|numeric'
            ]);

            // Get the bounds
            $topLeftLat = $validated['topLeftLat'];
            $topLeftLng = $validated['topLeftLng'];
            $bottomRightLat = $validated['bottomRightLat'];
            $bottomRightLng = $validated['bottomRightLng'];

            // Get the commercial ID
            $commercialId = $validated['commercial'] ?? 0;

            // Get the priority
            $priority = $validated['priority'] ?? null;

            // Get the date range
            $date1 = $validated['date1'] ?? null;
            $date2 = $validated['date2'] ?? null;

            $secteur = $validated['secteur'] ?? null;

            // Get the zoom level (default to 14 if not provided)
            $zoomLevel = isset($validated['zoom']) ? (int)$validated['zoom'] : 14;

            // Generate cache key based on request parameters
            $cacheKey = 'map_points_' . md5(json_encode($validated));

            // Check if we have a cached response
            if (\Cache::has($cacheKey)) {
                // Return cached response with appropriate headers
                return response()->json(\Cache::get($cacheKey))
                    ->header('X-Cache', 'HIT')
                    ->header('Cache-Control', 'public, max-age=300'); // 5 minutes
            }

            // Query the database for points within the bounds
            $points = [];

            // Import the Client model with eager loading to reduce N+1 queries
            $clientsQuery = Client::with([
                'user:id,prenom,nom', // Only select necessary fields for the commercial
                'comments' => function ($query) {
                    $query->latest('date')->limit(1); // Get only the latest comment
                },
                'comments.user:id,prenom,nom', // Get the user who made the comment
                'clientHistory' => function ($query) {
                    $query->latest('created_at')->limit(1); // Get only the latest status change
                }
            ])
                ->whereNotNull('gps_lat')
                ->whereNotNull('gps_lng')
                ->whereBetween('gps_lat', [$bottomRightLat, $topLeftLat])
                ->whereBetween('gps_lng', [$topLeftLng, $bottomRightLng])
                ->where('secteur', '=', $secteur);

            // Filter by commercial if provided
            if ($commercialId > 0) {
                $clientsQuery->where('rentre_par', $commercialId);
            }

            // Filter by date range if provided
            if ($date1 && $date2) {
                $clientsQuery->whereBetween('rentre_le', [$date1, $date2]);
            } elseif ($date1) {
                $clientsQuery->where('rentre_le', '>=', $date1);
            } elseif ($date2) {
                $clientsQuery->where('rentre_le', '<=', $date2);
            }

            // Filter by priority if provided
            if ($priority) {
                // Get the slug for the priority from ProjectService
                $prioritySlug = null;
                $priorities = ProjectService::listPriorities();
                foreach ($priorities as $category => $items) {
                    foreach ($items as $slug => $label) {
                        if ($label === $priority) {
                            $prioritySlug = $slug;
                            break 2;
                        }
                    }
                }

                if ($prioritySlug) {
                    // Join with rendez_vous table and filter by products
                    $clientsQuery->whereHas('rendez_vous', function ($query) use ($prioritySlug) {
                        $query->where('produits', 'LIKE', '%' . $prioritySlug . '%');
                    });
                } else {
                    // Fallback to the old method if no slug is found
                    $clientsQuery->where('projet', $priority);
                }
            }

            // Limit the number of results based on zoom level
            $clients = $clientsQuery->get();

            // Determine if we need detailed info based on zoom level
            $needDetailedInfo = $zoomLevel >= 14;

            // Format client data for the map
            foreach ($clients as $client) {
                // Basic marker data that's always included
                $markerData = [
                    'id' => $client->id, // Add client ID for identification
                    'lat' => $client->gps_lat,
                    'lng' => $client->gps_lng,
                    'title' => $client->nom . ' ' . $client->prenom,
                    'type' => $client->type, // Use client's actual type for marker color
                ];

                // Only include detailed content if zoomed in enough or if specifically requested
                if ($needDetailedInfo) {
                    // Get the commercial who added the client (using eager loaded relationship)
                    $commercialName = $client->user ? $client->user->prenom . ' ' . $client->user->nom : 'Ancien commercial';

                    // Format the date when the client was added
                    $addedDate = $client->rentre_le ? $client->rentre_le->format('d/m/Y') : 'Inconnue';

                    // Get the last comment for this client (using eager loaded relationship)
                    $lastComment = $client->comments->first();

                    // Only load sales data if we're at a high zoom level
                    $salesTableHtml = '';
                    if ($zoomLevel >= 16) {
                        // Get previous sales for this client with poses
                        $previousSales = Vente::where('id_client', $client->id)
                            ->with('poses')
                            ->leftJoin('users', 'ventes.id_commercial', '=', 'users.id')
                            ->orderBy('date', 'desc')
                            ->get([
                                'ventes.*',
                                'users.prenom as commercial_prenom',
                                'users.nom as commercial_nom'
                            ]);

                        // Build the sales table HTML
                        if ($previousSales->count() > 0) {
                            $salesTableHtml =
                                '<div style="margin-top: 10px; border-top: 1px solid #eee; padding-top: 10px;">' .
                                '<h4 style="margin: 0 0 5px 0;">Ventes précédentes:</h4>' .
                                '<div style="max-height: 200px; overflow-y: auto;">' .
                                '<table style="width: 100%; border-collapse: collapse; font-size: 12px;">' .
                                '<thead>' .
                                '<tr style="background-color: #f2f2f2;">' .
                                '<th style="padding: 5px; text-align: left; border: 1px solid #ddd;">Date</th>' .
                                '<th style="padding: 5px; text-align: left; border: 1px solid #ddd;">Commercial</th>' .
                                '<th style="padding: 5px; text-align: right; border: 1px solid #ddd;">Tarif total HT</th>' .
                                '<th style="padding: 5px; text-align: left; border: 1px solid #ddd;">Produits</th>' .
                                '<th style="padding: 5px; text-align: left; border: 1px solid #ddd;">Complément</th>' .
                                '<th style="padding: 5px; text-align: left; border: 1px solid #ddd;">Statut</th>' .
                                '</tr>' .
                                '</thead>' .
                                '<tbody>';

                            foreach ($previousSales as $sale) {
                                $saleDate = $sale->date ? $sale->date->format('d/m/Y') : 'N/A';

                                // Get the commercial information
                                //$saleCommercial = User::find($sale->id_commercial);
                                $saleCommercialName = $sale->commercial_nom ? $sale->commercial_prenom . ' ' . $sale->commercial_nom : 'N/A';

                                // Format the total price before tax
                                $totalHT = number_format($sale->tarif_total_ht, 0, ',', ' ') . ' €';

                                // Determine status text based on statut value
                                $statusText = 'N/A';
                                switch ($sale->statut) {
                                    case 0:
                                        $statusText = 'En attente';
                                        break;
                                    case 1:
                                        $statusText = 'Validée';
                                        break;
                                    case 2:
                                        $statusText = 'Vendu';
                                        break;
                                    case 3:
                                        $statusText = 'Annulée';
                                        break;
                                    default:
                                        $statusText = 'Autre';
                                        break;
                                }

                                // Get products from poses
                                $productsHtml = '';
                                if ($sale->poses->count() > 0) {
                                    $productsHtml = '<ul style="margin: 0; padding-left: 15px;">';
                                    foreach ($sale->poses as $pose) {
                                        if (!empty($pose->produit)) {
                                            $productsHtml .= '<li>' . $pose->produit . '</li>';
                                        }
                                        if (!empty($pose->produit2)) {
                                            $productsHtml .= '<li>' . $pose->produit2 . '</li>';
                                        }
                                        if (!empty($pose->produit3)) {
                                            $productsHtml .= '<li>' . $pose->produit3 . '</li>';
                                        }
                                    }
                                    $productsHtml .= '</ul>';
                                } else {
                                    $productsHtml = 'Aucun produit';
                                }

                                $salesTableHtml .=
                                    '<tr style="border-bottom: 1px solid #ddd;">' .
                                    '<td style="padding: 5px; border: 1px solid #ddd;">' . $saleDate . '</td>' .
                                    '<td style="padding: 5px; border: 1px solid #ddd;">' . $saleCommercialName . '</td>' .
                                    '<td style="padding: 5px; text-align: right; border: 1px solid #ddd;">' . $totalHT . '</td>' .
                                    '<td style="padding: 5px; border: 1px solid #ddd;">' . $productsHtml . '</td>' .
                                    '<td style="padding: 5px; border: 1px solid #ddd;">' . htmlspecialchars($sale->complement ?? '') . '</td>' .
                                    '<td style="padding: 5px; border: 1px solid #ddd;">' . $statusText . '</td>' .
                                    '</tr>';
                            }

                            $salesTableHtml .= '</tbody></table></div></div>';
                        }
                    }

                    // Build the full content HTML
                    $markerData['content'] = '<div style="padding: 5px;">' .
                        '<strong>' . $client->nom . '</strong><br>' .
                        $client->adresse . '<br>' .
                        'Par: ' . $commercialName . '<br>' .
                        'Ajouté: ' . $addedDate . '<br>' .
                        '<a href="/relances/contact/'. $client->id .'/edit" style="color: blue; text-decoration: underline;">Voir la fiche</a>' .
                        (in_array($client->type, [0, 6, 7, 8]) ?
                            '<label>Statut : </label><span>'. StatsContactsServices::ClientTypes()[$client->type] . '</span>'.
                            '<form id="updateTypeForm-' . $client->id . '" class="update-type-form" style="margin-top: 10px;">' .
                            '<select name="type" class="type-select" data-client-id="' . $client->id . '">' .
                            '<option value="0"' . ($client->type == 0 ? ' selected' : '') . '>Précontact</option>' .
                            '<option value="6"' . ($client->type == 6 ? ' selected' : '') . '>Refus</option>' .
                            '<option value="7"' . ($client->type == 7 ? ' selected' : '') . '>Absent</option>' .
                            '<option value="8"' . ($client->type == 8 ? ' selected' : '') . '>Relance</option>' .
                            '</select>' .
                            '</form>' : '') .
                        $salesTableHtml .
                        '<div style="margin-top: 10px; border-top: 1px solid #eee; padding-top: 10px;">' .
                        '<h4 style="margin: 0 0 5px 0;">Dernier commentaire:</h4>' .
                        ($lastComment ?
                            '<div style="background-color: #f5f5f5; padding: 5px; border-radius: 3px; margin-bottom: 10px;">' .
                            '<p style="margin: 0;">' . $lastComment->commentaire . '</p>' .
                            '<small style="color: #666;">Par: ' . ($lastComment->user ? $lastComment->user->prenom . ' ' . $lastComment->user->nom : 'Inconnu') . ' - ' . $lastComment->date->format('d/m/Y') . '</small>' .
                            '</div>' :
                            '<p style="color: #666; font-style: italic; margin: 0 0 10px 0;">Aucun commentaire</p>') .
                        '<form id="commentForm-' . $client->id . '" class="comment-form" style="margin-top: 10px;">' .
                        '<input type="hidden" name="client_id" value="' . $client->id . '">' .
                        '<textarea name="commentaire" placeholder="Ajouter un commentaire" style="width: 100%; min-height: 60px; margin-bottom: 5px; padding: 5px;"></textarea>' .
                        '<button type="submit" style="background-color: #4CAF50; color: white; border: none; padding: 5px 10px; text-align: center; text-decoration: none; display: inline-block; font-size: 12px; margin: 2px 2px; cursor: pointer; border-radius: 3px;">Envoyer</button>' .
                        '</form>' .
                        '</div>' .
                        '</div>';
                } else {
                    // For zoomed out views, just provide minimal info for the tooltip
                    $markerData['content'] = '<div style="padding: 5px;">' .
                        '<strong>' . $client->nom . ' ' . $client->prenom . '</strong><br>' .
                        $client->adresse . '<br>' .
                        '<a href="/relances/contact/'. $client->id .'/edit" style="color: blue; text-decoration: underline;">Voir la fiche</a>' .
                        '</div>';
                }

                $points[] = $markerData;
            }

            // Cache the response for 5 minutes
            \Cache::put($cacheKey, $points, 300);

            // Clean output buffer before returning JSON response
            ob_end_clean();

            // Return the points as JSON with cache headers
            return response()->json($points)
                ->header('X-Cache', 'MISS')
                ->header('Cache-Control', 'public, max-age=300'); // 5 minutes
        } catch (\Exception $e) {
            // Log the error for debugging
            \Log::error('Error fetching map points: ' . $e->getMessage(), [
                'exception' => $e,
                'request_params' => $request->all()
            ]);

            // Clean output buffer before returning error response
            ob_end_clean();

            // Return a JSON error response
            return response()->json([
                'error' => 'An error occurred while fetching map points',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function parseWktPolygon(string $wkt): array
    {
        $coordsStr = str_replace(['POLYGON ((', '))'], '', $wkt);
        $points = explode(',', $coordsStr);

        $coords = [];
        foreach ($points as $point) {
            [$lng, $lat] = array_map('floatval', preg_split('/\s+/', trim($point)));
            $coords[] = ['lat' => $lat, 'lng' => $lng];
        }

        return $coords;
    }

    /**
     * Open the client form with GPS coordinates from map click
     */
    public function openClientForm($lat = null, $lng = null)
    {
        $this->dispatch('openClientForm', $lat, $lng);
    }

    /**
     * Handle client form events
     */
    protected $listeners = [
        'clientSaved' => 'handleClientSaved',
        'clientDeleted' => 'handleClientDeleted',
        'formOpened' => 'handleFormOpened',
        'formClosed' => 'handleFormClosed'
    ];

    /**
     * Handle when a client is saved from the form
     */
    public function handleClientSaved($data)
    {
        // Refresh map data or add new marker
        $this->dispatch('refreshMapMarkers', $data);

        // Show success message
        session()->flash('success', $data['isNew'] ? 'Client créé avec succès!' : 'Client mis à jour avec succès!');
    }

    /**
     * Handle when a client is deleted from the form
     */
    public function handleClientDeleted($clientId)
    {
        // Remove marker from map
        $this->dispatch('removeMapMarker', $clientId);

        // Show success message
        session()->flash('success', 'Client supprimé avec succès!');
    }

    /**
     * Handle when the form is opened
     */
    public function handleFormOpened()
    {
        $this->showClientForm = true;
    }

    /**
     * Handle when the form is closed
     */
    public function handleFormClosed()
    {
        $this->showClientForm = false;
    }

    /**
     * Open form to edit an existing client
     */
    public function editClient($clientId)
    {
        $this->dispatch('editClient', $clientId);
    }

    public function render()
    {
        return view('livewire.map');
    }
}
