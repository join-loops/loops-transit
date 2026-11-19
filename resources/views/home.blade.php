@extends('layouts.app')

@section('content')
    <section>
        <h1 class="text-3xl font-semibold tracking-tight">A witness for Loops account migrations</h1>
        <p class="mt-4 leading-relaxed text-stone-600 dark:text-stone-300">
            When an account moves between Loops servers, both servers sign what happened. This Transit checks those signatures,
            records the keys that made them and adds each step to a public, append-only log. It never sees videos or profiles,
            only the signed intent and receipt, so a move stays verifiable even after either server rotates its keys or goes offline.
        </p>
    </section>

    <section class="mt-10 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="rounded-xl border border-stone-200 bg-white p-4 dark:border-stone-800 dark:bg-stone-900">
            <div class="text-xs text-stone-500 dark:text-stone-400">Log entries</div>
            <div class="mt-1 text-2xl font-semibold">{{ number_format($state['size']) }}</div>
        </div>
        <div class="rounded-xl border border-stone-200 bg-white p-4 dark:border-stone-800 dark:bg-stone-900">
            <div class="text-xs text-stone-500 dark:text-stone-400">Sealed moves</div>
            <div class="mt-1 text-2xl font-semibold">{{ number_format($sealed) }}</div>
        </div>
        <div class="col-span-2 rounded-xl border border-stone-200 bg-white p-4 dark:border-stone-800 dark:bg-stone-900">
            <div class="text-xs text-stone-500 dark:text-stone-400">Tree root</div>
            <div class="mt-2 truncate font-mono text-sm" title="sha256:{{ $state['root'] }}">sha256:{{ $state['root'] }}</div>
        </div>
    </section>

    <section class="mt-10">
        <h2 class="text-lg font-semibold">Look up a migration</h2>
        <p class="mt-1 text-sm text-stone-600 dark:text-stone-300">Paste the intent JWS or its SHA-256 hash. Records are only shown to people who already hold the intent.</p>
        <form method="POST" action="{{ route('lookup.search') }}" class="mt-4 flex gap-2">
            @csrf
            <input name="q" required autocomplete="off" spellcheck="false" placeholder="sha256:... or eyJhbGciOi..."
                class="min-w-0 flex-1 rounded-lg border border-stone-300 bg-white px-3 py-2 font-mono text-sm outline-none focus:border-stone-500 dark:border-stone-700 dark:bg-stone-900">
            <button class="rounded-lg bg-stone-900 px-4 py-2 text-sm font-medium text-white hover:bg-stone-700 dark:bg-white dark:text-stone-900 dark:hover:bg-stone-200">Look up</button>
        </form>
    </section>

    <section class="mt-10">
        <h2 class="text-lg font-semibold">Use this Transit</h2>
        <p class="mt-2 text-sm leading-relaxed text-stone-600 dark:text-stone-300">
            Add <code class="rounded bg-stone-200/70 px-1.5 py-0.5 font-mono text-xs dark:bg-stone-800">{{ $transit }}</code>
            to the Transit list in your Loops admin migration settings. A move is witnessed here when both servers list this Transit.
            @if ($requireAdvertised)
                Both servers must show it in their NodeInfo, which Loops does automatically once it is in the list.
            @endif
        </p>
    </section>

    <section class="mt-10">
        <h2 class="text-lg font-semibold">Signing keys</h2>
        <div class="mt-3 overflow-hidden rounded-xl border border-stone-200 dark:border-stone-800">
            <table class="w-full text-left text-sm">
                <thead class="bg-stone-100 text-xs text-stone-500 dark:bg-stone-900 dark:text-stone-400">
                    <tr>
                        <th class="px-4 py-2 font-medium">Key ID</th>
                        <th class="px-4 py-2 font-medium">Status</th>
                        <th class="px-4 py-2 font-medium">Since</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-200 bg-white dark:divide-stone-800 dark:bg-stone-950">
                    @forelse ($keys as $key)
                        <tr>
                            <td class="px-4 py-2 font-mono text-xs">{{ $key->kid }}</td>
                            <td class="px-4 py-2">
                                <span @class([
                                    'rounded-full px-2 py-0.5 text-xs',
                                    'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300' => $key->isActive(),
                                    'bg-stone-200 text-stone-600 dark:bg-stone-800 dark:text-stone-300' => ! $key->isActive(),
                                ])>{{ $key->status }}</span>
                            </td>
                            <td class="px-4 py-2 text-xs text-stone-500 dark:text-stone-400">{{ $key->activated_at?->toDateString() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-4 py-3 text-sm text-stone-500">No signing key yet. The operator needs to run <code class="font-mono">php artisan transit:key:generate</code>.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="mt-10">
        <h2 class="text-lg font-semibold">Audit the log</h2>
        <dl class="mt-3 space-y-3 text-sm">
            <div>
                <dt class="font-mono text-xs">GET /api/v1/log</dt>
                <dd class="text-stone-600 dark:text-stone-300">The latest signed tree head.</dd>
            </div>
            <div>
                <dt class="font-mono text-xs">GET /api/v1/log/entries?start=0&amp;end=99</dt>
                <dd class="text-stone-600 dark:text-stone-300">Log entries. They hold hashes, server names, key fingerprints and timestamps, never account addresses.</dd>
            </div>
            <div>
                <dt class="font-mono text-xs">GET /api/v1/log/proof/inclusion?index=0</dt>
                <dd class="text-stone-600 dark:text-stone-300">Proof that an entry is in the tree.</dd>
            </div>
            <div>
                <dt class="font-mono text-xs">GET /api/v1/log/proof/consistency?first=1&amp;second=2</dt>
                <dd class="text-stone-600 dark:text-stone-300">Proof that a newer tree only added entries to an older one.</dd>
            </div>
        </dl>
    </section>
@endsection
