<?php

use App\Http\Controllers\AdminAuctionController;
use App\Http\Controllers\AdminLandController;
use App\Http\Controllers\AdminScratchBoxController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\AssetListingController;
use App\Http\Controllers\AuctionController;
use App\Http\Controllers\CronController;
use App\Http\Controllers\LandController;
use App\Http\Controllers\LandFixedPriceController;
use App\Http\Controllers\OfferController;
use App\Http\Controllers\QuestController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AdminLandCollectionController;
use App\Http\Controllers\AdminLandCollectionImportController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BnbSpotController;
use App\Http\Controllers\BuildingController;
use App\Http\Controllers\CityController;
use App\Http\Controllers\GameEconomySettingsController;
use App\Http\Controllers\LandTransferController;
use App\Http\Controllers\MarketLandController;
use App\Http\Controllers\MetaSpotController;
use App\Http\Controllers\NftController;
use App\Http\Controllers\ScratchBoxController;
use App\Http\Controllers\SpotController;
use App\Http\Controllers\SpotWithdrawController;
use App\Http\Controllers\TestController;
use Illuminate\Support\Facades\Route;

// User Authentication
Route::post('user/authenticate', [AuthController::class, 'authenticate']);
Route::get('users', [UserController::class, 'index']);
Route::middleware('auth:sanctum')->group(function () {
    Route::get('user/show', [UserController::class, 'show']);
    Route::post('user/update', [UserController::class, 'update']);
    Route::post('user/logout', [AuthController::class, 'logout']);
    Route::post('user/update-profile', [UserController::class, 'updateProfile']);
    Route::get('user/referral-tree', [UserController::class, 'getReferralTree']);
});
Route::middleware('auth:sanctum')->group(function () {
    // Assets
    Route::get('assets', [AssetController::class, 'index']);
    Route::post('assets/update', [AssetController::class, 'update']);
    Route::get('assets/{userId}/{type}', [AssetController::class, 'getBalance']);
    Route::post('assets/lock', [AssetController::class, 'lock']);
    Route::post('assets/unlock', [AssetController::class, 'unlock']);
    Route::post('assets/add', [AssetController::class, 'add']);
    Route::post('assets/subtract', [AssetController::class, 'subtract']);


    // Lands
    Route::get('lands', [LandController::class, 'getBoundLands']);
    Route::get('lands/all', [LandController::class, 'all']);
    Route::get('lands/user', [LandController::class, 'getUserLands']);
    Route::get('lands/{id}', [LandController::class, 'show']);
    Route::post('lands/{id}/get-active-auction', [LandController::class, 'getLandActiveAuction']);

    // Fixed Price
    Route::post('lands/{id}/set-price', [LandFixedPriceController::class, 'setPrice']);
    Route::post('lands/{id}/update-price', [LandFixedPriceController::class, 'updatePrice']);
    Route::post('lands/{id}/cancel-sell', [LandFixedPriceController::class, 'cancelSell']);
    // Route::post('lands/{id}/buy', [LandFixedPriceController::class, 'acceptBuy']);
    Route::get('marketplace/lands', [MarketLandController::class, 'getMarketplaceLands']);

    // Auctions
    Route::post('auctions/start/{landId}', [AuctionController::class, 'startAuction']);
    Route::post('auctions/{id}/bid', [AuctionController::class, 'placeBid']);
    Route::get('auctions/active', [AuctionController::class, 'getActiveAuctions']);
    Route::post('auctions/create', [AuctionController::class, 'createAuction']);
    Route::get('auctions/{auctionId}/bids', [AuctionController::class, 'getBidsForAuction']);
    Route::post('auctions/{auctionId}/cancel', [AuctionController::class, 'cancelAuction']);

    // Offers
    Route::get('offers/{landId}', [OfferController::class, 'getOffersByLand']);
    Route::post('offers/user', [OfferController::class, 'getOffersByUser']);
    Route::post('offers/submit', [OfferController::class, 'submitOffer']);
    Route::post('offers/delete/{offerId}', [OfferController::class, 'deleteOffer']);
    Route::post('offers/update/{offerId}', [OfferController::class, 'updateOffer']);
    // Route::post('offers/accept/{offerId}', [OfferController::class, 'acceptOffer']);

    // Quests
    Route::get('quests', [QuestController::class, 'index']);
    Route::get('quests/{quest}', [QuestController::class, 'show']);
    Route::post('quests', [QuestController::class, 'store']);
    Route::put('quests/{quest}', [QuestController::class, 'update']);
    Route::delete('quests/{quest}', [QuestController::class, 'destroy']);
    Route::post('quests/complete', [QuestController::class, 'complete']);
    Route::get('user/quests', [QuestController::class, 'userQuests']);
    Route::get('user/available-quests', [QuestController::class, 'availableQuests']);

    // Scratch Boxes
    Route::get('scratch-boxes', [ScratchBoxController::class, 'index']);
    Route::get('scratch-boxes/available', [ScratchBoxController::class, 'available']);
    Route::get('scratch-boxes/owned', [ScratchBoxController::class, 'owned']);
    Route::post('scratch-boxes/{id}/buy', [ScratchBoxController::class, 'buy']);
    // Route::post('scratch-boxes/{id}/open', [ScratchBoxController::class, 'open']);

    // Asset Listings
    Route::get('asset-listings', [AssetListingController::class, 'index']);
    Route::post('asset-listings', [AssetListingController::class, 'create']);
    Route::put('asset-listings/{listing}', [AssetListingController::class, 'update']);
    Route::delete('asset-listings/{listing}', [AssetListingController::class, 'destroy']);
    Route::post('asset-listings/{listing}/buy', [AssetListingController::class, 'buy']);

    Route::post('set-land-building-id', [BuildingController::class, 'setLandBuildingid']);
});

// Admin Land
Route::get('admin/manage/lands', [AdminLandController::class, 'index']);
Route::get('admin/manage/lands/all-ids', [AdminLandController::class, 'getAllLandIds']);
Route::get('/admin/manage/lands/filtered-ids', [AdminLandController::class, 'getFilteredLandIds']);
Route::post('admin/manage/lands/bulk-update-fixed-price', [AdminLandController::class, 'bulkUpdateFixedPrice']);
Route::post('admin/manage/lands/bulk-update-price-by-size', [AdminLandController::class, 'bulkUpdatePriceBySize']);

// Admin Auction
Route::post('admin/manage/lands/bulk-create-auctions', [AdminAuctionController::class, 'bulkCreateAuctions']);
Route::post('admin/manage/lands/bulk-cancel-auctions', [AdminAuctionController::class, 'bulkCancelAuctions']);
Route::post('admin/manage/lands/bulk-remove-auctions', [AdminAuctionController::class, 'bulkRemoveAuctions']);
Route::get('admin/manage/auctions', [AdminAuctionController::class, 'getAuctions']);





// Land Import and Collections
Route::post('admin/lands/import', [AdminLandCollectionImportController::class, 'import']);
Route::get('admin/lands/collections', [AdminLandCollectionController::class, 'getCollections']);
Route::post('admin/lands/revert/{id}', [AdminLandCollectionController::class, 'revertToCollection']);
Route::post('admin/lands/toggle-active/{id}', [AdminLandCollectionController::class, 'toggleActive']);
Route::get('admin/lands/collections/{id}', [AdminLandCollectionController::class, 'getCollection']);
Route::post('admin/lands/update-active-collections', [AdminLandCollectionController::class, 'updateActiveCollections']);
Route::delete('admin/lands/collections/{id}', [AdminLandCollectionController::class, 'deleteCollection']);
Route::post('admin/lands/lock/{id}', [AdminLandCollectionController::class, 'lockLands']);
Route::post('admin/lands/unlock/{id}', [AdminLandCollectionController::class, 'unlockLands']);
Route::post('admin/lands/collections/{id}/update-type', [AdminLandCollectionController::class, 'updateLandType']);
Route::post('/admin/lands/check-duplicates', [AdminLandCollectionController::class, 'checkDuplicates']);

// Admin Scratch Boxes
Route::get('admin/scratch-boxes', [AdminScratchBoxController::class, 'index']);
Route::post('admin/scratch-boxes', [AdminScratchBoxController::class, 'create']);
Route::delete('admin/scratch-boxes/{id}', [AdminScratchBoxController::class, 'destroy']);
Route::get('admin/scratch-boxes/all-available-land-ids', [AdminScratchBoxController::class, 'getAllAvailableLandIds']);
Route::get('admin/scratch-boxes/available-lands', [AdminScratchBoxController::class, 'getAvailableLands']);




Route::get('/cities', [CityController::class, 'index']);
Route::post('/cities/store', [CityController::class, 'store']);
Route::get('/cities/show/{id}', [CityController::class, 'show']);
Route::post('/cities/update/{id}', [CityController::class, 'update']);
Route::post('/cities/delete/{id}', [CityController::class, 'destroy']);



Route::get('/game_economy_settings', [GameEconomySettingsController::class, 'index']);
Route::put('/game_economy_settings/{id}', [GameEconomySettingsController::class, 'update']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('lands/{id}/buy', [LandTransferController::class, 'acceptBuy']);
    Route::post('offers/accept/{offerId}', [LandTransferController::class, 'acceptOffer']);
    Route::post('scratch-boxes/{id}/open', [LandTransferController::class, 'openScratchBox']);
});

Route::get('spot/update_balances', [SpotController::class, 'updateBalances']);
Route::middleware('auth:sanctum')->group(function () {
    Route::post('spot/withdraw_bnb', [SpotWithdrawController::class, 'withdrawBnb']);
    Route::post('spot/withdraw_meta', [SpotWithdrawController::class, 'withdrawMeta']);
});
Route::get('test/{landId}', [LandTransferController::class, 'executeSingleAuction']);


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
