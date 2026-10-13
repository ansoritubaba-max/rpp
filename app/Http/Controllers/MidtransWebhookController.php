<?php

namespace App\Http\Controllers;

use App\Services\Subscription\MidtransQrisService;
use App\Services\Subscription\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MidtransWebhookController extends Controller
{
    public function __invoke(Request $request, MidtransQrisService $qris, PaymentService $payments): JsonResponse
    {
        if (! $qris->acceptNotification($request->all(), $payments)) {
            return response()->json(['message' => 'Notifikasi tidak valid.'], 403);
        }

        return response()->json(['message' => 'OK']);
    }
}
