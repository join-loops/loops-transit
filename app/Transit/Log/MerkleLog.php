<?php

namespace App\Transit\Log;

use App\Models\LogLeaf;
use App\Models\TreeHead;
use App\Transit\Json;
use App\Transit\KeyRing;
use App\Transit\Transit;
use App\Transit\TransitException;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MerkleLog
{
    public const HEAD_TYPE = 'TransitTreeHead';

    public const MAX_ENTRIES = 1000;

    public function __construct(protected KeyRing $keys) {}

    public function locked(Closure $callback): mixed
    {
        $attempts = 0;

        while (true) {
            try {
                return DB::transaction(function () use ($callback) {
                    DB::table('log_state')->where('id', 1)->update(['updated_at' => now()]);

                    return $callback();
                }, 3);
            } catch (UniqueConstraintViolationException $e) {
                if (++$attempts >= 5) {
                    throw $e;
                }

                usleep(random_int(5000, 50000));
            }
        }
    }

    public function append(string $kind, array $data): array
    {
        if (DB::transactionLevel() === 0) {
            return $this->locked(fn () => $this->append($kind, $data));
        }

        $index = $this->size();
        $leafHash = TreeReader::leafHash(Json::canonical($data));
        $now = now();

        LogLeaf::create([
            'leaf_index' => $index,
            'kind' => $kind,
            'leaf_hash' => bin2hex($leafHash),
            'data' => $data,
            'created_at' => $now,
        ]);

        $nodes = [['level' => 0, 'node_index' => $index, 'hash' => bin2hex($leafHash)]];
        $hash = $leafHash;
        $level = 0;
        $position = $index;

        while ($position % 2 === 1) {
            $left = DB::table('log_nodes')->where('level', $level)->where('node_index', $position - 1)->value('hash');
            $hash = TreeReader::nodeHash(hex2bin((string) $left), $hash);
            $level++;
            $position = intdiv($position, 2);
            $nodes[] = ['level' => $level, 'node_index' => $position, 'hash' => bin2hex($hash)];
        }

        DB::table('log_nodes')->insert($nodes);

        $size = $index + 1;
        $root = bin2hex((new DatabaseTreeReader)->root($size));

        DB::table('log_state')->where('id', 1)->update([
            'size' => $size,
            'root' => $root,
            'updated_at' => $now,
        ]);

        $this->storeHead($size, $root, $now);

        return [
            'index' => $index,
            'size' => $size,
            'root' => $root,
            'leaf' => bin2hex($leafHash),
        ];
    }

    public function size(): int
    {
        return (int) DB::table('log_state')->where('id', 1)->value('size');
    }

    public function state(): array
    {
        $state = DB::table('log_state')->where('id', 1)->first();

        return [
            'size' => (int) ($state->size ?? 0),
            'root' => (string) ($state->root ?? hash('sha256', '')),
        ];
    }

    public function head(): TreeHead
    {
        $state = $this->state();
        $head = TreeHead::find($state['size']);

        if ($head) {
            return $head;
        }

        return $this->locked(fn () => TreeHead::find($state['size']) ?? $this->storeHead($state['size'], $state['root'], now()));
    }

    public function entries(int $start, int $end): Collection
    {
        if ($start < 0 || $end < $start) {
            throw new TransitException('The entry range is not valid', 'out_of_range', 400);
        }

        return LogLeaf::whereBetween('leaf_index', [$start, min($end, $start + self::MAX_ENTRIES - 1)])
            ->orderBy('leaf_index')
            ->get();
    }

    public function inclusion(int $index, ?int $size = null): array
    {
        $size = $this->checkedSize($size);
        $leaf = LogLeaf::find($index);

        if (! $leaf || $index >= $size) {
            throw new TransitException('The leaf is not in a tree of that size', 'out_of_range', 400);
        }

        $reader = new DatabaseTreeReader;

        return [
            'index' => $index,
            'size' => $size,
            'leafHash' => Transit::prefixed($leaf->leaf_hash),
            'root' => Transit::prefixed(bin2hex($reader->root($size))),
            'proof' => array_map('bin2hex', $reader->inclusionProof($index, $size)),
        ];
    }

    public function consistency(int $first, ?int $second = null): array
    {
        $second = $this->checkedSize($second);
        $reader = new DatabaseTreeReader;

        if ($first < 1 || $first > $second) {
            throw new TransitException('The tree sizes are not valid for a consistency proof', 'out_of_range', 400);
        }

        return [
            'first' => $first,
            'second' => $second,
            'firstRoot' => Transit::prefixed(bin2hex($reader->root($first))),
            'secondRoot' => Transit::prefixed(bin2hex($reader->root($second))),
            'proof' => array_map('bin2hex', $reader->consistencyProof($first, $second)),
        ];
    }

    public function findLeaf(string $hash): ?LogLeaf
    {
        $hash = Transit::unprefixed($hash);

        return $hash ? LogLeaf::where('leaf_hash', $hash)->orderBy('leaf_index')->first() : null;
    }

    protected function checkedSize(?int $size): int
    {
        $current = $this->size();
        $size ??= $current;

        if ($size < 1 || $size > $current) {
            throw new TransitException('The log does not have a tree of that size', 'out_of_range', 400);
        }

        return $size;
    }

    protected function storeHead(int $size, string $root, CarbonInterface $time): TreeHead
    {
        $jws = $this->keys->sign([
            'type' => self::HEAD_TYPE,
            'version' => Transit::PROTOCOL_VERSION,
            'transit' => Transit::url(),
            'size' => $size,
            'root' => Transit::prefixed($root),
            'timestamp' => $time->toIso8601ZuluString(),
        ]);

        return TreeHead::create([
            'size' => $size,
            'root' => $root,
            'jws' => $jws,
            'created_at' => $time,
        ]);
    }
}
