<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\Web3BnbController;
use App\Http\Controllers\BnbSpotController;

class CheckBnbDeposits extends Command
{
    protected $signature = 'bnb:check-deposits';
    protected $description = 'Check for new BNB deposits and process them';

    public function handle()
    {
        $Web3BnbController = new Web3BnbController();
        $bnbSpotController = new BnbSpotController();

        $Web3BnbController->checkNewTransactions();
        $this->info('Checked for new BNB transactions');

        $bnbSpotController->processNewDeposits();
        $this->info('Processed new BNB deposits');
    }
}