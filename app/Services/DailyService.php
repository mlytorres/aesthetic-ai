<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Thin wrapper around the Daily.co REST API.
 * Docs: https://docs.daily.co/reference/rest-api
 *
 * Set DAILY_API_KEY and DAILY_DOMAIN in .env.
 */
class DailyService
{
    private const BASE_URL = 'https://api.daily.co/v1';

    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = (string) config('services.daily.api_key');
    }

    /**
     * Create a private Daily.co room for a consultation.
     *
     * @param  string  $roomName  Unique room identifier (e.g. "consult-{uuid}")
     * @param  int  $expiresAt  Unix timestamp when the room should auto-expire
     * @return array{name: string, url: string}
     *
     * @throws RuntimeException When the API call fails
     */
    public function createRoom(string $roomName, int $expiresAt): array
    {
        if (empty($this->apiKey)) {
            throw new RuntimeException('DAILY_API_KEY is not configured.');
        }

        $response = Http::withToken($this->apiKey)
            ->post(self::BASE_URL.'/rooms', [
                'name' => $roomName,
                'privacy' => 'private',
                'properties' => [
                    'exp' => $expiresAt,
                    'eject_at_room_exp' => true,
                    'enable_chat' => true,
                    'enable_screenshare' => false,
                    'start_video_off' => false,
                    'start_audio_off' => false,
                    // HIPAA: cloud recording is disabled by default
                    'max_participants' => 2,
                ],
            ]);

        if (! $response->successful()) {
            Log::error('Daily.co createRoom failed', [
                'room' => $roomName,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException("Daily.co createRoom failed: {$response->status()}");
        }

        $data = $response->json();

        return [
            'name' => $data['name'],
            'url' => $data['url'],
        ];
    }

    /**
     * Delete a Daily.co room. Silently ignores 404 (already deleted).
     */
    public function deleteRoom(string $roomName): void
    {
        if (empty($this->apiKey)) {
            return;
        }

        try {
            $response = Http::withToken($this->apiKey)
                ->delete(self::BASE_URL."/rooms/{$roomName}");

            if (! $response->successful() && $response->status() !== 404) {
                Log::warning('Daily.co deleteRoom failed', [
                    'room' => $roomName,
                    'status' => $response->status(),
                ]);
            }
        } catch (ConnectionException $e) {
            Log::warning('Daily.co deleteRoom connection error', ['room' => $roomName, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Create a short-lived meeting token granting access to a private room.
     *
     * @param  string  $roomName  The room to grant access to
     * @param  string  $participantName  Display name shown in the call
     * @param  int  $expiresAt  Unix timestamp (must be <= room exp)
     * @return string The meeting token string
     */
    public function createMeetingToken(string $roomName, string $participantName, int $expiresAt): string
    {
        if (empty($this->apiKey)) {
            throw new RuntimeException('DAILY_API_KEY is not configured.');
        }

        $response = Http::withToken($this->apiKey)
            ->post(self::BASE_URL.'/meeting-tokens', [
                'properties' => [
                    'room_name' => $roomName,
                    'user_name' => $participantName,
                    'exp' => $expiresAt,
                    'is_owner' => false,
                ],
            ]);

        if (! $response->successful()) {
            Log::error('Daily.co createMeetingToken failed', [
                'room' => $roomName,
                'status' => $response->status(),
            ]);
            throw new RuntimeException("Daily.co createMeetingToken failed: {$response->status()}");
        }

        return $response->json('token');
    }
}
