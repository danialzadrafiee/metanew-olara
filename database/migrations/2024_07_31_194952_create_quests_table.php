<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Quest;

return new class extends Migration
{
    public function up()
    {
        Schema::create('quests', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('rewards')->nullable();
            $table->json('costs')->nullable();
            $table->timestamps();
        });

        // Insert initial quest data
        $quests = [
            [
                'title' => 'trade_gems_with_ticket',
                'description' => 'Trade your gems to receive a ticket and gold.',
                'rewards' => json_encode([
                    ['type' => 'ticket', 'amount' => 1],
                ]),
            ],
            [
                'title' => 'trade_ticket_with_giftbox',
                'description' => 'Trade your tickets to receive a giftbox.',
                'rewards' => json_encode([
                    ['type' => 'giftbox', 'amount' => 1]
                ]),
                'costs' => json_encode([
                    ['type' => 'ticket', 'amount' => 1]
                ])
            ],
            [
                'title' => 'trade_giftbox_with_rewards',
                'description' => 'Trade your giftboxes to receive some rewards.',
                'rewards' => json_encode([
                    ['type' => 'cp', 'amount' => 5000],
                    ['type' => 'wood', 'amount' => 100],
                    ['type' => 'sand', 'amount' => 30],
                ]),
                'costs' => json_encode([
                    ['type' => 'giftbox', 'amount' => 1]
                ])
            ]
        ];

        foreach ($quests as $quest) {
            Quest::create($quest);
        }
    }

    public function down()
    {
        Schema::dropIfExists('quests');
    }
};