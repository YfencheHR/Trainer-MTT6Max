<?php

namespace App\Services\Equity;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EquitySimulationClient
{
    protected string $baseUrl;
    protected int $timeout = 300; // 5 minutos

    public function __construct()
    {
        $this->baseUrl = config('services.python.url') ?? 'http://python-service:5000';
    }

    /**
     * Simula equity preflop
     *
     * @param string $heroHand - ej 'AKs'
     * @param array $villainRange - ej ['AA', 'KK', 'AKs']
     * @param int $iterations - número de simulaciones
     */
    public function simulateEquity(string $heroHand, array $villainRange, int $iterations = 5000): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->post($this->baseUrl . '/api/equity/simulate', [
                    'hero_hand' => $heroHand,
                    'villain_range' => $villainRange,
                    'iterations' => $iterations,
                ]);

            if ($response->failed()) {
                Log::error('Equity simulation failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \Exception('Equity simulation failed: ' . $response->body());
            }

            return $response->json('result', []);
        } catch (\Exception $e) {
            Log::error('Equity simulation error', [
                'error' => $e->getMessage(),
                'hero_hand' => $heroHand,
            ]);
            throw $e;
        }
    }

    /**
     * Genera tabla de ataque para un stack depth
     *
     * @param int $stackDepthBb
     * @param int $defenseRangePercent
     */
    public function generateAttackTable(int $stackDepthBb, int $defenseRangePercent = 20): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->post($this->baseUrl . '/api/tables/generate/attack', [
                    'stack_depth_bb' => $stackDepthBb,
                    'defense_range_percent' => $defenseRangePercent,
                ]);

            if ($response->failed()) {
                Log::error('Attack table generation failed', [
                    'status' => $response->status(),
                ]);
                throw new \Exception('Attack table generation failed');
            }

            $data = $response->json();
            return [
                'stack_depth_bb' => $stackDepthBb,
                'type' => 'ATTACK',
                'range' => $data['opening_range'] ?? [],
                'range_percent' => $data['range_percent'] ?? 0,
                'tables' => $data['tables'] ?? [],
            ];
        } catch (\Exception $e) {
            Log::error('Attack table generation error', [
                'error' => $e->getMessage(),
                'stack_depth_bb' => $stackDepthBb,
            ]);
            throw $e;
        }
    }

    /**
     * Genera tabla de defensa para un stack depth
     */
    public function generateDefenseTable(int $stackDepthBb, int $attackRangePercent = 15): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->post($this->baseUrl . '/api/tables/generate/defense', [
                    'stack_depth_bb' => $stackDepthBb,
                    'attack_range_percent' => $attackRangePercent,
                ]);

            if ($response->failed()) {
                Log::error('Defense table generation failed', [
                    'status' => $response->status(),
                ]);
                throw new \Exception('Defense table generation failed');
            }

            $data = $response->json();
            return [
                'stack_depth_bb' => $stackDepthBb,
                'type' => 'DEFENSE',
                'range' => $data['defending_range'] ?? [],
                'range_percent' => $data['range_percent'] ?? 0,
                'tables' => $data['tables'] ?? [],
            ];
        } catch (\Exception $e) {
            Log::error('Defense table generation error', [
                'error' => $e->getMessage(),
                'stack_depth_bb' => $stackDepthBb,
            ]);
            throw $e;
        }
    }

    /**
     * Health check del servicio Python
     */
    public function healthCheck(): bool
    {
        try {
            $response = Http::timeout(5)->get($this->baseUrl . '/health');
            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Python service health check failed', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
