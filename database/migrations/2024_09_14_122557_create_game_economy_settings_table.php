<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGameEconomySettingsTable extends Migration
{
    public function up()
    {
        Schema::create('game_economy_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->double('value');
            $table->timestamps();
        });

        // Insert default values
        $this->insertDefaultValues();
    }

    public function down()
    {
        Schema::dropIfExists('game_economy_settings');
    }

    private function insertDefaultValues()
    {
        $settings = [
            // First transfer
            ['key' => 'first_transfer.bank_share', 'value' => 0.35],
            ['key' => 'first_transfer.inviter_share', 'value' => 0.05],
            ['key' => 'first_transfer.foundation_share', 'value' => 0.60],
            ['key' => 'first_transfer.bank_share_no_inviter', 'value' => 0.40],
            
            // Subsequent transfers
            ['key' => 'subsequent_transfer.seller_share', 'value' => 0.985],
            ['key' => 'subsequent_transfer.foundation_share', 'value' => 0.0075],
            ['key' => 'subsequent_transfer.bank_share', 'value' => 0.0075],
            
            ['key' => 'land_transfer.cp_reward', 'value' => 500],
        ];
    
        DB::table('game_economy_settings')->insert($settings);
    }
}