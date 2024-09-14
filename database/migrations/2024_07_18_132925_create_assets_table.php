<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->double('amount')->default(0);
            $table->double('locked_amount')->default(0);
            $table->enum('type', ['cp', 'meta', 'bnb', 'iron', 'wood', 'sand', 'gold', 'giftbox', 'ticket', 'chest_silver', 'chest_gold', 'chest_diamond', 'scratch_box']);
            $table->timestamps();
            $table->unique(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};