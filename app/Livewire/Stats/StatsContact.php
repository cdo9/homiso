<?php

namespace App\Livewire\Stats;

use Livewire\Component;
use App\Services\CommerciauxService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class StatsContact extends Component
{
    public $dateDebut;
    public $dateFin;
    public $commerciaux = [];
    public $statsData = [];

    public function mount()
    {
        $this->dateDebut = $this->dateDebut ?: Carbon::now()->startOfMonth()->toDateString();
        $this->dateFin = $this->dateFin ?: Carbon::now()->endOfMonth()->toDateString();
        $this->loadCommerciaux();
        $this->loadStats();
    }

    public function updatedDateDebut()
    {
        $this->loadStats();
    }

    public function updatedDateFin()
    {
        $this->loadStats();
    }

    private function loadCommerciaux()
    {
        $this->commerciaux = CommerciauxService::getCommerciauxListBySector();
    }

    private function loadStats()
    {
        $this->statsData = [];

        foreach ($this->commerciaux as $commercial) {
            $stats = DB::table('clients')
                ->select('type', DB::raw('count(1) as cpt'))
                ->whereBetween('rentre_le', [$this->dateDebut, $this->dateFin])
                ->where('rentre_par', $commercial->id)
                ->groupBy('type')
                ->pluck('cpt', 'type')
                ->toArray();

            $total = array_sum($stats);

            $this->statsData[$commercial->id] = [
                'commercial' => $commercial,
                'stats' => $stats,
                'total' => $total
            ];
        }
    }

    private function getClientsTypes(): array
    {
        return [
            6 => 'Refus',
            7 => 'Absent',
            8 => 'Relance',
            0 => 'Précontact',
            1 => 'Prospect',
            2 => 'Client',
            3 => 'Annulé'
        ];
    }

    private function getCommercialNom($commercial)
    {
        return $commercial->prenom . ' ' . $commercial->nom;
    }

    public function render()
    {
        $types = $this->getClientsTypes();

        return view('livewire.stats.stats-contact', [
            'types' => $types
        ]);
    }
}
