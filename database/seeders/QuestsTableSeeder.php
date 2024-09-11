<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Quest;

class QuestsTableSeeder extends Seeder
{
    public function run()
    {
        $quests = [
            [
                'title' => 'trade_gems_with_ticket',
                'description' => 'Trade your gems to receive a ticket and gold.',
                'rewards' => [
                    ['type' => 'ticket', 'amount' => 1],
                ],
            ],
            [
                'title' => 'trade_ticket_with_giftbox',
                'description' => 'Trade your tickets to receive a giftbox.',
                'rewards' => [
                    ['type' => 'giftbox', 'amount' => 1]
                ],
                'costs' => [
                    ['type' => 'ticket', 'amount' => 1]
                ]
            ],
            [
                'title' => 'trade_giftbox_with_rewards',
                'description' => 'Trade your giftboxes to receive some rewards.',
                'rewards' => [
                    ['type' => 'cp', 'amount' => 5000],
                    ['type' => 'wood', 'amount' => 100],
                    ['type' => 'sand', 'amount' => 30],
                ],
                'costs' => [
                    ['type' => 'giftbox', 'amount' => 1]
                ]
            ]
        ];

        foreach ($quests as $quest) {
            Quest::create($quest);
        }
    }
}
