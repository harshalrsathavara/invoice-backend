<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdminCommand extends Command
{
    protected $signature = 'invoice:admin
                            {--name= : The name shown in the panel}
                            {--email= : Sign-in address}
                            {--password= : Sign-in password}';

    protected $description = 'Create (or promote) the administrator account';

    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('Name');
        $email = $this->option('email') ?: $this->ask('Email');
        $password = $this->option('password') ?: $this->secret('Password');

        if (! $name || ! $email || ! $password) {
            $this->error('Name, email and password are all required.');

            return self::FAILURE;
        }

        $user = User::updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => Hash::make($password), 'is_admin' => true],
        );

        $this->info(($user->wasRecentlyCreated ? 'Created' : 'Updated')." administrator {$user->email}.");
        $this->line('Sign in at '.url('/admin/login'));

        return self::SUCCESS;
    }
}
