<?php

namespace App\Console\Commands;

use App\Actions\Access\SyncPermissionCatalogue;
use App\Models\ParishSetting;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[Signature('parishdesk:install
    {--parish= : The parish name}
    {--name= : The Super Admin\'s name}
    {--email= : The Super Admin\'s email address}
    {--password= : The Super Admin\'s password}')]
#[Description('Set up ParishDesk with its permissions and the first Super Admin account')]
class InstallParishDesk extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(SyncPermissionCatalogue $sync): int
    {
        $sync->handle();

        if (User::role(Role::SUPER_ADMIN)->exists()) {
            $this->components->warn('ParishDesk is already installed: a Super Admin exists. Permissions were re-synced.');

            return self::SUCCESS;
        }

        $parish = $this->option('parish') ?: text('Parish name', required: true);
        $name = $this->option('name') ?: text('Super Admin name', required: true);
        $email = $this->option('email') ?: text('Super Admin email', required: true);
        $secret = $this->option('password') ?: password('Super Admin password', required: true);

        $validator = Validator::make(
            ['parish' => $parish, 'name' => $name, 'email' => $email, 'password' => $secret],
            [
                'parish' => ['required', 'string', 'max:255'],
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'string', Password::default()],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        ParishSetting::current()->update(['name' => $parish]);

        $user = User::query()->create([
            'name' => $name,
            'email' => strtolower($email),
            'password' => $secret,
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $user->assignRole(Role::SUPER_ADMIN);

        $this->components->info("ParishDesk is ready. Sign in as {$user->email} to set up roles and invite staff.");

        return self::SUCCESS;
    }
}
