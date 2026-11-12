<?php

namespace App\Providers;

use App\Services\Bots\BotDecisionService;
use App\Services\Bots\Brain\BrainAssembler;
use App\Services\Bots\Brain\BrainRepository;
use App\Services\Bots\Brain\BrainRunner;
use App\Services\Bots\Brain\Perceiver;
use App\Services\Bots\Brain\PreflopRanking;
use App\Services\Bots\Brain\RuleEngine;
use App\Services\Equity\EquitySimulationClient;
use App\Services\Equity\HandMatrixGenerator;
use App\Services\Equity\HeuristicPreflopStrategyGenerator;
use App\Services\Ev\EquityCalculator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Sistema de decisión de los bots: una sola instancia por petición (reutiliza cerebros y la tabla de manos)
        foreach ([
            PreflopRanking::class,
            Perceiver::class,
            RuleEngine::class,
            BrainRunner::class,
            BrainAssembler::class,
            BrainRepository::class,
            BotDecisionService::class,
            EquityCalculator::class,
            EquitySimulationClient::class,
            HandMatrixGenerator::class,
            HeuristicPreflopStrategyGenerator::class,
        ] as $class) {
            $this->app->singleton($class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
