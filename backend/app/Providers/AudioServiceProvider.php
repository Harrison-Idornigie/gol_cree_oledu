<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Tenants\Media\AudioProcessingService;

class AudioServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AudioProcessingService::class, function ($app) {
            // AudioProcessingService uses FFmpeg/FFprobe directly
            return new AudioProcessingService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Ensure FFmpeg is installed and accessible
        if (!$this->checkFfmpeg()) {
            $this->app['log']->warning('FFmpeg is not installed or not accessible. Audio processing features may be limited.');
        }
    }

    /**
     * Check if FFmpeg is installed and accessible.
     */
    protected function checkFfmpeg(): bool
    {
        exec('which ffmpeg', $output, $returnCode);
        return $returnCode === 0;
    }
}