<?php

namespace App\Http\Controllers;

use App\Models\AccountMigration;
use App\Transit\Transit;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LookupController extends Controller
{
    public function show(Request $request): View
    {
        $hash = Transit::unprefixed($request->query('hash'));

        return view('lookup', [
            'hash' => $hash,
            'migration' => $hash ? AccountMigration::where('intent_hash', $hash)->first() : null,
        ]);
    }

    public function search(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'max:'.config('transit.max_jws_bytes')],
        ]);

        $query = trim($data['q']);
        $hash = substr_count($query, '.') === 2 ? hash('sha256', $query) : Transit::unprefixed($query);

        if ($hash === null) {
            return back()->withErrors(['q' => 'Paste an intent hash or the intent JWS.'])->withInput();
        }

        return redirect()->route('lookup', ['hash' => $hash]);
    }
}
