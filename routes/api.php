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
use App\Http\Controllers\BuildingController;
use App\Http\Controllers\MarketLandController;
use App\Http\Controllers\ScratchBoxController;
use App\Http\Controllers\UserSpotController;
use Illuminate\Support\Facades\Route;

// User Authentication and Profile
Route::post('user/authenticate', [UserController::class, 'authenticate']);
Route::middleware('auth:sanctum')->group(function () {
    Route::get('user/show', [UserController::class, 'show']);
    Route::post('user/update', [UserController::class, 'update']);
    Route::post('user/logout', [UserController::class, 'logout']);
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
    Route::post('lands/{id}/buy', [LandFixedPriceController::class, 'acceptBuy']);
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
    Route::post('offers/accept/{offerId}', [OfferController::class, 'acceptOffer']);

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
    Route::post('scratch-boxes/{id}/open', [ScratchBoxController::class, 'open']);

    // Asset Listings
    Route::get('asset-listings', [AssetListingController::class, 'index']);
    Route::post('asset-listings', [AssetListingController::class, 'create']);
    Route::put('asset-listings/{listing}', [AssetListingController::class, 'update']);
    Route::delete('asset-listings/{listing}', [AssetListingController::class, 'destroy']);
    Route::post('asset-listings/{listing}/buy', [AssetListingController::class, 'buy']);

    Route::post('set-land-building-id', [BuildingController::class, 'setLandBuildingid']);

    // SPOT 
    Route::post('spot/deposit', [UserSpotController::class, 'depositAsset']);
    Route::post('spot/withdraw', [UserSpotController::class, 'withdrawAsset']);
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

// Cron Jobs
Route::get('cron/auctions-process', [CronController::class, 'processAllAuctions']);
Route::get('cron/force-process-auction', [CronController::class, 'forceAllProcessAllAuctions']);
Route::get('fpa', [CronController::class, 'forceAllProcessAllAuctions']);
