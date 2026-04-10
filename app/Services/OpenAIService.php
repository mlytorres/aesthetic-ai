<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin HTTP client wrapper for the OpenAI API.
 *
 * Currently used for:
 *   • Image editing (gpt-image-1) — AI before/after simulation
 *
 * Authentication uses OPENAI_API_KEY from the environment.
 * All requests time out after 120 s to accommodate image generation latency.
 */
class OpenAIService
{
    private const BASE_URL = 'https://api.openai.com/v1';

    private const TIMEOUT = 120;

    private const IMAGE_MODEL = 'gpt-image-1';

    public function __construct(private readonly string $apiKey) {}

    // ─── Image editing ────────────────────────────────────────────────────────

    /**
     * Generate an AI-edited "after" image using the OpenAI image edit endpoint.
     *
     * @param  string  $imagePath  Absolute path to the source image (JPEG/PNG ≤ 4 MB)
     * @param  string  $prompt  Natural-language description of the desired transformation
     * @param  string  $size  Output size: '1024x1024' | '1792x1024' | '1024x1792'
     * @return string Base64-encoded PNG of the generated image
     *
     * @throws RuntimeException On API error or unexpected response shape
     */
    public function editImage(string $imagePath, string $prompt, string $size = '1024x1024'): string
    {
        $response = Http::withToken($this->apiKey)
            ->timeout(self::TIMEOUT)
            ->attach('image', fopen($imagePath, 'r'), basename($imagePath))
            ->post(self::BASE_URL.'/images/edits', [
                'model' => self::IMAGE_MODEL,
                'prompt' => $prompt,
                'n' => 1,
                'size' => $size,
                'response_format' => 'b64_json',
            ]);

        $this->assertSuccess($response, 'image edit');

        $b64 = $response->json('data.0.b64_json');

        if (! is_string($b64) || $b64 === '') {
            throw new RuntimeException('OpenAI image edit returned empty b64_json');
        }

        return $b64;
    }

    /**
     * Generate a simulation image purely from a text prompt (no source photo required).
     * Useful for procedure demonstrations when no patient photo is available.
     *
     * @return string Base64-encoded PNG
     *
     * @throws RuntimeException
     */
    public function generateImage(string $prompt, string $size = '1024x1024'): string
    {
        $response = Http::withToken($this->apiKey)
            ->timeout(self::TIMEOUT)
            ->post(self::BASE_URL.'/images/generations', [
                'model' => self::IMAGE_MODEL,
                'prompt' => $prompt,
                'n' => 1,
                'size' => $size,
                'response_format' => 'b64_json',
            ]);

        $this->assertSuccess($response, 'image generation');

        $b64 = $response->json('data.0.b64_json');

        if (! is_string($b64) || $b64 === '') {
            throw new RuntimeException('OpenAI image generation returned empty b64_json');
        }

        return $b64;
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Throw a descriptive RuntimeException when the OpenAI API returns a non-2xx response.
     *
     * @throws RuntimeException
     */
    private function assertSuccess(Response $response, string $operation): void
    {
        if ($response->successful()) {
            return;
        }

        $message = $response->json('error.message') ?? $response->body();

        throw new RuntimeException(
            sprintf('OpenAI %s failed [%d]: %s', $operation, $response->status(), $message),
        );
    }
}
