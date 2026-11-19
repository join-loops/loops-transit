<?php

namespace App\Transit\Log;

class ProofVerifier
{
    public static function inclusion(int $index, int $size, string $leafHash, array $proof, string $root): bool
    {
        if ($index < 0 || $index >= $size) {
            return false;
        }

        $fn = $index;
        $sn = $size - 1;
        $hash = $leafHash;

        foreach ($proof as $sibling) {
            if ($sn === 0) {
                return false;
            }

            if (($fn & 1) === 1 || $fn === $sn) {
                $hash = TreeReader::nodeHash($sibling, $hash);

                while (($fn & 1) === 0 && $fn !== 0) {
                    $fn >>= 1;
                    $sn >>= 1;
                }
            } else {
                $hash = TreeReader::nodeHash($hash, $sibling);
            }

            $fn >>= 1;
            $sn >>= 1;
        }

        return $sn === 0 && hash_equals($root, $hash);
    }

    public static function consistency(int $first, int $second, string $firstRoot, string $secondRoot, array $proof): bool
    {
        if ($first < 1 || $first > $second) {
            return false;
        }

        if ($first === $second) {
            return $proof === [] && hash_equals($firstRoot, $secondRoot);
        }

        if ($proof === []) {
            return false;
        }

        if (($first & ($first - 1)) === 0) {
            array_unshift($proof, $firstRoot);
        }

        $fn = $first - 1;
        $sn = $second - 1;

        while (($fn & 1) === 1) {
            $fn >>= 1;
            $sn >>= 1;
        }

        $fr = $proof[0];
        $sr = $proof[0];

        foreach (array_slice($proof, 1) as $hash) {
            if ($sn === 0) {
                return false;
            }

            if (($fn & 1) === 1 || $fn === $sn) {
                $fr = TreeReader::nodeHash($hash, $fr);
                $sr = TreeReader::nodeHash($hash, $sr);

                while (($fn & 1) === 0 && $fn !== 0) {
                    $fn >>= 1;
                    $sn >>= 1;
                }
            } else {
                $sr = TreeReader::nodeHash($sr, $hash);
            }

            $fn >>= 1;
            $sn >>= 1;
        }

        return $sn === 0 && hash_equals($firstRoot, $fr) && hash_equals($secondRoot, $sr);
    }
}
