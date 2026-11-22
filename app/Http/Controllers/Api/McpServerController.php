<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Services\Ai\McpServerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class McpServerController extends Controller
{
    public function __construct(
        protected McpServerService $mcpServer,
    ) {}

    public function handle(Request $request): JsonResponse|Response
    {
        /** @var DeviceToken $deviceToken */
        $deviceToken = $request->attributes->get('device_token');

        $decoded = json_decode((string) $request->getContent(), true);
        if (! is_array($decoded)) {
            return response()->json([
                'jsonrpc' => '2.0',
                'id' => null,
                'error' => [
                    'code' => -32700,
                    'message' => 'Parse error: expected valid JSON-RPC 2.0 body.',
                ],
            ]);
        }

        // Batch JSON-RPC request support
        if (array_is_list($decoded)) {
            if (empty($decoded)) {
                return response()->json([
                    'jsonrpc' => '2.0',
                    'id' => null,
                    'error' => [
                        'code' => -32600,
                        'message' => 'Invalid Request: empty batch.',
                    ],
                ]);
            }

            $responses = [];
            foreach ($decoded as $item) {
                if (! is_array($item)) {
                    $responses[] = [
                        'jsonrpc' => '2.0',
                        'id' => null,
                        'error' => ['code' => -32600, 'message' => 'Invalid Request.'],
                    ];

                    continue;
                }
                $res = $this->mcpServer->handleJsonRpc($item, $deviceToken);
                if ($res !== null) {
                    $responses[] = $res;
                }
            }

            return empty($responses) ? response()->noContent(202) : response()->json($responses);
        }

        $result = $this->mcpServer->handleJsonRpc($decoded, $deviceToken);

        if ($result === null) {
            return response()->noContent(202);
        }

        return response()->json($result);
    }
}
