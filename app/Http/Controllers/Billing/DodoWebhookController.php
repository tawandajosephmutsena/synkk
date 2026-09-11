<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Services\DodoWebhookProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use JsonException;
use Throwable;
use UnexpectedValueException;

class DodoWebhookController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, DodoWebhookProcessor $processor): JsonResponse
    {
        try {
            $processor->process(
                $request->getContent(),
                (string) $request->header('webhook-id'),
                (string) $request->header('webhook-signature'),
                (string) $request->header('webhook-timestamp'),
            );
        } catch (InvalidArgumentException) {
            return response()->json(['message' => 'Invalid webhook signature.'], 401);
        } catch (JsonException|UnexpectedValueException) {
            return response()->json(['message' => 'Invalid webhook payload.'], 400);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'Webhook processing failed.'], 500);
        }

        return response()->json(['received' => true]);
    }
}
