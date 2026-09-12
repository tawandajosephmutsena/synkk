<?php

namespace App\Console\Commands;

use App\Actions\Teams\CreateTeam;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class BootstrapAdminCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'synkk:bootstrap-admin 
                            {--email= : Email address for the administrator}
                            {--password= : Password for the administrator}
                            {--name=Administrator : Display name for the administrator}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Headless bootstrap command to provision or update initial team and superadmin user for 1-click deployments';

    /**
     * Execute the console command.
     */
    public function handle(CreateTeam $createTeam): int
    {
        $email = $this->option('email') ?: $this->ask('Enter administrator email');
        $password = $this->option('password') ?: $this->secret('Enter administrator password');
        $name = $this->option('name') ?: 'Administrator';

        $validator = Validator::make([
            'email' => $email,
            'password' => $password,
            'name' => $name,
        ], [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', Password::default()],
            'name' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::where('email', $email)->first();

        if ($user) {
            $user->forceFill([
                'name' => $name,
                'password' => Hash::make($password),
                'is_super_admin' => true,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();

            if (! $user->currentTeam) {
                $personalTeam = $user->ownedTeams()->where('is_personal', true)->first();
                if ($personalTeam) {
                    $user->switchTeam($personalTeam);
                } else {
                    $createTeam->handle($user, $user->name."'s Team", isPersonal: true);
                }
            }

            $this->info("Administrator [{$email}] updated successfully.");

            return self::SUCCESS;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'is_super_admin' => true,
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();

        $createTeam->handle($user, $user->name."'s Team", isPersonal: true);

        $this->info("Administrator [{$email}] provisioned successfully.");

        return self::SUCCESS;
    }
}
