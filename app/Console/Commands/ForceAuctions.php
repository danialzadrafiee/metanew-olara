<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ForceAuctions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'auction:force';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Force auctions to start by calling the FPA API';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Forcing auctions to start...');

        try {
            $response = Http::get('http://localhost:8000/api/fpa');
            
            if ($response->successful()) {
                $this->info('API Response:');
                $this->line($response->body());
            } else {
                $this->error('Failed to fetch data: ' . $response->status());
            }
        } catch (\Exception $e) {
            $this->error('An error occurred: ' . $e->getMessage());
        }

        $this->info('Auction force process completed.');
    }
}