<?php

// @formatter:off
// phpcs:ignoreFile
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property int $amount
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $user
 * @method static \Database\Factories\AssetFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|Asset newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Asset newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Asset query()
 * @method static \Illuminate\Database\Eloquent\Builder|Asset whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Asset whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Asset whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Asset whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Asset whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Asset whereUserId($value)
 */
	class Asset extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property int $land_id
 * @property int $owner_id
 * @property int $minimum_price
 * @property string $start_time
 * @property \Illuminate\Support\Carbon $end_time
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\AuctionBid> $bids
 * @property-read int|null $bids_count
 * @property-read mixed $highest_bid
 * @property-read mixed $highest_bidder
 * @property-read bool $is_active
 * @property-read \App\Models\Land $land
 * @method static \Illuminate\Database\Eloquent\Builder|Auction active()
 * @method static \Illuminate\Database\Eloquent\Builder|Auction newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Auction newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Auction query()
 * @method static \Illuminate\Database\Eloquent\Builder|Auction whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Auction whereEndTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Auction whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Auction whereLandId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Auction whereMinimumPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Auction whereOwnerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Auction whereStartTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Auction whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Auction whereUpdatedAt($value)
 */
	class Auction extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property int $auction_id
 * @property int $user_id
 * @property string $amount
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Auction $auction
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder|AuctionBid newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|AuctionBid newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|AuctionBid query()
 * @method static \Illuminate\Database\Eloquent\Builder|AuctionBid whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AuctionBid whereAuctionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AuctionBid whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AuctionBid whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AuctionBid whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AuctionBid whereUserId($value)
 */
	class AuctionBid extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property int $amount
 * @property int $locked_amount
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $user
 * @method static \Database\Factories\CurrencyFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|Currency newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Currency newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Currency query()
 * @method static \Illuminate\Database\Eloquent\Builder|Currency whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Currency whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Currency whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Currency whereLockedAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Currency whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Currency whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Currency whereUserId($value)
 */
	class Currency extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property string|null $full_id
 * @property string|null $region
 * @property string|null $zone
 * @property string|null $coordinates
 * @property string|null $center_point
 * @property int|null $size
 * @property int|null $owner_id
 * @property int|null $fixed_price
 * @property int|null $is_suspend
 * @property int|null $building_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $latitude
 * @property string|null $longitude
 * @property-read \App\Models\Auction|null $activeAuction
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Auction> $auctions
 * @property-read int|null $auctions_count
 * @property-read mixed $formatted_active_auction
 * @property-read bool $has_active_auction
 * @property-read mixed $minimum_bid
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Offer> $offers
 * @property-read int|null $offers_count
 * @property-read \App\Models\User|null $owner
 * @property-read mixed $owner_nickname
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Transaction> $transactions
 * @property-read int|null $transactions_count
 * @property-read mixed $type
 * @method static \Illuminate\Database\Eloquent\Builder|Land newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Land newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Land query()
 * @method static \Illuminate\Database\Eloquent\Builder|Land whereBuildingId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Land whereCenterPoint($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Land whereCoordinates($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Land whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Land whereFixedPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Land whereFullId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Land whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Land whereIsForSale($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Land whereLatitude($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Land whereLongitude($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Land whereOwnerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Land whereRegion($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Land whereSize($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Land whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Land whereZone($value)
 */
	class Land extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property int $land_id
 * @property int $user_id
 * @property int $price
 * @property int $is_accepted
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Land $land
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder|Offer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Offer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Offer query()
 * @method static \Illuminate\Database\Eloquent\Builder|Offer whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Offer whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Offer whereIsAccepted($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Offer whereLandId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Offer wherePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Offer whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Offer whereUserId($value)
 */
	class Offer extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property string $title
 * @property string|null $description
 * @property array $rewards
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 * @method static \Database\Factories\QuestFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|Quest newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Quest newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Quest query()
 * @method static \Illuminate\Database\Eloquent\Builder|Quest whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Quest whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Quest whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Quest whereRewards($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Quest whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Quest whereUpdatedAt($value)
 */
	class Quest extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property string $name
 * @property string $type
 * @property int $amount
 * @property string $quest_name
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\UserReward> $userRewards
 * @property-read int|null $user_rewards_count
 * @method static \Database\Factories\RewardFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|Reward newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Reward newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Reward query()
 * @method static \Illuminate\Database\Eloquent\Builder|Reward whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Reward whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Reward whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Reward whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Reward whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Reward whereQuestName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Reward whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Reward whereUpdatedAt($value)
 */
	class Reward extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property int $land_id
 * @property int $seller_id
 * @property int $buyer_id
 * @property int $price
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $buyer
 * @property-read \App\Models\Land $land
 * @property-read \App\Models\User $seller
 * @method static \Illuminate\Database\Eloquent\Builder|Transaction newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Transaction newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Transaction query()
 * @method static \Illuminate\Database\Eloquent\Builder|Transaction whereBuyerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Transaction whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Transaction whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Transaction whereLandId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Transaction wherePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Transaction whereSellerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Transaction whereUpdatedAt($value)
 */
	class Transaction extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property string $address
 * @property string|null $nickname
 * @property string|null $avatar_url
 * @property string|null $coordinates
 * @property int $current_mission
 * @property int|null $referrer_id
 * @property string|null $referral_code
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, User> $allReferrals
 * @property-read int|null $all_referrals_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Asset> $assets
 * @property-read int|null $assets_count
 * @property-read mixed $assets_data
 * @property-read mixed $cp
 * @property-read mixed $meta
 * @property-read mixed $referral_tree
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Offer> $offers
 * @property-read int|null $offers_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Land> $ownedLands
 * @property-read int|null $owned_lands_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Quest> $quests
 * @property-read int|null $quests_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, User> $referrals
 * @property-read int|null $referrals_count
 * @property-read User|null $referrer
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Sanctum\PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Transaction> $transactions
 * @property-read int|null $transactions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\UserReward> $userRewards
 * @property-read int|null $user_rewards_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|User query()
 * @method static \Illuminate\Database\Eloquent\Builder|User whereAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereAvatarUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereCoordinates($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereCurrentMission($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereNickname($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereReferralCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereReferrerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereUpdatedAt($value)
 */
	class User extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property int $user_id
 * @property int $reward_id
 * @property bool $is_claimed
 * @property \Illuminate\Support\Carbon|null $claimed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Reward $reward
 * @property-read \App\Models\User $user
 * @method static \Database\Factories\UserRewardFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|UserReward newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|UserReward newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|UserReward query()
 * @method static \Illuminate\Database\Eloquent\Builder|UserReward whereClaimedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserReward whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserReward whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserReward whereIsClaimed($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserReward whereRewardId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserReward whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserReward whereUserId($value)
 */
	class UserReward extends \Eloquent {}
}

