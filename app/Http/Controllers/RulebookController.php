<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RulebookController extends Controller
{
    /**
     * The rulebook, served as the PDF itself.
     *
     * It is the one in docs/ rather than a copy under public/, because that is
     * the file this application treats as the source of truth, and a second
     * copy is a copy that falls behind the next time the rules are revised.
     * Inline rather than as a download, so it opens in the browser's own
     * viewer in the tab the sidebar sent it to.
     */
    public function __invoke(): BinaryFileResponse
    {
        return response()->file(base_path('docs/running-hot-rulebook.pdf'), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="running-hot-rulebook.pdf"',
        ]);
    }
}
