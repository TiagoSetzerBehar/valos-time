<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;

/**
 * VALO's Time: a member who is logged in but does not remember the current password can ask for the
 * same "reset your password" email that the login page sends. It always goes to the member's own address.
 */
class ValoPasswordLinkController extends Controller
{
    public function send(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $status = Password::broker(config('fortify.passwords'))->sendResetLink(['email' => $user->email]);

        if ($status === Password::RESET_THROTTLED) {
            return response()->json(['sent' => false, 'message' => __($status)], 429);
        }
        if ($status !== Password::RESET_LINK_SENT) {
            return response()->json(['sent' => false, 'message' => __($status)], 422);
        }

        return response()->json(['sent' => true, 'email' => $user->email]);
    }
}
