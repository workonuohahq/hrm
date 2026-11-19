<?php

/**
 * Vercel serverless entry point.
 *
 * Keep Laravel's canonical public front controller as the single request
 * bootstrap so the existing routes, middleware, and Inertia app remain intact.
 */
require __DIR__ . '/../public/index.php';
