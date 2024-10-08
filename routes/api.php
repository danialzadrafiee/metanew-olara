<?php

use Illuminate\Support\Facades\Route;

// Import all controllers
use App\Http\Controllers\{
    AdminAuctionController,
    AdminLandController,
    AdminScratchBoxController,
    AssetController,
    AssetListingController,
    AuctionController,
    LandController,
    LandFixedPriceController,
    OfferController,
    QuestController,
    UserController,
    AdminLandCollectionController,
    AdminLandCollectionImportController,
    AuthController,
    BuildingController,
    CityController,
    GameEconomySettingsController,
    LandTransferController,
    MarketLandController,
    ScratchBoxController,
    SpotController,
    SpotWithdrawController
};

// User Authentication
Route::post('user/authenticate', [AuthController::class, 'authenticate']);
Route::get('users', [UserController::class, 'index']);

// Public Routes
Route::get('/cities', [CityController::class, 'index']);
Route::get('/game_economy_settings', [GameEconomySettingsController::class, 'index']);

// Authenticated Routes
Route::middleware('auth:sanctum')->group(function () {
    // User
    Route::prefix('user')->group(function () {
        Route::get('show', [UserController::class, 'show']);
        Route::post('update', [UserController::class, 'update']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('update-profile', [UserController::class, 'updateProfile']);
        Route::get('referral-tree', [UserController::class, 'getReferralTree']);
    });

    // Assets
    Route::prefix('assets')->group(function () {
        Route::get('/', [AssetController::class, 'index']);
        Route::post('update', [AssetController::class, 'update']);
        Route::get('{userId}/{type}', [AssetController::class, 'getBalance']);
        Route::post('lock', [AssetController::class, 'lock']);
        Route::post('unlock', [AssetController::class, 'unlock']);
        Route::post('add', [AssetController::class, 'add']);
        Route::post('subtract', [AssetController::class, 'subtract']);
    });

    // Lands
    Route::prefix('lands')->group(function () {
        Route::get('/', [LandController::class, 'getBoundLands']);
        Route::get('all', [LandController::class, 'all']);
        Route::get('user', [LandController::class, 'getUserLands']);
        Route::get('{id}', [LandController::class, 'show']);
        Route::post('{id}/get-active-auction', [LandController::class, 'getLandActiveAuction']);

        // Fixed Price
        Route::post('{id}/set-price', [LandFixedPriceController::class, 'setPrice']);
        Route::post('{id}/update-price', [LandFixedPriceController::class, 'updatePrice']);
        Route::post('{id}/cancel-sell', [LandFixedPriceController::class, 'cancelSell']);
        Route::post('{id}/buy', [LandTransferController::class, 'acceptBuy']);
    });

    // Marketplace
    Route::get('marketplace/lands', [MarketLandController::class, 'getMarketplaceLands']);

    // Auctions
    Route::prefix('auctions')->group(function () {
        Route::post('start/{landId}', [AuctionController::class, 'startAuction']);
        Route::post('{id}/bid', [AuctionController::class, 'placeBid']);
        Route::get('active', [AuctionController::class, 'getActiveAuctions']);
        Route::post('create', [AuctionController::class, 'createAuction']);
        Route::get('{auctionId}/bids', [AuctionController::class, 'getBidsForAuction']);
        Route::post('{auctionId}/cancel', [AuctionController::class, 'cancelAuction']);
    });

    // Offers
    Route::prefix('offers')->group(function () {
        Route::get('{landId}', [OfferController::class, 'getOffersByLand']);
        Route::post('user', [OfferController::class, 'getOffersByUser']);
        Route::post('submit', [OfferController::class, 'submitOffer']);
        Route::post('delete/{offerId}', [OfferController::class, 'deleteOffer']);
        Route::post('update/{offerId}', [OfferController::class, 'updateOffer']);
        Route::post('accept/{offerId}', [LandTransferController::class, 'acceptOffer']);
    });

    // Quests
    Route::prefix('quests')->group(function () {
        Route::get('/', [QuestController::class, 'index']);
        Route::get('{quest}', [QuestController::class, 'show']);
        Route::post('/', [QuestController::class, 'store']);
        Route::put('{quest}', [QuestController::class, 'update']);
        Route::delete('{quest}', [QuestController::class, 'destroy']);
        Route::post('complete', [QuestController::class, 'complete']);
        Route::get('user', [QuestController::class, 'userQuests']);
        Route::get('available', [QuestController::class, 'availableQuests']);
    });

    // Scratch Boxes
    Route::prefix('scratch-boxes')->group(function () {
        Route::get('/', [ScratchBoxController::class, 'index']);
        Route::get('available', [ScratchBoxController::class, 'available']);
        Route::get('owned', [ScratchBoxController::class, 'owned']);
        Route::post('{id}/buy', [ScratchBoxController::class, 'buy']);
        Route::post('{id}/open', [LandTransferController::class, 'openScratchBox']);
    });

    // Asset Listings
    Route::prefix('asset-listings')->group(function () {
        Route::get('/', [AssetListingController::class, 'index']);
        Route::post('/', [AssetListingController::class, 'create']);
        Route::put('{listing}', [AssetListingController::class, 'update']);
        Route::delete('{listing}', [AssetListingController::class, 'destroy']);
        Route::post('{listing}/buy', [AssetListingController::class, 'buy']);
    });

    // Buildings
    Route::post('set-land-building-id', [BuildingController::class, 'setLandBuildingid']);

    // Spot
    Route::prefix('spot')->group(function () {
        Route::post('withdraw_bnb', [SpotWithdrawController::class, 'withdrawBnb']);
        Route::post('withdraw_meta', [SpotWithdrawController::class, 'withdrawMeta']);
    });
});

// Admin Routes
Route::prefix('admin')->group(function () {
    // Land Management
    Route::prefix('manage/lands')->group(function () {
        Route::get('/', [AdminLandController::class, 'index']);
        Route::get('all-ids', [AdminLandController::class, 'getAllLandIds']);
        Route::get('filtered-ids', [AdminLandController::class, 'getFilteredLandIds']);
        Route::post('bulk-update-fixed-price', [AdminLandController::class, 'bulkUpdateFixedPrice']);
        Route::post('bulk-update-price-by-size', [AdminLandController::class, 'bulkUpdatePriceBySize']);
    });

    // Auction Management
    Route::prefix('manage')->group(function () {
        Route::post('lands/bulk-create-auctions', [AdminAuctionController::class, 'bulkCreateAuctions']);
        Route::post('lands/bulk-cancel-auctions', [AdminAuctionController::class, 'bulkCancelAuctions']);
        Route::post('lands/bulk-remove-auctions', [AdminAuctionController::class, 'bulkRemoveAuctions']);
        Route::get('auctions', [AdminAuctionController::class, 'getAuctions']);
    });

    // Land Import and Collections
    Route::prefix('lands')->group(function () {
        Route::post('import', [AdminLandCollectionImportController::class, 'import']);
        Route::get('collections', [AdminLandCollectionController::class, 'getCollections']);
        Route::post('revert/{id}', [AdminLandCollectionController::class, 'revertToCollection']);
        Route::post('toggle-active/{id}', [AdminLandCollectionController::class, 'toggleActive']);
        Route::get('collections/{id}', [AdminLandCollectionController::class, 'getCollection']);
        Route::post('update-active-collections', [AdminLandCollectionController::class, 'updateActiveCollections']);
        Route::delete('collections/{id}', [AdminLandCollectionController::class, 'deleteCollection']);
        Route::post('lock/{id}', [AdminLandCollectionController::class, 'lockLands']);
        Route::post('unlock/{id}', [AdminLandCollectionController::class, 'unlockLands']);
        Route::post('collections/{id}/update-type', [AdminLandCollectionController::class, 'updateLandType']);
        Route::post('check-duplicates', [AdminLandCollectionController::class, 'checkDuplicates']);
    });

    // Scratch Boxes Management
    Route::prefix('scratch-boxes')->group(function () {
        Route::get('/', [AdminScratchBoxController::class, 'index']);
        Route::post('/', [AdminScratchBoxController::class, 'create']);
        Route::delete('{id}', [AdminScratchBoxController::class, 'destroy']);
        Route::get('all-available-land-ids', [AdminScratchBoxController::class, 'getAllAvailableLandIds']);
        Route::get('available-lands', [AdminScratchBoxController::class, 'getAvailableLands']);
    });
});

// City Management
Route::prefix('cities')->group(function () {
    Route::post('store', [CityController::class, 'store']);
    Route::get('show/{id}', [CityController::class, 'show']);
    Route::post('update/{id}', [CityController::class, 'update']);
    Route::post('delete/{id}', [CityController::class, 'destroy']);
});

// Game Economy Settings
Route::put('/game_economy_settings/{id}', [GameEconomySettingsController::class, 'update']);

// Cron Jobs
Route::get('cron/spot/update_balances', [SpotController::class, 'updateBalances']);
Route::get('cron/auctions/execute_all_auctions', [LandTransferController::class, 'executeAllAuctions']);
Route::get('cron/auctions/execute_ended_auctions', [LandTransferController::class, 'executeAllAuctions']);
Route::get('cron/auctions/force_execute_all_auctions', function (LandTransferController $controller) {
    return $controller->executeAllAuctions(true);
});

// Test Routes
Route::get('test-ex-single-auction/{landId}', [LandTransferController::class, 'executeSingleAuction']);

// Environment Check
Route::get('/env-check', function () {
    return response()->json([
        'ENV' => $_ENV,
        'SERVER' => $_SERVER,
        'APP_NAME' => env('APP_NAME'),
        'LAND_MINTER_CONTRACT_ADDRESS' => env('LAND_MINTER_CONTRACT_ADDRESS'),
        'BANK_PVK' => env('BANK_PVK'),
        'BANK_ADDRESS' => env('BANK_ADDRESS'),
        'RPC_URL' => env('RPC_URL'),
        'CHAIN_ID' => env('CHAIN_ID'),
        'META_CONTRACT_ADDRESS' => env('META_CONTRACT_ADDRESS'),
        'FOUNDATION_ADDRESS' => env('FOUNDATION_ADDRESS'),
    ]);
});
