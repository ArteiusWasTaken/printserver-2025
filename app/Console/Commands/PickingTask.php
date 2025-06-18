<?php

namespace App\Console\Commands;

use App\Services\PickingService;
use Exception;
use Illuminate\Console\Command;

/**
 *
 */
class PickingTask extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'picking:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Llama a la función picking del API';

    protected PickingService $pickingService;

    /**
     * Create a new console command instance.
     *
     * @return void
     */
    public function __construct(PickingService $pickingService)
    {
        parent::__construct();
        $this->pickingService = $pickingService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $resultado = $this->pickingService->rawinfoPicking();

            $this->info('Resultado: ' . $resultado);
        } catch (Exception $e) {
            $this->error('Error ejecutando picking: ' . $e->getMessage());
        }

        return 0;
    }
}
