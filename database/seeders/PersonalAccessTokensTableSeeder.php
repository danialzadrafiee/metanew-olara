<?php
namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Laravel\Sanctum\PersonalAccessToken;

class PersonalAccessTokensTableSeeder extends Seeder
{
    public function run()
    {
        $existingToken = PersonalAccessToken::where('tokenable_id', 2)->first();
        if (!$existingToken) {
            $createdAt = '2024-08-04 21:32:24';
            $expiresAt = '2026-08-04 21:32:24';
            PersonalAccessToken::create([
                'tokenable_type' => 'App\\Models\\User',
                'tokenable_id' => 2,
                'name' => 'auth_token',
                'token' => 'b98f21880ba87ed6406248ed38ffab3195e0e2394bc21840ec4a7ce6ba118b8e',
                'abilities' => '["*"]',
                'last_used_at' =>  $createdAt,
                'created_at' => $createdAt,
                'expires_at' => $expiresAt,
                'updated_at' =>  $createdAt,
            ]);
        }
    }
}