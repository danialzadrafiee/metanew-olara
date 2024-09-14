<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\User;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->integer('role')->nullable()->default(0);
            $table->integer('city_id')->nullable()->default(0);
            $table->string('address')->unique();
            $table->string('nickname')->nullable();
            $table->string('avatar_url')->nullable();
            $table->json('coordinates')->nullable();
            $table->integer('current_mission')->default(0);
            $table->unsignedBigInteger('inviter_id')->nullable();
            $table->string('referral_code')->unique()->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

     
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};