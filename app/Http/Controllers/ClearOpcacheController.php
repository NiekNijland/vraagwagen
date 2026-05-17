<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class ClearOpcacheController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $expected = (string) config('app.deploy_token');
        $supplied = $request->query->getString('token', '');

        if ($expected === '' || ! hash_equals($expected, $supplied)) {
            abort(403);
        }

        if (! function_exists('opcache_reset')) {
            abort(500, 'OPcache not available');
        }

        opcache_reset();

        return response('OPCACHE_CLEARED');
    }
}
