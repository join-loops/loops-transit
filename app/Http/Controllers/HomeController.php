<?php

namespace App\Http\Controllers;

use App\Models\AccountMigration;
use App\Transit\KeyRing;
use App\Transit\Log\MerkleLog;
use App\Transit\Transit;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __invoke(KeyRing $keys, MerkleLog $log): View
    {
        return view('home', [
            'transit' => Transit::url(),
            'operator' => (array) config('transit.operator'),
            'state' => $log->state(),
            'sealed' => AccountMigration::whereNotNull('sealed_at')->count(),
            'keys' => $keys->all(),
            'requireAdvertised' => (bool) config('transit.require_advertised'),
        ]);
    }
}
