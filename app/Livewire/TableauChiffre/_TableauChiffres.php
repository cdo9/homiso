<?php

namespace App\Livewire\TableauChiffre;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use App\Models\User;

class TableauChiffres extends Component
{
    public $month;
    public $year;
    public $groupes = [];
    public $isDirecteur = false;
    public $isTelepro = false;
    public $isAdmin = false;
    public $sortColumn = 'nom';
    public $sortDirection = 'asc';
    public $ventes = [];
    public $projets = [];
    public $rdvs = [];
    public $totauxGlobaux;
    public $totauxSecteur;

    
    protected $listeners = ['filters-updated' => 'applyFilters'];

    public function mount($year = null, $month = null)
    {
        $this->year = $year;
        $this->month = $month;
        $this->initializeUserData();
        $this->loadGroupes();
    }

    private function initializeUserData(){
        $userData = TableauChiffres::getInitializedUserData();
        $this->isAdmin = $userData->isAdmin;
        $this->isDirecteur = $userData->isDirecteur;
        $this->isTelepro = $userData->isTelepro;
    }

    public static function getInitializedUserData(){
        $user = Auth::user();
        $result = (object)[
            'isDirecteur' => $user->hasRole('directeur'),
            'isTelepro' => $user->hasRole('telepro'),
            'isAdmin' => $user->hasRole('admin')

        ];
        return $result;
    }

    public function applyFilters($data)
    {
        $this->month = $data['month'];
        $this->year = $data['year'];
        $this->loadGroupes();
    }

    public function updatedMonth()
    {
        $this->loadGroupes();
    }

    public function updatedYear()
    {
        $this->loadGroupes();
    }

    public function sortBy($column)
    {
        if ($this->sortColumn === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortColumn = $column;
            $this->sortDirection = 'asc';
        }
        $this->loadGroupes();
    }

    // === MÉTHODES DE CALCUL ===

    public function calculerCaRealise($userId)
    {
        [$startDate, $endDate] = $this->getDateRange();

        $commune = $this->getVentesSum($userId, 'Commune', $startDate, $endDate);
        $normal = $this->getVentesSum($userId, 'Normale', $startDate, $endDate);
        /*if($userId == 28)
            dd($commune, $normal, $startDate, $endDate);*/

        return $commune + $normal;
    }

    private function getVentesSum($userId, $typeVente, $startDate, $endDate)
    {
        return DB::table('ventes')
            ->where('id_commercial', $userId)
            ->where('type_vente', $typeVente)
            ->whereBetween('date', [$startDate, $endDate])
            ->where('statut', '!=', 3)
            ->sum('ca_ht');
    }

    public function calculerArgusTotal($userId)
    {
        [$startDate, $endDate] = $this->getDateRange();

        return DB::table('rendez_vous')
            ->where(function ($query) use ($userId) {
                $query->where('id_commercial', $userId)
                    ->orWhere('partage_avec', $userId);
            })
            ->whereBetween('date', [$startDate, $endDate])
            ->count();
    }

    public function rdv48Hours($userId)
    {
        $startDate = Carbon::now();
        $endDate = Carbon::now()->subDays(2);

        $nbRdv48H = DB::table('clients as c')
            ->join('projets as p', 'p.id_client', '=', 'c.id')
            ->where('c.rentre_par', $userId)
            ->where('p.rdv_annule', 0)
            ->whereBetween('p.date_confirmation', [$endDate, $startDate])
            ->selectRaw('count(p.id) as total')
            ->first();

        return $nbRdv48H?->total ?? 0;
    }

    public function calculerNombreRdv($userId, $groupe)
    {
        [$startDate, $endDate] = $this->getDateRange();

        $rdvs = DB::table('clients as c')
            ->join('projets as p', 'p.id_client', '=', 'c.id')
            ->where('c.rentre_par', $userId)
            ->where('p.rdv_annule', 0)
            ->whereBetween('p.date_confirmation', [$startDate, $endDate])
            //->where('p.secteur', $groupe->secteur)
            ->selectRaw('count(p.id) as total')
            ->first();

        return $rdvs?->total ?? 0;
    }

    public function rdv48hDernieres($userId)
    {
        [$startDate, $endDate] = $this->getDateRange();

        $results = DB::table('clients as c')
        ->leftJoin('projets as p', 'c.id', '=', 'p.id_client')
        ->select(
            DB::raw("DATE_FORMAT(p.date_confirmation, '%Y-%u') as annee_semaine"),
            DB::raw('COUNT(p.id) as cpt'),
            DB::raw("
                SUM(
                    CASE 
                        WHEN p.date IS NOT NULL 
                            AND p.date_ajout IS NOT NULL
                            AND DATEDIFF(p.date, p.date_ajout) <=
                                CASE 
                                    WHEN DAYOFWEEK(p.date_confirmation) BETWEEN 2 AND 4 THEN 2
                                    WHEN DAYOFWEEK(p.date_confirmation) BETWEEN 5 AND 7 THEN 4
                                    ELSE 2
                                END + 1
                        THEN 1 ELSE 0 
                    END
                ) as rdv48h
            ")
        )
        ->where('c.rentre_par', $userId)
        ->where('p.rdv_annule', 0)
        ->whereBetween('p.date_confirmation', [$startDate, $endDate])
        ->groupBy('annee_semaine')
        ->orderBy('annee_semaine')
        ->get();

        $last48h = 0;

        foreach($results as $data){
            $last48h += $data->rdv48h;
        }
        return $last48h;

    }

    public function calculerCaParSemaine($userId)
    {
        $semaines = $this->getSemainesDefinitions($this->year, $this->month);
        $caParSemaine = [];

        foreach ($semaines as [$startDay, $endDay]) {
            $startDate = Carbon::createFromDate($this->year, $this->month, $startDay)->toDateString();
            $endDate = Carbon::createFromDate($this->year, $this->month, $endDay)->toDateString();

            $commune = $this->getVentesSum($userId, 'Commune', $startDate, $endDate);
            $normal = $this->getVentesSum($userId, 'Normale', $startDate, $endDate);

            $caParSemaine[] = $commune + $normal;
        }
        return $caParSemaine;
    }

    public static function getSemainesDefinitions($year, $month)
    {
        $firstDay = Carbon::create($year, $month, 1);
        $lastDay = $firstDay->copy()->endOfMonth();
        $weeks = [];

        // On commence sur le premier lundi trouvé (ou le 1er si le mois commence plus tard)
        $current = $firstDay->copy();
        if ($current->dayOfWeek !== Carbon::MONDAY) {
            $current->next(Carbon::MONDAY);
            // Si le lundi est dans le mois, sinon on commence au 1er jour
            if ($current->month !== $month) {
                $current = $firstDay->copy();
            }
        }

        // Boucle tant que le début de la semaine est dans le mois
        while ($current <= $lastDay) {
            $start = $current->copy();
            // Pour la première semaine, on prend le plus tôt des deux : 1er du mois ou lundi
            if ($weeks === []) {
                $start = $firstDay->copy();
            }
            $end = $start->copy()->endOfWeek(Carbon::SUNDAY);
            // Si on dépasse la fin du mois, on arrête au dernier jour
            if ($end > $lastDay) {
                $end = $lastDay->copy();
            }
            $weeks[] = [$start->day, $end->day];
            $current = $end->addDay();
        }
        return $weeks;
    }

    public function calculerPaniermoyen($userId)
    {
        [$startDate, $endDate] = $this->getDateRange();

        return DB::table('ventes')
            ->select('type_vente', DB::raw('count(*) as total'))
            ->where('id_commercial', $userId)
            ->whereIn('type_vente', ['Commune', 'Normale'])
            ->whereBetween('date', [$startDate, $endDate])
            ->where('statut', '!=', 3)
            ->groupBy('type_vente')
            ->get();
    }

    public function calculerNombreVentes($userId)
    {
        [$startDate, $endDate] = $this->getDateRange();

        return DB::table('ventes')
            ->where('id_commercial', $userId)
            ->whereIn('type_vente', ['Commune', 'Normale'])
            ->whereBetween('date', [$startDate, $endDate])
            ->where('statut', '!=', 3)
            ->count();
    }

    public function calculerDeperdition($userId, $groupe)
    {
        [$startDate, $endDate] = $this->getDateRange();

        $result = DB::table('clients as c')
            ->leftJoin('projets as p', 'p.id_client', '=', 'c.id')
            ->select([
                DB::raw('COALESCE(SUM(p.rdv_annule), 0) as cpt'),
                DB::raw('COALESCE(SUM(p.rdv_confirme), 0) as rdv')
            ])
            ->where('c.rentre_par', $userId)
            //->where('c.secteur', $groupe->secteur)
            ->where(function($query) use($startDate, $endDate){
                $query->where(function($subQuery) use($startDate, $endDate){
                    // Cas 1: rdv_confirme = 0 AND date_ajout between dates
                    $subQuery->where('p.rdv_confirme', 0)
                        ->whereBetween('p.date_ajout', [$startDate, $endDate]);
                })
                ->orWhere(function($subQuery) use($startDate, $endDate){
                    // Cas 2: rdv_confirme = 1 avec deux sous-conditions
                    $subQuery->where('p.rdv_confirme', 1)
                        ->where(function($innerQuery) use($startDate, $endDate){
                            $innerQuery->where(function($condition1) use($startDate, $endDate){
                                // Condition 1: datediff <= 2 AND date_ajout between dates
                                $condition1->whereRaw('DATEDIFF(p.date, p.date_ajout) <= 2')
                                    ->whereBetween('p.date_ajout', [$startDate, $endDate]);
                            })
                            ->orWhere(function($condition2) use($startDate, $endDate){
                                // Condition 2: datediff > 2 AND date_confirmation between dates
                                $condition2->whereRaw('DATEDIFF(p.date, p.date_ajout) > 2')
                                    ->whereBetween('p.date_confirmation', [$startDate, $endDate]);
                            });
                        });
                });
            })
            ->first();
        return $result;
    }

    public function transformationRDV($userId)
    {
        [$startDate, $endDate] = $this->getDateRange();

        $result = DB::table('rendez_vous')
            ->selectRaw("COUNT(id) as cpt, SUM(CASE WHEN resultat = 'Vendu' THEN 1 ELSE 0 END) as vente")
            ->where(function ($query) use ($userId) {
                $query->where('id_commercial', $userId)
                    ->orWhere('partage_avec', $userId);
            })
            ->whereBetween('date', [$startDate, $endDate])
            ->first();

        $cpt = $result->cpt ?? 0;
        $vente = $result->vente ?? 0;

        return $cpt > 0 ? round(($vente / $cpt) * 100) : null;
    }

    public function deperditionRDV($userId)
    {
        [$startDate, $endDate] = $this->getDateRange();

        $result = DB::table('clients as c')
        ->leftJoin('projets as p', 'p.id_client', '=', 'c.id')
        ->select(
            DB::raw('COALESCE(SUM(p.rdv_annule), 0) as cpt'),
            DB::raw('COALESCE(SUM(p.rdv_confirme), 0) as rdv')
        )
        ->where('c.rentre_par', $userId)
        ->where(function($query) use ($startDate, $endDate) {
            $query
                ->where(function($q) use ($startDate, $endDate) {
                    $q->where('p.rdv_confirme', 0)
                    ->whereBetween('p.date_ajout', [$startDate, $endDate]);
                })
                ->orWhere(function($q) use ($startDate, $endDate) {
                    $q->where('p.rdv_confirme', 1)
                    ->where(function($q2) use($startDate, $endDate) {
                        $q2
                            ->whereRaw('DATEDIFF(p.date, p.date_ajout) <= 2')
                            ->whereBetween('p.date_ajout', [$startDate, $endDate])
                        ->orWhere(function($q3) use($startDate, $endDate) {
                            $q3
                                ->whereRaw('DATEDIFF(p.date, p.date_ajout) > 2')
                                ->whereBetween('p.date_confirmation', [$startDate, $endDate]);
                        });
                    });
                });
        })
        ->first();


        $cpt = $result->cpt ?? 0;
        $vente = $result->rdv ?? 0;

        return $cpt > 0 && $vente > 0 ? round(($cpt / $vente) * 100) : null;
    }

    public function calcMarge($userId) {
        [$startDate, $endDate] = $this->getDateRange();

        $result = DB::table('ventes')
            ->where('id_commercial', $userId)
            ->whereIn('type_vente', ['Commune', 'Normale'])
            ->whereBetween('date', [$startDate, $endDate])
            ->where('statut', '!=', 3)
            ->sum('montant');

        return $result;
    }
    // === MÉTHODES UTILITAIRES ===

    private function getDateRange()
    {
        $startDate = Carbon::createFromDate($this->year, $this->month, 1)->startOfMonth()->toDateString();
        $endDate = Carbon::createFromDate($this->year, $this->month, 1)->endOfMonth()->toDateString();
        return [$startDate, $endDate];
    }

    // === MÉTHODES DE CHARGEMENT PRINCIPALES ===

    public function loadGroupes()
    {
        [$startDate, $endDate] = $this->getDateRange();
        $user = Auth::user();

        $groupes = TableauChiffres::getGroupesForUser($user, $this->isAdmin, $this->isDirecteur, $this->isTelepro);
        $global = $this->initializeGlobalTotals();
        $globalSecteur = $this->initializeGlobalTotals();

        $groupesAvecUsers = $this->processGroupes($groupes, $startDate, $endDate, $user, $global, $globalSecteur);
        $this->finalizeGlobalTotals($global, $globalSecteur);

        foreach($groupesAvecUsers as $index => $groupe){
            if($groupe->id_secteur != session('selectedSecteur'))
                unset($groupesAvecUsers[$index]);
        }

        $this->groupes = $groupesAvecUsers;
        $this->totauxGlobaux = $global;
        $this->totauxSecteur = $globalSecteur;
    }

    public static function getGroupesForUser($user, $isAdmin, $isDirecteur, $isTelepro)
    {
        if ($isAdmin || $isDirecteur) {
            return DB::table('groupes')
                ->leftJoin('secteurs', 'groupes.secteur', '=', 'secteurs.id')
                ->select('groupes.*', 'secteurs.nom as nom_secteur', 'secteurs.id as id_secteur')
                ->orderBy('tri')->get();
        }

        if (DB::table('groupes')->where('id_responsable', $user->id)->exists()) {
            return DB::table('groupes')
                ->leftJoin('secteurs', 'groupes.secteur', '=', 'secteurs.id')
                ->select('groupes.*', 'secteurs.nom as nom_secteur', 'secteurs.id as id_secteur')
                ->where('id_responsable', $user->id)
                ->orderBy('tri')
                ->get();
        }

        if ($isTelepro) {
            return DB::table('groupes')
                ->leftJoin('secteurs', 'groupes.secteur', '=', 'secteurs.id')
                ->select('groupes.*', 'secteurs.nom as nom_secteur', 'secteurs.id as id_secteur')
                ->where('groupes.id', $user->groupe)
                ->orderBy('tri')->get();
        }

        return DB::table('groupes')
            ->leftJoin('secteurs', 'groupes.secteur', '=', 'secteurs.id')
            ->select('groupes.*', 'secteurs.nom as nom_secteur', 'secteurs.id as id_secteur')
            ->orderBy('tri')
            ->get();
    }

    private function initializeGlobalTotals()
    {
        return (object)[
            'somme_objectif_ca' => 0,
            'somme_objectif_rdv' => 0,
            'somme_ca_realise' => 0,
            'somme_rdv_total' => 0,
            'somme_argus_total' => 0,
            'somme_ca_par_semaine' => [0, 0, 0, 0, 0, 0],
            'somme_panier_moyen' => 0,
            'somme_ventes' => 0,
            'somme_cpt' => 0,
            'somme_rdv' => 0,
            'somme_rdv_effectues' => 0,
            'somme_transformation' => null,
            'somme_deperdition' => null,
            'somme_rdv48h' => 0,
            'somme_marge' => 0,
            'denominateur' => 0
        ];
    }

    private function processGroupes($groupes, $startDate, $endDate, $user, $global, $globalSecteur)
    {
        $groupesAvecUsers = collect();

        foreach ($groupes as $groupe) {

            $this->loadGroupeUsers($groupe, $startDate, $endDate, $user);
            $this->calculateGroupeData($groupe);
            $this->updateGlobalTotals($global, $groupe, $globalSecteur);
            $groupesAvecUsers->push($groupe);
        }

        return $groupesAvecUsers;
    }

    private function loadGroupeUsers($groupe, $startDate, $endDate, $user)
    {
        $usersQuery = $this->buildUsersQuery($groupe, $startDate, $endDate, $user, $this->isAdmin, $this->isDirecteur, $this->isTelepro);
        $groupe->users = $usersQuery->get();
        $this->initializeGroupeSums($groupe);
    }

    public static function buildUsersQuery($groupe, $startDate, $endDate, $user, $isAdmin, $isDirecteur, $isTelepro)
    {
        $usersQuery = User::select('users.id', 'users.name', 'users.nom', 'users.prenom', 'users.objectif_ca',
            'obj.ca as obj_objectif_ca', 'users.objectif_rdv', 'type', 'directeur', 'groupe', 'telepro', 'prospecteur', 
            'junior', 'role', 'contrat', 'users.active', 'nouveau_vendeur', 'date_entree')
            ->leftJoin('objectifs as obj', function($join) use($startDate){
                $join->on('obj.id_commercial', '=', 'users.id')
                ->where('obj.date', '=', $startDate);
            })
            ->where('groupe', $groupe->id)
            /*->whereDate('date_entree', '<=', $endDate)
            ->where(function ($q) use ($startDate) {
                $q->whereNull('delete')->orWhereDate('delete', '>=', $startDate);
            })*/
            ->where(function($query) use ($startDate, $endDate, $groupe) {
                $query->where('users.active', '1')
                ->orWhere(function($q) use ($startDate, $endDate, $groupe) {
                    $q->whereIn('users.id', function($subquery) use ($startDate, $endDate, $groupe) {
                        $subquery->select(DB::raw('DISTINCT id_commercial'))
                            ->from('ventes')
                            ->whereBetween('date', [
                                $startDate,
                                $endDate
                            ])
                            ->where('secteur', $groupe->secteur);
                    })
                    ->where('users.active', '0');
                });
            })
            ->join('secteur_commercials as secteur', function($join) use($groupe){
                $join->on('secteur.id_commercial', '=', 'users.id')
                ->where('secteur.id_secteur', '=', $groupe->secteur);
            })
            ->orderByDesc('active')
            ->orderByRaw('ISNULL(date_entree) ASC')
            ->orderBy('date_entree')
            ->orderBy('nom');

        if (!$isAdmin && !$isDirecteur) {
            if ($groupe->id !== null) {
                $usersQuery->where('groupe', $groupe->id);
            } else {
                $usersQuery->where('users.id', $user->id);
            }
        }

        if ($isTelepro) {
            $usersQuery->where('users.id', $user->id);
        }

        return $usersQuery;
    }

    private function initializeGroupeSums($groupe)
    {
        $groupe->somme_rdv_effectues = 0;
        $groupe->somme_ventes = 0;
        $groupe->somme_cpt = 0;
        $groupe->somme_rdv = 0;
        $groupe->somme_rdv48h = 0;
    }

    private function calculateGroupeData($groupe)
    {
        foreach ($groupe->users as $index => $user) {
            
            $this->calculateUserData($user, $groupe);
            /*if($user->nb_ventes == 0)
                unset($groupe->users[$index]);*/
        }

        $this->calculateGroupeSums($groupe);
    }

    private function calculateUserData(&$user, $groupe)
    {
        // Initialisation des objectifs
        $user->objectif_ca = $user->obj_objectif_ca == 0 ? $user->objectif_ca : $user->obj_objectif_ca;
        $user->objectif_rdv = $user->objectif_rdv ?? 0;

        // Calculs principaux
        $user->ca_realise = $this->calculerCaRealise($user->id);
        $user->rdv_total = $this->calculerNombreRdv($user->id, $groupe);
        $user->argus_total = $this->calculerArgusTotal($user->id);
        $user->ca_par_semaine = $this->calculerCaParSemaine($user->id);
        $user->transformation = $this->transformationRDV($user->id);
        $user->nb_ventes = $this->calculerNombreVentes($user->id);
        $user->deperditionData = $this->calculerDeperdition($user->id, $groupe);
        $user->marge = $this->calcMarge($user->id);

        $user->deperdition = $this->deperditionRDV($user->id);

        // Calculs dérivés
        $count_ventes = $this->calculerPaniermoyen($user->id);
        $user->denominateur = 0;
        $user->denominateurGroupe = 0;
        foreach($count_ventes as $vente){
            if($vente->type_vente == "Commune"){
                $user->denominateur += $vente->total;
                $user->denominateurGroupe += $vente->total / 2;
			}else{
                $user->denominateur += $vente->total;
                $user->denominateurGroupe += $vente->total;
			}
        }
        
        $user->panier_moyen = $user->denominateur > 0 ? $user->ca_realise / $user->denominateur : 0;

        $rdv48h = $this->rdv48hDernieres($user->id);
        $user->rdv48h = $user->rdv_total > 0 ? round(($rdv48h / $user->rdv_total) * 100) : 0;

        /*$user->deperdition = ($user->rdv_total && $user->nb_ventes !== null)
            ? round((($user->rdv_total - $user->nb_ventes) / $user->rdv_total) * 100)
            : null;*/

        // Période d'essai
        $user->is_fin_periode_essai = $this->isFinPeriodeEssai($user->date_entree);

        // Mise à jour des sommes du groupe
        $groupe->somme_rdv48h += $rdv48h;
        $groupe->somme_cpt += $user->deperditionData->cpt ?? 0;
        $groupe->somme_rdv += $user->deperditionData->rdv ?? 0;
        $groupe->somme_rdv_effectues += $user->rdv_total ?? 0;

    }

    private function isFinPeriodeEssai($dateEntree)
    {
        $dateEntree = Carbon::parse($dateEntree)->startOfDay();
        $debut = now()->startOfDay()->subMonths(3);
        $fin = now()->startOfDay()->subMonths(3)->addDays(21);
        return $dateEntree->between($debut, $fin);
    }

    private function calculateGroupeSums(&$groupe)
    {
        $groupe->somme_objectif_ca = $groupe->users->sum('objectif_ca');
        $groupe->somme_objectif_rdv = $groupe->users->sum('objectif_rdv');
        $groupe->somme_ca_realise = $groupe->users->sum('ca_realise');
        $groupe->somme_rdv_total = $groupe->users->sum('rdv_total');
        $groupe->somme_argus_total = $groupe->users->sum('argus_total');
        $groupe->somme_ca_par_semaine = [0, 0, 0, 0, 0, 0];
        $groupe->somme_marge = $groupe->users->sum('marge');
        $groupe->somme_transformation = $groupe->somme_rdv_effectues > 0
            ? round(($groupe->somme_ventes / $groupe->somme_rdv_effectues) * 100)
            : null;

        $groupe->somme_deperdition = $groupe->somme_rdv > 0
            ? round((($groupe->somme_cpt) / $groupe->somme_rdv) * 100)
            : 100;

        $groupe->pourcentage_rdv48h = $groupe->somme_rdv_effectues > 0
            ? round(($groupe->somme_rdv48h / $groupe->somme_rdv_effectues) * 100, 2)
            : 0;

        $groupe_denominateur = 0;
        foreach ($groupe->users as $user){
            $groupe_denominateur += $user->denominateurGroupe;
            foreach ($user->ca_par_semaine as $i => $val) {
                $groupe->somme_ca_par_semaine[$i] += $val;
            }
        }
        $groupe->denominateur = $groupe_denominateur;
        $groupe->somme_panier_moyen = $groupe_denominateur > 0 ? $groupe->somme_ca_realise / $groupe_denominateur : 0;
    }

    private function updateGlobalTotals($global, $groupe, $globalSecteur)
    {
        $global->somme_objectif_ca += $groupe->somme_objectif_ca;
        $global->somme_objectif_rdv += $groupe->somme_objectif_rdv;
        $global->somme_ca_realise += $groupe->somme_ca_realise;
        $global->somme_rdv_total += $groupe->somme_rdv_total;
        $global->somme_argus_total += $groupe->somme_argus_total;
        $global->somme_ventes += $groupe->somme_ventes;
        $global->somme_cpt += $groupe->somme_cpt;
        $global->somme_rdv += $groupe->somme_rdv;
        $global->somme_rdv_effectues += $groupe->somme_rdv_effectues;
        $global->somme_rdv48h += $groupe->somme_rdv48h;
        $global->somme_marge += $groupe->somme_marge;
        $global->denominateur += $groupe->denominateur;
        $global->somme_panier_moyen = ($global->denominateur > 0) ? $global->somme_ca_realise / $global->denominateur : 0;

        foreach($groupe->somme_ca_par_semaine as $i => $val){
            $global->somme_ca_par_semaine[$i] += $val;
        }

        if($groupe->id_secteur == session('selectedSecteur')){
            $globalSecteur->somme_objectif_ca += $groupe->somme_objectif_ca;
            $globalSecteur->somme_objectif_rdv += $groupe->somme_objectif_rdv;
            $globalSecteur->somme_ca_realise += $groupe->somme_ca_realise;
            $globalSecteur->somme_rdv_total += $groupe->somme_rdv_total;
            $globalSecteur->somme_argus_total += $groupe->somme_argus_total;
            $globalSecteur->somme_ventes += $groupe->somme_ventes;
            $globalSecteur->somme_cpt += $groupe->somme_cpt;
            $globalSecteur->somme_rdv += $groupe->somme_rdv;
            $globalSecteur->somme_rdv_effectues += $groupe->somme_rdv_effectues;
            $globalSecteur->somme_rdv48h += $groupe->somme_rdv48h;
            $globalSecteur->somme_marge += $groupe->somme_marge;
            $globalSecteur->denominateur += $groupe->denominateur;
            $globalSecteur->somme_panier_moyen = ($globalSecteur->denominateur > 0 ) ? $globalSecteur->somme_ca_realise / $globalSecteur->denominateur : 0;
            foreach($groupe->somme_ca_par_semaine as $i => $val){
                $globalSecteur->somme_ca_par_semaine[$i] += $val;
            }
        }
        
    }

    private function finalizeGlobalTotals($global, $globalSecteur)
    {
        $global->somme_transformation = $global->somme_rdv_effectues > 0
            ? round(($global->somme_ventes / $global->somme_rdv_effectues) * 100)
            : null;

        $global->somme_deperdition = $global->somme_rdv > 0
            ? round((($global->somme_cpt) / $global->somme_rdv) * 100)
            : 100;

        $global->pourcentage_rdv48h = $global->somme_rdv_effectues > 0
            ? round(($global->somme_rdv48h / $global->somme_rdv_effectues) * 100, 2)
            : 0;

        $globalSecteur->somme_transformation = $globalSecteur->somme_rdv_effectues > 0
            ? round(($globalSecteur->somme_ventes / $globalSecteur->somme_rdv_effectues) * 100)
            : null;

        $globalSecteur->somme_deperdition = $globalSecteur->somme_rdv > 0
            ? round((($globalSecteur->somme_cpt) / $globalSecteur->somme_rdv) * 100)
            : 100;

        $globalSecteur->pourcentage_rdv48h = $globalSecteur->somme_rdv_effectues > 0
            ? round(($globalSecteur->somme_rdv48h / $globalSecteur->somme_rdv_effectues) * 100, 2)
            : 0;
            
    }

    public function render()
    {
        return view('livewire.tableau-chiffre.tableau-chiffres');
    }
}
