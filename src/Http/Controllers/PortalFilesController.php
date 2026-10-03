<?php

namespace RadThemes\AlpCrm\Http\Controllers;

use RadThemes\AlpCrm\Portal\PortalPages;
use Statamic\Facades\User;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Client portal downloads: only files shared with the logged-in client.
 */
class PortalFilesController
{
    public function __invoke(int $file): StreamedResponse
    {
        $user = User::current() ?? abort(403);

        return (PortalPages::files($user)->whereKey($file)->first() ?? abort(404))->download();
    }
}
