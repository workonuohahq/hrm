<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class CheckInstallation
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // Vercel deployments use environment variables, not the installer's
        // ephemeral storage/installed marker. Close installer routes explicitly
        // once provisioning has been completed through APP_INSTALLED=true.
        if ($request->is('install') || $request->is('install/*')) {
            if ((bool) config('app.installed', false)) {
                abort(404);
            }

            return $next($request);
        }

        // Skip check for installer routes, API routes, and static assets
        if (
            $request->is('update/*') ||
            $request->is('css/*') ||
            $request->is('js/*') ||
            $request->is('images/*') ||
            $request->is('installer/*')
        ) {
            return $next($request);
        }

        // Check only on dashboard, login, register routes
        if (!$request->is('/*') && !$request->is('dashboard*') && !$request->is('login') && !$request->is('register')) {
            return $next($request);
        }

        // APP_INSTALLED is authoritative for Vercel's stateless filesystem.
        if (!$this->isInstalled()) {
            return redirect('/install');
        }

        // If logged in as superadmin and migrations needed, redirect to /update
        if (isSaas()) {
            if (auth()->check() && auth()->user()->hasRole('superadmin') && $this->needsMigration()) {
                return redirect('/update');
            }
        } else {
            if (auth()->check() && auth()->user()->hasRole('company') && $this->needsMigration()) {
                return redirect('/update');
            }
        }

        return $next($request);
    }

    /**
     * Check if application is installed.
     *
     * APP_INSTALLED is required on Vercel because its filesystem is ephemeral
     * and the installer's local installation marker is not persistent.
     */
    private function isInstalled(): bool
    {
        return (bool) config('app.installed', false) || isAppInstalled();
    }

    /**
     * Check if migrations are needed
     */
    private function needsMigration(): bool
    {
        try {
            Artisan::call('migrate:status');
            $output = Artisan::output();
            return strpos($output, 'Pending') !== false;
        } catch (\Exception $e) {
            return false;
        }
    }
}
