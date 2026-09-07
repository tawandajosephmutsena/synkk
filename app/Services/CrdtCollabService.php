<?php

namespace App\Services;

use App\Models\User;
use App\Models\Vault;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * @phpstan-type CollabPeer array{
 *     peer_id: string,
 *     user_id: int,
 *     name: string,
 *     email: string,
 *     color: string,
 *     cursor: array{line: int, col: int},
 *     last_seen: int
 * }
 */
class CrdtCollabService
{
    protected const PEER_TTL_SECONDS = 45;

    protected const ROOM_TTL_SECONDS = 3600;

    protected const MAX_DELTAS_RETAINED = 200;

    /**
     * Color palette for active collaborator avatars and carets.
     *
     * @var array<int, string>
     */
    protected array $avatarColors = [
        '#10B981', // emerald
        '#6366F1', // indigo
        '#F59E0B', // amber
        '#EC4899', // pink
        '#3B82F6', // blue
        '#8B5CF6', // purple
        '#14B8A6', // teal
        '#F97316', // orange
    ];

    /**
     * Join a note collaboration room, registering presence.
     *
     * @return array{
     *     status: string,
     *     room_id: string,
     *     clock: int,
     *     peers: list<CollabPeer>,
     *     deltas: array<int, mixed>
     * }
     */
    public function join(Vault $vault, User $user, string $path, string $peerId): array
    {
        $roomKey = $this->roomKey($vault, $path);
        $lockKey = $roomKey.'_lock';

        /** @var array{status: string, room_id: string, clock: int, peers: list<CollabPeer>, deltas: array<int, mixed>} $result */
        $result = Cache::lock($lockKey, 5)->block(2, function () use ($roomKey, $user, $peerId) {
            $room = $this->getRoom($roomKey);

            $color = $this->avatarColors[$user->id % count($this->avatarColors)];

            $room['peers'][$peerId] = [
                'peer_id' => $peerId,
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'color' => $color,
                'cursor' => ['line' => 1, 'col' => 0],
                'last_seen' => time(),
            ];

            $room['peers'] = $this->pruneStalePeers($room['peers']);
            $this->saveRoom($roomKey, $room);

            return [
                'status' => 'joined',
                'room_id' => $roomKey,
                'clock' => $room['clock'],
                'peers' => array_values($room['peers']),
                'deltas' => array_slice($room['deltas'], -50),
            ];
        });

        return $result;
    }

    /**
     * Sync local CRDT deltas and cursor position, returning unread deltas from other peers.
     *
     * @param  array<int, array{type: string, pos: int, text?: string, len?: int}>  $localDeltas
     * @param  array{line: int, col: int}|null  $cursor
     * @return array{
     *     status: string,
     *     clock: int,
     *     incoming_deltas: array<int, array{
     *         clock: int,
     *         peer_id: string,
     *         type: string,
     *         pos: int,
     *         text?: string,
     *         len?: int,
     *         timestamp: int
     *     }>,
     *     peers: list<CollabPeer>
     * }
     */
    public function sync(
        Vault $vault,
        User $user,
        string $path,
        string $peerId,
        array $localDeltas = [],
        ?array $cursor = null,
        int $sinceClock = 0
    ): array {
        $roomKey = $this->roomKey($vault, $path);
        $lockKey = $roomKey.'_lock';

        /** @var array{status: string, clock: int, incoming_deltas: array<int, array{clock: int, peer_id: string, type: string, pos: int, text?: string, len?: int, timestamp: int}>, peers: list<CollabPeer>} $result */
        $result = Cache::lock($lockKey, 5)->block(2, function () use ($roomKey, $user, $peerId, $localDeltas, $cursor, $sinceClock) {
            $room = $this->getRoom($roomKey);

            $color = $this->avatarColors[$user->id % count($this->avatarColors)];

            // Update heartbeat and cursor
            $room['peers'][$peerId] = [
                'peer_id' => $peerId,
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'color' => $color,
                'cursor' => $cursor ?? ($room['peers'][$peerId]['cursor'] ?? ['line' => 1, 'col' => 0]),
                'last_seen' => time(),
            ];

            // Ingest new local deltas into room stream
            foreach ($localDeltas as $delta) {
                $room['clock']++;
                $room['deltas'][] = [
                    'clock' => $room['clock'],
                    'peer_id' => $peerId,
                    'user_id' => $user->id,
                    'type' => $delta['type'],
                    'pos' => (int) $delta['pos'],
                    'text' => (string) ($delta['text'] ?? ''),
                    'len' => (int) ($delta['len'] ?? 0),
                    'timestamp' => time(),
                ];
            }

            // Cap history to prevent memory bloat
            if (count($room['deltas']) > self::MAX_DELTAS_RETAINED) {
                $room['deltas'] = array_slice($room['deltas'], -self::MAX_DELTAS_RETAINED);
            }

            $room['peers'] = $this->pruneStalePeers($room['peers']);
            $this->saveRoom($roomKey, $room);

            // Filter incoming deltas originating from OTHER peers since $sinceClock
            $incomingDeltas = [];
            foreach ($room['deltas'] as $delta) {
                if ($delta['clock'] > $sinceClock && $delta['peer_id'] !== $peerId) {
                    $incomingDeltas[] = $delta;
                }
            }

            return [
                'status' => 'synced',
                'clock' => $room['clock'],
                'incoming_deltas' => $incomingDeltas,
                'peers' => array_values($room['peers']),
            ];
        });

        return $result;
    }

    /**
     * Leave a collaboration room.
     */
    public function leave(Vault $vault, string $path, string $peerId): void
    {
        $roomKey = $this->roomKey($vault, $path);
        $lockKey = $roomKey.'_lock';

        Cache::lock($lockKey, 5)->block(2, function () use ($roomKey, $peerId) {
            $room = $this->getRoom($roomKey);

            unset($room['peers'][$peerId]);
            $room['peers'] = $this->pruneStalePeers($room['peers']);

            $this->saveRoom($roomKey, $room);
        });
    }

    /**
     * Get active presence list for a note.
     *
     * @return list<CollabPeer>
     */
    public function getPresence(Vault $vault, string $path): array
    {
        $roomKey = $this->roomKey($vault, $path);
        $room = $this->getRoom($roomKey);
        $peers = $this->pruneStalePeers($room['peers']);

        return array_values($peers);
    }

    /**
     * Get active peers in a room as a collection.
     *
     * @return Collection<int, CollabPeer>
     */
    public function getRoomPeers(Vault $vault, string $path = 'general'): Collection
    {
        return collect($this->getPresence($vault, $path));
    }

    /**
     * Apply character-level CRDT deltas sequentially to a document string.
     *
     * @param  array<int, array{type: string, pos: int, text?: string, len?: int}>  $deltas
     */
    public function applyDeltasToContent(string $content, array $deltas): string
    {
        foreach ($deltas as $op) {
            $pos = max(0, min(strlen($content), (int) $op['pos']));
            $type = $op['type'];

            if ($type === 'insert') {
                $text = (string) ($op['text'] ?? '');
                $content = substr($content, 0, $pos).$text.substr($content, $pos);
            } elseif ($type === 'delete') {
                $len = max(0, (int) ($op['len'] ?? 1));
                $content = substr($content, 0, $pos).substr($content, $pos + $len);
            }
        }

        return $content;
    }

    /**
     * Format a canonical room cache key.
     */
    protected function roomKey(Vault $vault, string $path): string
    {
        $cleanPath = ltrim(str_replace('\\', '/', $path), '/');

        return "synkk_collab_v{$vault->id}_".md5($cleanPath);
    }

    /**
     * Get room state from cache.
     *
     * @return array{
     *     clock: int,
     *     peers: array<string, CollabPeer>,
     *     deltas: array<int, mixed>
     * }
     */
    protected function getRoom(string $roomKey): array
    {
        return Cache::get($roomKey, [
            'clock' => 0,
            'peers' => [],
            'deltas' => [],
        ]);
    }

    /**
     * Save room state to cache.
     *
     * @param  array{clock: int, peers: array<string, CollabPeer>, deltas: array<int, mixed>}  $room
     */
    protected function saveRoom(string $roomKey, array $room): void
    {
        Cache::put($roomKey, $room, now()->addSeconds(self::ROOM_TTL_SECONDS));
    }

    /**
     * Filter out peers who haven't pinged in the last TTL seconds.
     *
     * @param  array<string, CollabPeer>  $peers
     * @return array<string, CollabPeer>
     */
    protected function pruneStalePeers(array $peers): array
    {
        $cutoff = time() - self::PEER_TTL_SECONDS;

        return array_filter($peers, fn (array $p): bool => $p['last_seen'] >= $cutoff);
    }
}
