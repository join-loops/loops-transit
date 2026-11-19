<?php

namespace App\Console\Commands;

use App\Transit\KeyRing;
use Illuminate\Console\Command;

class RotateKeyCommand extends Command
{
    protected $signature = 'transit:key:rotate';

    protected $description = 'Retire the active signing key and generate a new one';

    public function handle(KeyRing $keys): int
    {
        if (! $keys->hasActive()) {
            $this->components->error('There is no active key to rotate. Run transit:key:generate first.');

            return self::FAILURE;
        }

        $previous = $keys->active()->kid;
        $key = $keys->generate();

        $this->components->info('Retired '.$previous.'. Signing key '.$key->kid.' is now active.');
        $this->components->info('The retired public key stays published so existing seals keep verifying.');

        return self::SUCCESS;
    }
}
