<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;

class ShareGlobalSettings
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // Ensure storage link exists
        $this->ensureStorageLink();

        // Skip during installation
        if (!$request->is('install/*') && !$request->is('update/*') && isAppInstalled()) {
            // Share settings with all Inertia responses
            Inertia::share([
                'globalSettings' => function () {
                    return settings(); // Use our helper function
                }
            ]);
        }

        return $next($request);
    }

    /**
     * Ensure storage symlink exists
     */
    private function ensureStorageLink()
    {
        // Serverless deployments use remote object storage; do not try to create
        // a symlink in Vercel's read-only deployment filesystem.
        if (config('app.vercel')) {
            return;
        }

        if (!File::exists(public_path('storage'))) {
            try {
                Artisan::call('storage:link');
            } catch (\Exception $e) {
                // Silently fail if unable to create link
            }
        }
    }
}