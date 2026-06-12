<?php

declare(strict_types=1);

namespace App\Http\Controllers\Help;

use App\Http\Controllers\Controller;
use App\Services\HandbookService;
use Inertia\Inertia;
use Inertia\Response;

class HelpController extends Controller
{
    public function __construct(private HandbookService $handbook) {}

    public function index(): Response
    {
        abort_unless($this->handbook->isEnabled(), 404);

        return Inertia::render('help/index', [
            'chapters' => $this->handbook->chapters(),
        ]);
    }

    public function show(string $slug): Response
    {
        abort_unless($this->handbook->isEnabled(), 404);

        $article = $this->handbook->article($slug);
        abort_if($article === null, 404);

        return Inertia::render('help/show', [
            'article' => $article,
            'chapters' => $this->handbook->chapters(),
        ]);
    }
}
