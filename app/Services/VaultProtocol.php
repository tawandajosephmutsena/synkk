<?php

namespace App\Services;

use App\Models\Vault;
use Illuminate\Http\Request;

final class VaultProtocol
{
    public const CURRENT_PROTOCOL_VERSION = 2;

    public const MINIMUM_PROTOCOL_VERSION = 2;

    public function getProtocolVersion(Request $request): int
    {
        $header = $request->header('X-Synkk-Protocol');

        return $header !== null ? (int) $header : self::CURRENT_PROTOCOL_VERSION;
    }

    public function validateProtocolVersion(Request $request, ?Vault $vault = null): void
    {
        $version = $this->getProtocolVersion($request);

        if ($version < self::MINIMUM_PROTOCOL_VERSION) {
            abort(426, 'Client protocol version '.$version.' is obsolete. Please upgrade your Synkk plugin to protocol version '.self::MINIMUM_PROTOCOL_VERSION.'.');
        }
    }

    /**
     * @return array{
     *     protocol_version: int,
     *     minimum_protocol_version: int,
     *     capabilities: array<string, bool>
     * }
     */
    public function manifestCapabilities(Vault $vault): array
    {
        return [
            'protocol_version' => self::CURRENT_PROTOCOL_VERSION,
            'minimum_protocol_version' => self::MINIMUM_PROTOCOL_VERSION,
            'capabilities' => [
                'e2ee' => (bool) $vault->is_e2ee,
                'whole_file_sync' => true,
                'realtime_collaboration' => true,
                'version_history' => true,
                'ghost_files' => true,
            ],
        ];
    }
}
