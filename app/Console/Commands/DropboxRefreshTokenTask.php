<?php

namespace App\Console\Commands;

use App\Services\DropboxService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Http\JsonResponse;

/**
 *
 */
class DropboxRefreshTokenTask extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dropbox:refreshToken';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Renovar el token de Dropbox cada x horas';

    protected DropboxService $dropboxService;

    /**
     * Create a new console command instance.
     *
     * @return void
     */
    public function __construct(DropboxService $dropboxService)
    {
        parent::__construct();
        $this->dropboxService = $dropboxService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): JsonResponse
    {
        set_time_limit(0);
        $dropbox = new DropboxService();
        try {
            $token = $dropbox->refreshAccessToken();

            return response()->json(['code' => 200, 'token' => $token]);
        } catch (Exception $e) {
            return response()->json(['code' => 500, 'error' => $e->getMessage()]);
        }
    }
}
