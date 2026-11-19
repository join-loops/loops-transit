<?php

namespace App\Transit\Log;

use App\Transit\TransitException;

abstract class TreeReader
{
    abstract protected function node(int $level, int $index): string;

    public static function leafHash(string $data): string
    {
        return hash('sha256', "\x00".$data, true);
    }

    public static function nodeHash(string $left, string $right): string
    {
        return hash('sha256', "\x01".$left.$right, true);
    }

    public static function split(int $size): int
    {
        $k = 1;

        while (($k << 1) < $size) {
            $k <<= 1;
        }

        return $k;
    }

    public function root(int $size): string
    {
        return $this->rangeHash(0, $size);
    }

    public function rangeHash(int $start, int $size): string
    {
        if ($size === 0) {
            return hash('sha256', '', true);
        }

        if (($size & ($size - 1)) === 0 && $start % $size === 0) {
            return $this->node(strlen(decbin($size)) - 1, intdiv($start, $size));
        }

        $k = self::split($size);

        return self::nodeHash($this->rangeHash($start, $k), $this->rangeHash($start + $k, $size - $k));
    }

    public function inclusionProof(int $index, int $size): array
    {
        if ($index < 0 || $index >= $size) {
            throw new TransitException('The leaf index is outside the tree', 'out_of_range', 400);
        }

        return $this->path($index, 0, $size);
    }

    public function consistencyProof(int $first, int $second): array
    {
        if ($first < 1 || $first > $second) {
            throw new TransitException('The tree sizes are not valid for a consistency proof', 'out_of_range', 400);
        }

        return $first === $second ? [] : $this->subproof($first, 0, $second, true);
    }

    protected function path(int $index, int $start, int $size): array
    {
        if ($size === 1) {
            return [];
        }

        $k = self::split($size);

        if ($index < $k) {
            return [...$this->path($index, $start, $k), $this->rangeHash($start + $k, $size - $k)];
        }

        return [...$this->path($index - $k, $start + $k, $size - $k), $this->rangeHash($start, $k)];
    }

    protected function subproof(int $first, int $start, int $size, bool $complete): array
    {
        if ($first === $size) {
            return $complete ? [] : [$this->rangeHash($start, $size)];
        }

        $k = self::split($size);

        if ($first <= $k) {
            return [...$this->subproof($first, $start, $k, $complete), $this->rangeHash($start + $k, $size - $k)];
        }

        return [...$this->subproof($first - $k, $start + $k, $size - $k, false), $this->rangeHash($start, $k)];
    }
}
