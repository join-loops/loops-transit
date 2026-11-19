<?php

namespace App\Console\Commands;

use App\Transit\KeyRing;
use Illuminate\Console\Command;

class GenerateKeyCommand extends Command
{
    protected $signature = 'transit:key:generate {--force : Replace the active key even if one exists}';

    protected $description = 'Generate the Ed25519 signing key for this Transit';

    public function handle(KeyRing $keys): int
    {
        if ($keys->hasActive() && ! $this->option('force')) {
            $this->components->error('This Transit already has an active key. Use transit:key:rotate to replace it.');

            return self::FAILURE;
        }

        $key = $keys->generate();

        $this->components->info('Signing key '.$key->kid.' is now active.');

        return self::SUCCESS;
    }
}
