<?php
namespace SerbenConnect\Domain;

if (!defined('ABSPATH')) { exit; }

class Member
{
    private $profile;
    private $plan;
    private $card;
    private $points;
    private $cashback;
    private $financial;
    private $club;
    private $raw;

    public function __construct(array $cliente = [], array $clubData = [], array $clubMeta = [])
    {
        $this->raw = ['cliente' => $cliente, 'club' => $clubData, 'club_meta' => $clubMeta];
        $this->profile = new Profile($cliente);
        $this->club = new Club($clubData, $clubMeta);
        $this->plan = new Plan($cliente, $clubData);
        $this->card = new Card($cliente, $clubData);
        $this->points = new Points($cliente, $clubData);
        $this->cashback = new Cashback($cliente, $clubData);
        $this->financial = new Financial($cliente, $clubData);
    }

    public function profile(): Profile { return $this->profile; }
    public function club(): Club { return $this->club; }
    public function hasClub(): bool { return $this->club->isLinked(); }
    public function plan(): Plan { return $this->plan; }
    public function card(): Card { return $this->card; }
    public function points(): Points { return $this->points; }
    public function cashback(): Cashback { return $this->cashback; }
    public function financial(): Financial { return $this->financial; }
    public function raw(): array { return $this->raw; }
    public function get(string $path, $default = '') { return DataExtractor::get($this->raw, $path, $default); }
}
