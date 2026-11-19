@extends('layouts.app')

@section('title', 'Lookup · '.config('transit.operator.name'))

@section('content')
    <h1 class="text-2xl font-semibold tracking-tight">Look up a migration</h1>

    <form method="POST" action="{{ route('lookup.search') }}" class="mt-6 flex gap-2">
        @csrf
        <input name="q" required autocomplete="off" spellcheck="false" value="{{ old('q', $hash ? 'sha256:'.$hash : '') }}" placeholder="sha256:... or eyJhbGciOi..."
            class="min-w-0 flex-1 rounded-lg border border-stone-300 bg-white px-3 py-2 font-mono text-sm outline-none focus:border-stone-500 dark:border-stone-700 dark:bg-stone-900">
        <button class="rounded-lg bg-stone-900 px-4 py-2 text-sm font-medium text-white hover:bg-stone-700 dark:bg-white dark:text-stone-900 dark:hover:bg-stone-200">Look up</button>
    </form>

    @error('q')
        <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror

    @if ($hash && ! $migration)
        <div class="mt-8 rounded-xl border border-stone-200 bg-white p-5 text-sm text-stone-600 dark:border-stone-800 dark:bg-stone-900 dark:text-stone-300">
            This Transit has no record of that intent.
        </div>
    @elseif ($migration)
        <div class="mt-8 rounded-xl border border-stone-200 bg-white dark:border-stone-800 dark:bg-stone-900">
            <div class="flex items-center justify-between gap-4 border-b border-stone-200 p-5 dark:border-stone-800">
                <div class="text-lg font-semibold">{{ $migration->source_host }} <span class="text-stone-400">&rarr;</span> {{ $migration->destination_host }}</div>
                <span @class([
                    'rounded-full px-2.5 py-0.5 text-xs font-medium',
                    'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300' => $migration->isSealed(),
                    'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300' => ! $migration->isSealed(),
                ])>{{ $migration->isSealed() ? 'Sealed' : 'Intent received' }}</span>
            </div>
            <dl class="grid gap-4 p-5 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-xs text-stone-500 dark:text-stone-400">Intent logged</dt>
                    <dd>{{ $migration->intent_received_at ? $migration->intent_received_at->toDayDateTimeString().' UTC, entry '.$migration->intent_leaf : 'Not submitted by the source' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-stone-500 dark:text-stone-400">Sealed</dt>
                    <dd>{{ $migration->sealed_at ? $migration->sealed_at->toDayDateTimeString().' UTC, entry '.$migration->seal_leaf : 'Waiting for the destination receipt' }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-xs text-stone-500 dark:text-stone-400">Source key</dt>
                    <dd class="break-all font-mono text-xs">{{ $migration->intent_key_fingerprint }}</dd>
                </div>
                @if ($migration->receipt_key_fingerprint)
                    <div class="sm:col-span-2">
                        <dt class="text-xs text-stone-500 dark:text-stone-400">Destination key</dt>
                        <dd class="break-all font-mono text-xs">{{ $migration->receipt_key_fingerprint }}</dd>
                    </div>
                @endif
            </dl>
            @if ($migration->seal_jws)
                <details class="border-t border-stone-200 p-5 dark:border-stone-800">
                    <summary class="cursor-pointer text-sm font-medium">Seal</summary>
                    <pre class="mt-3 overflow-x-auto whitespace-pre-wrap break-all rounded-lg bg-stone-100 p-3 font-mono text-xs dark:bg-stone-950">{{ $migration->seal_jws }}</pre>
                </details>
            @endif
            <div class="border-t border-stone-200 p-5 text-xs text-stone-500 dark:border-stone-800 dark:text-stone-400">
                JSON: <a class="underline" href="{{ route('migrations.show', ['hash' => $migration->intent_hash]) }}">{{ route('migrations.show', ['hash' => $migration->intent_hash]) }}</a>
            </div>
        </div>
    @endif
@endsection
