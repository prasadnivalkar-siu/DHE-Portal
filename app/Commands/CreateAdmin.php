<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserIdentityModel;
use CodeIgniter\Shield\Models\UserModel;
use Throwable;

final class CreateAdmin extends BaseCommand
{
    protected $group = 'DHE';
    protected $name = 'dhe:create-admin';
    protected $description = 'Creates the first DHE Portal Super Administrator with a generated temporary password.';
    protected $usage = 'dhe:create-admin <email> <username>';
    protected $arguments = [
        'email' => 'Super Administrator email address.',
        'username' => 'Display username using letters, numbers, or dots.',
    ];

    public function run(array $params): int
    {
        $email = strtolower(trim((string) ($params[0] ?? '')));
        $username = trim((string) ($params[1] ?? ''));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || preg_match('/^[a-zA-Z0-9.]{3,30}$/', $username) !== 1) {
            CLI::error('Usage: php spark dhe:create-admin admin@example.org admin.name');

            return EXIT_ERROR;
        }

        $identity = model(UserIdentityModel::class)
            ->where('type', 'email_password')
            ->where('secret', $email)
            ->first();
        if ($identity !== null || model(UserModel::class)->where('username', $username)->first() !== null) {
            CLI::error('A user with that email address or username already exists.');

            return EXIT_ERROR;
        }

        $password = bin2hex(random_bytes(10)) . '!Aa1';
        $user = new User(['username' => $username, 'email' => $email, 'password' => $password, 'active' => 1]);

        try {
            $users = model(UserModel::class);
            if (! $users->save($user)) {
                CLI::error(implode(PHP_EOL, $users->errors()));

                return EXIT_ERROR;
            }
            $created = $users->findById($users->getInsertID());
            $created->syncGroups('superadmin');
        } catch (Throwable $exception) {
            CLI::error('Super Administrator creation failed: ' . $exception->getMessage());

            return EXIT_ERROR;
        }

        CLI::write('Super Administrator created.', 'green');
        CLI::write('Email: ' . $email);
        CLI::write('Temporary password: ' . $password, 'yellow');
        CLI::write('Sign in and change this password immediately from My Account.');

        return EXIT_SUCCESS;
    }
}
