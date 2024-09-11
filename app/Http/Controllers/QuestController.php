<?php

namespace App\Http\Controllers;

use App\Models\Quest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QuestController extends Controller
{
    public function index()
    {
        $quests = Quest::all();
        return response()->json($quests);
    }

    public function show(Quest $quest)
    {
        return response()->json($quest);
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'rewards' => 'required|array',
            'costs' => 'nullable|array',
        ]);

        $quest = Quest::create($validatedData);
        return response()->json($quest, 201);
    }

    public function update(Request $request, Quest $quest)
    {
        $validatedData = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'rewards' => 'sometimes|required|array',
            'costs' => 'nullable|array',
        ]);

        $quest->update($validatedData);
        return response()->json($quest);
    }

    public function destroy(Quest $quest)
    {
        $quest->delete();
        return response()->json(null, 204);
    }

    public function complete(Request $request)
    {
        $quest_id = $request->input('quest_id');

        Log::info('Starting quest completion for user ' . Auth::id() . ' and quest ' . $quest_id);

        return DB::transaction(function () use ($quest_id) {
            $user = Auth::user();
            
            if (!$user) {
                Log::error('No authenticated user found');
                return response()->json(['message' => 'User not authenticated'], 401);
            }

            $quest = Quest::find($quest_id);

            if (!$quest) {
                Log::error('Quest not found: ' . $quest_id);
                return response()->json(['message' => 'Quest not found'], 404);
            }

            Log::info('Checking if quest is already completed');
            $completed = $user->quests()->where('quest_id', $quest_id)->wherePivot('completed_at', '!=', null)->exists();
            
            if ($completed) {
                Log::info('Quest already completed');
                return response()->json(['message' => 'Quest already completed'], 400);
            }

            // Check and deduct costs
            if ($quest->costs) {
                Log::info('Checking and deducting costs');
                foreach ($quest->costs as $cost) {
                    $deducted = $user->updateAsset($cost['type'], -$cost['amount']);
                    if (!$deducted) {
                        Log::error('Failed to deduct cost from user');
                        return response()->json(['message' => 'Insufficient resources to complete quest'], 400);
                    }
                }
            }

            Log::info('Attaching quest to user');
            $user->quests()->attach($quest_id, ['completed_at' => now()]);

            // Award rewards
            if ($quest->rewards) {
                Log::info('Processing rewards');
                foreach ($quest->rewards as $reward) {
                    Log::info('Adding reward: ' . json_encode($reward));
                    $rewardAdded = $user->updateAsset($reward['type'], $reward['amount']);

                    if (!$rewardAdded) {
                        Log::error('Failed to add reward to user');
                        throw new \Exception('Failed to add reward to user');
                    }
                }
            }

            Log::info('Quest completed successfully');
            return response()->json(['message' => 'Quest completed and rewards added'], 200);
        });
    }

    public function userQuests(Request $request)
    {
        $user = Auth::user();
        $quests = $user->quests()->with('pivot')->get();
        return response()->json($quests);
    }

    public function availableQuests(Request $request)
    {
        $user = Auth::user();
        $completedQuestIds = $user->quests()->pluck('quests.id');
        $availableQuests = Quest::whereNotIn('id', $completedQuestIds)->get();
        return response()->json($availableQuests);
    }
}