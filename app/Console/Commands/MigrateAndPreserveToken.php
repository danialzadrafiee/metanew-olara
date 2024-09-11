<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Laravel\Sanctum\PersonalAccessToken;
use App\Models\User;

class MigrateAndPreserveToken extends Command
{
    protected $signature = 'migrate:preserve-token {--fresh} {--seed}';
    protected $description = 'Run migrations while preserving the auth tokens for users 2 and 3';

    public function handle()
    {
        $preservedData = [];

        // Store the current users and tokens
        foreach ([2, 3] as $userId) {
            $user = User::find($userId);
            if ($user) {
                $userData = $user->only([
                    'id', 'role', 'address', 'nickname', 'avatar_url', 'coordinates',
                    'current_mission', 'referrer_id', 'referral_code', 'remember_token',
                    'created_at', 'updated_at'
                ]);
                $token = $user->tokens()->first();
                $tokenData = null;
                if ($token) {
                    $tokenData = [
                        'name' => $token->name,
                        'token' => $token->token,
                        'abilities' => $token->abilities,
                        'created_at' => $token->created_at,
                        'expires_at' => $token->expires_at,
                    ];
                }
                $preservedData[$userId] = [
                    'user' => $userData,
                    'token' => $tokenData
                ];
            }
        }

        // Run migrations
        if ($this->option('fresh')) {
            $this->call('migrate:fresh', ['--force' => true]);
        } else {
            $this->call('migrate', ['--force' => true]);
        }

        // Run seeders if option is set
        if ($this->option('seed')) {
            $this->call('db:seed', ['--force' => true]);
        }

        // Restore the users and tokens if they existed
        foreach ($preservedData as $userId => $data) {
            if ($data['user']) {
                $user = User::updateOrCreate(
                    ['address' => $data['user']['address']],
                    $data['user']
                );
                $this->info("User {$userId} has been preserved.");

                if ($data['token']) {
                    PersonalAccessToken::updateOrCreate(
                        [
                            'tokenable_id' => $user->id,
                            'tokenable_type' => get_class($user),
                            'name' => $data['token']['name'],
                        ],
                        [
                            'token' => $data['token']['token'],
                            'abilities' => $data['token']['abilities'],
                            'created_at' => $data['token']['created_at'],
                            'expires_at' => $data['token']['expires_at'],
                        ]
                    );
                    $this->info("Auth token for user {$userId} has been preserved.");
                }
            }
        }

        if (empty($preservedData)) {
            $this->info('No existing users or tokens found for users 2 and 3.');
        }
    }
}