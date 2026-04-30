<?php

declare(strict_types=1);

namespace App\Http\Controllers\Clinic;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class ApiDocumentationController extends Controller
{
    /**
     * Staff-facing CRM & REST API reference (this product only).
     */
    public function __invoke(): Response
    {
        return Inertia::render('clinic/api-docs', [
            'appUrl' => (string) config('app.url'),
        ]);
    }
}
