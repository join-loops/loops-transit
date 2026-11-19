<?php

namespace App\Console\Commands;

use App\Models\LogLeaf;
use App\Models\TreeHead;
use App\Transit\Json;
use App\Transit\KeyRing;
use App\Transit\Log\MerkleLog;
use App\Transit\Log\TreeReader;
use App\Transit\Transit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VerifyLogCommand extends Command
{
    protected $signature = 'transit:log:verify';

    protected $description = 'Recompute the whole log and check it against stored nodes and signed tree heads';

    protected array $problems = [];

    public function handle(MerkleLog $log, KeyRing $keys): int
    {
        $stack = [];
        $size = 0;
        $heads = 0;

        $this->checkHead($keys, 0, hash('sha256', ''), $heads);

        foreach (LogLeaf::orderBy('leaf_index')->lazyById(500, 'leaf_index') as $leaf) {
            if ($leaf->leaf_index !== $size) {
                $this->problem('Leaf '.$size.' is missing');

                break;
            }

            $hash = TreeReader::leafHash(Json::canonical($leaf->data));

            if (bin2hex($hash) !== $leaf->leaf_hash) {
                $this->problem('Leaf '.$size.' does not match its stored hash');
            }

            $level = 0;
            $this->checkNode(0, $size, $hash);

            while ($stack !== [] && end($stack)[0] === $level) {
                [, $left] = array_pop($stack);
                $hash = TreeReader::nodeHash($left, $hash);
                $level++;
                $this->checkNode($level, $size >> $level, $hash);
            }

            $stack[] = [$level, $hash];
            $size++;

            $this->checkHead($keys, $size, $this->fold($stack), $heads);
        }

        $state = $log->state();
        $finalRoot = $size === 0 ? hash('sha256', '') : $this->fold($stack);

        if ($state['size'] !== $size || $state['root'] !== $finalRoot) {
            $this->problem('The log state does not match the recomputed tree');
        }

        if ($this->problems !== []) {
            foreach (array_slice($this->problems, 0, 50) as $problem) {
                $this->components->error($problem);
            }

            $this->components->error(count($this->problems).' problem(s) found.');

            return self::FAILURE;
        }

        $this->components->info('Checked '.$size.' leaves and '.$heads.' signed tree heads. Root '.Transit::prefixed($finalRoot));

        return self::SUCCESS;
    }

    protected function fold(array $stack): string
    {
        $root = null;

        foreach (array_reverse($stack) as [, $subtree]) {
            $root = $root === null ? $subtree : TreeReader::nodeHash($subtree, $root);
        }

        return bin2hex($root);
    }

    protected function checkNode(int $level, int $index, string $hash): void
    {
        $stored = DB::table('log_nodes')->where('level', $level)->where('node_index', $index)->value('hash');

        if ($stored !== bin2hex($hash)) {
            $this->problem('Node '.$level.':'.$index.' does not match the recomputed tree');
        }
    }

    protected function checkHead(KeyRing $keys, int $size, string $root, int &$heads): void
    {
        $head = TreeHead::find($size);

        if (! $head) {
            return;
        }

        $heads++;
        $payload = $keys->verify($head->jws);

        if ($head->root !== $root) {
            $this->problem('Tree head '.$size.' has the wrong root');
        }

        if (! $payload || ($payload['size'] ?? null) !== $size || Transit::unprefixed($payload['root'] ?? null) !== $root) {
            $this->problem('Tree head '.$size.' has an invalid signature or payload');
        }
    }

    protected function problem(string $message): void
    {
        $this->problems[] = $message;
    }
}
