<?php

namespace App\Console\Commands;

use App\Actions\Access\SyncPermissionCatalogue;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('permissions:sync')]
#[Description('Mirror the permission catalogue into the database without touching admin-defined roles')]
class SyncPermissions extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(SyncPermissionCatalogue $sync): int
    {
        $result = $sync->handle();

        $this->components->info(sprintf(
            'Permissions synced: %d created, %d removed.',
            count($result['created']),
            count($result['deleted']),
        ));

        foreach ($result['created'] as $name) {
            $this->components->twoColumnDetail($name, '<fg=green>created</>');
        }

        foreach ($result['deleted'] as $name) {
            $this->components->twoColumnDetail($name, '<fg=red>removed</>');
        }

        return self::SUCCESS;
    }
}
