<?php

declare(strict_types=1);

namespace App\Session;

use Illuminate\Support\Facades\Redis;
use SessionHandlerInterface;

class FuelPhpSessionHandler implements SessionHandlerInterface
{
    private int $expiration;

    /** @var array<string, mixed> */
    private array $fuelKeys = [];

    /** @var array<string, mixed> */
    private array $fuelFlash = [];

    public function __construct(int $expiration = 7200)
    {
        $this->expiration = $expiration;
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $sessionId): string|false
    {
        $raw = Redis::connection('fuelphp_session')->get($sessionId);

        if ($raw === null || $raw === false) {
            return '';
        }

        $payload = @unserialize($raw);

        if (!is_array($payload)) {
            return '';
        }

        // Handle session rotation: follow rotated_session_id pointer
        if (isset($payload['rotated_session_id'])) {
            $raw = Redis::connection('fuelphp_session')->get($payload['rotated_session_id']);

            if ($raw === null || $raw === false) {
                return '';
            }

            $payload = @unserialize($raw);

            if (!is_array($payload)) {
                return '';
            }
        }

        // FuelPHP payload structure: [$keys, $data, $flash]
        $this->fuelKeys = isset($payload[0]) && is_array($payload[0]) ? $payload[0] : [];
        $data = isset($payload[1]) && is_array($payload[1]) ? $payload[1] : [];
        $this->fuelFlash = isset($payload[2]) && is_array($payload[2]) ? $payload[2] : [];

        // Reverse FuelPHP's slash encoding in data values
        $data = $this->unescapeSlashes($data);

        // Return serialized data for Laravel's session store
        return serialize($data);
    }

    public function write(string $sessionId, string $data): bool
    {
        $laravelData = @unserialize($data);

        if (!is_array($laravelData)) {
            $laravelData = [];
        }

        // Ensure FuelPHP's required keys metadata exists
        $now = time();
        if (empty($this->fuelKeys)) {
            $request = request();
            $this->fuelKeys = [
                'session_id'  => $sessionId,
                'previous_id' => $sessionId,
                'ip_hash'     => md5($request->ip() . $request->ip()),
                'user_agent'  => $request->userAgent() ?? '',
                'created'     => $now,
                'updated'     => $now,
                'payload'     => '',
            ];
        }

        // Update the timestamp in FuelPHP's keys
        $this->fuelKeys['updated'] = $now;

        // Apply FuelPHP's slash encoding to data values
        $encodedData = $this->escapeSlashes($laravelData);

        // Rebuild the FuelPHP payload: [$keys, $data, $flash]
        $payload = serialize([$this->fuelKeys, $encodedData, $this->fuelFlash]);

        Redis::connection('fuelphp_session')->setex($sessionId, $this->expiration, $payload);

        return true;
    }

    public function destroy(string $sessionId): bool
    {
        Redis::connection('fuelphp_session')->del($sessionId);

        return true;
    }

    public function gc(int $maxLifetime): int
    {
        // FuelPHP uses Redis TTL for expiration, no garbage collection needed
        return 0;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function escapeSlashes(array $data): array
    {
        foreach ($data as $key => $val) {
            if (is_string($val)) {
                $data[$key] = str_replace('\\', '{{slash}}', $val);
            }
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function unescapeSlashes(array $data): array
    {
        foreach ($data as $key => $val) {
            if (is_string($val)) {
                $data[$key] = str_replace('{{slash}}', '\\', $val);
            }
        }

        return $data;
    }
}
