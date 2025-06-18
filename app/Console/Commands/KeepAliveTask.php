<?php

namespace App\Console\Commands;

use App\Services\KeepAliveService;
use Exception;
use Illuminate\Console\Command;

/**
 *
 */
class KeepAliveTask extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'printers:keepAlive';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Keep Alive para las imrpesoras Zebra';

    protected KeepAliveService $keepAliveService;

    /**
     * Create a new console command instance.
     *
     * @return void
     */
    public function __construct(KeepAliveService $keepAliveService)
    {
        parent::__construct();
        $this->keepAliveService = $keepAliveService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $resultado = $this->keepAliveService->keepAlive();

            $this->info('Resultado: ' . $resultado);
        } catch (Exception $e) {
            $this->error('Error ejecutando picking: ' . $e->getMessage());
        }

        return 0;
    }
}
