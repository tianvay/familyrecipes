<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

/**
 * Legt ein Familienmitglied an – vor allem für den ersten Admin-Account,
 * danach geht das bequemer über „Benutzer verwalten“.
 */
class CreateUser extends Command
{
    protected $signature = 'users:create {--admin : Direkt als Familienoberhaupt (Admin) anlegen}';

    protected $description = 'Legt ein Familienmitglied mit Rolle an';

    public function handle(): int
    {
        $name = text('Name', placeholder: 'z. B. Anna', required: true);
        $username = Str::lower(trim(text('Benutzername', placeholder: 'anna', required: true)));
        $password = password('Passwort', required: true);
        $role = $this->option('admin') ? Role::Admin : $this->askForRole();

        $validator = Validator::make(compact('name', 'username', 'password'), [
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'alpha_dash:ascii', 'max:50', Rule::unique(User::class)],
            'password' => ['required', Password::defaults()],
        ], attributes: ['username' => 'Benutzername', 'password' => 'Passwort']);

        if ($validator->fails()) {
            $this->components->bulletList($validator->errors()->all());

            return self::FAILURE;
        }

        $user = new User(compact('name', 'username', 'password'));
        $user->role = $role;
        $user->save();

        $this->components->info(sprintf(
            '%s (@%s) wurde mit der Rolle „%s“ angelegt.',
            $user->name,
            $user->username,
            $user->role->label(),
        ));

        return self::SUCCESS;
    }

    private function askForRole(): Role
    {
        $options = collect(Role::cases())
            ->mapWithKeys(fn (Role $role) => [$role->value => $role->label()])
            ->all();

        return Role::from(select('Rolle', $options, default: Role::Adult->value));
    }
}
