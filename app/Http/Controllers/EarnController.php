<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Asset;
use App\Models\Earn;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EarnController extends Controller
{
    public function distribute(Request $request)
    {
        $validated = $request->validate([
            'meta_amount' => 'required|numeric|min:0',
        ]);

        $metaAmount = $validated['meta_amount'];

        return DB::transaction(function () use ($metaAmount) {
            $users = User::with(['assets' => function ($query) {
                $query->where('type', 'cp');
            }])->get();

            $totalCp = $users->sum(function ($user) {
                return $user->assets->where('type', 'cp')->first()->amount ?? 0;
            });

            if ($totalCp == 0) {
                return response()->json(['error' => 'No one has any CP to distribute']);
            }

            $userDistributions = [];
            $totalCpRemoved = 0;
            $totalMetaDistributed = 0;

            foreach ($users as $user) {
                $userCp = $user->assets->where('type', 'cp')->first()->amount ?? 0;
                $userShare = ($userCp / $totalCp) * $metaAmount;

                // Round down to nearest 0.5
                $roundedShare = floor($userShare * 2) / 2;

                // Remove all CP
                $user->setAssetExact('cp', 0);

                // Add the user's share of META
                $user->addAsset('meta', $roundedShare);

                $userDistributions[] = [
                    'user_id' => $user->id,
                    'cp_removed' => $userCp,
                    'meta_added' => $roundedShare,
                ];

                $totalCpRemoved += $userCp;
                $totalMetaDistributed += $roundedShare;
            }

            // Record the transaction
            $earn = Earn::create([
                'meta_amount' => $metaAmount,
                'users_count' => $users->count(),
                'total_cp_removed' => $totalCpRemoved,
                'user_distributions' => $userDistributions,
            ]);

            // Prepare the response
            $response = [
                'message' => 'Distribution completed successfully',
                'transaction_id' => $earn->id,
                'meta_amount_distributed' => $totalMetaDistributed,
                'users_count' => $users->count(),
                'total_cp_removed' => $totalCpRemoved,
                'user_distributions' => $userDistributions,
            ];

            return response()->json([
                'message' => 'Distribution completed successfully',
                'details' => $response
            ], 200);
        });
    }

    public function history()
    {
        $earns = Earn::orderBy('created_at', 'desc')->get();
        return response()->json($earns);
    }
}
