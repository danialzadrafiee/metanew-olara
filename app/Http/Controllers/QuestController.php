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
        Log::info("Attempting to complete quest", ['quest_id' => $quest_id]);
    
        return DB::transaction(function () use ($quest_id) {
            $user = Auth::user();
    
            if (!$user) {
                Log::error('No authenticated user found');
                return response()->json(['message' => 'User not authenticated'], 401);
            }
    
            $quest = Quest::find($quest_id);
    
            if (!$quest) {
                Log::error('Quest not found', ['quest_id' => $quest_id]);
                return response()->json(['message' => 'Quest not found'], 404);
            }
    
            if ($user->quests()->where('quest_id', $quest_id)->wherePivot('completed_at', '!=', null)->exists()) {
                return response()->json(['message' => 'Quest already completed'], 400);
            }
    
            // Decode JSON data
            $costs = json_decode($quest->costs, true);
            $rewards = json_decode($quest->rewards, true);
    
            // Deduct costs
            if ($costs) {
                foreach ($costs as $cost) {
                    if (!$user->removeAsset($cost['type'], $cost['amount'])) {
                        Log::error('Insufficient resources to complete quest', [
                            'user_id' => $user->id,
                            'cost_type' => $cost['type'],
                            'cost_amount' => $cost['amount']
                        ]);
                        return response()->json(['message' => 'Insufficient resources to complete quest'], 400);
                    }
                }
            }
    
            // Add rewards
            if ($rewards) {
                foreach ($rewards as $reward) {
                    if (!$user->addAsset($reward['type'], $reward['amount'])) {
                        Log::error('Failed to add reward to user', [
                            'user_id' => $user->id,
                            'reward_type' => $reward['type'],
                            'reward_amount' => $reward['amount']
                        ]);
                        throw new \Exception('Failed to add reward to user');
                    }
                }
            }
    
            $user->quests()->attach($quest_id, ['completed_at' => now()]);
            Log::info("Quest completed successfully", ['user_id' => $user->id, 'quest_id' => $quest_id]);
    
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
