<?php

namespace App\Console\Commands;

use App\Actions\Teams\CreateTeam;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
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
                            {--name= : Display name for the administrator}
                            {--if-empty : Provision only when no users exist, using bootstrap environment variables}';

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
        if ($this->option('if-empty')) {
            return $this->bootstrapIfEmpty($createTeam);
        }

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

    private function bootstrapIfEmpty(CreateTeam $createTeam): int
    {
        if (User::query()->exists()) {
            $this->info('Administrator bootstrap skipped because users already exist.');

            return self::SUCCESS;
        }

        if ($this->option('email') || $this->option('password') || $this->option('name')) {
            $this->error('The --if-empty option reads credentials from SYNKK_BOOTSTRAP_* environment variables only.');

            return self::FAILURE;
        }

        $email = getenv('SYNKK_BOOTSTRAP_EMAIL');
        $password = getenv('SYNKK_BOOTSTRAP_PASSWORD');
        $name = getenv('SYNKK_BOOTSTRAP_NAME') ?: 'Administrator';
        $passwordSource = getenv('SYNKK_BOOTSTRAP_PASSWORD_SOURCE');

        if (! is_string($email) || $email === '' || ! is_string($password) || $password === '') {
            $this->error('SYNKK_BOOTSTRAP_EMAIL and SYNKK_BOOTSTRAP_PASSWORD are required for first-run bootstrap.');

            return self::FAILURE;
        }

        $passwordRules = $passwordSource === 'umbrel'
            ? ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/']
            : ['required', 'string', Password::default()];

        $validator = Validator::make([
            'email' => $email,
            'password' => $password,
            'name' => $name,
        ], [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => $passwordRules,
            'name' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        return DB::transaction(function () use ($createTeam, $email, $password, $name): int {
            if (User::query()->exists()) {
                $this->info('Administrator bootstrap skipped because users already exist.');

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
        });
    }
}
