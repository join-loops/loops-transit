<?php

namespace App\Transit\Log;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class DatabaseTreeReader extends TreeReader
{
    protected array $nodes = [];

    protected function node(int $level, int $index): string
    {
        $key = $level.':'.$index;

        if (! isset($this->nodes[$key])) {
            $hash = DB::table('log_nodes')->where('level', $level)->where('node_index', $index)->value('hash');

            if (! is_string($hash)) {
                throw new RuntimeException('The log is missing node '.$key);
            }

            $this->nodes[$key] = hex2bin($hash);
        }

        return $this->nodes[$key];
    }
}
