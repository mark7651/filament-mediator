<?php

namespace Mediator\Http\Controllers;

use Illuminate\Http\Request;
use League\Glide\Signatures\SignatureException;
use League\Glide\Signatures\SignatureFactory;
use Mediator\Glide\Thumbnails;
use Mediator\Mediator;

class ThumbnailController
{
    public function __invoke(Request $request, Thumbnails $thumbnails, string $path): mixed
    {
        try {
            SignatureFactory::create($thumbnails->token())->validateRequest(
                path: $thumbnails->path().'/'.$path,
                params: $request->query(),
            );
        } catch (SignatureException) {
            abort(403);
        }

        // An address to a file the disk does not serve openly carries the hour
        // it stops being good, signed along with the measures. Nobody is signed
        // in here, because a picture is served without a session, so the hour is
        // what stands between a private file and an address that outlives it.
        $expires = $request->query('expires');

        abort_if($expires !== null && (int) $expires < now()->getTimestamp(), 403);

        // Asked of the whole table: the address was signed by this application
        // and the question is which file it points at, not which files the
        // person looking is shown.
        $file = Mediator::unscoped()->where('path', $path)->first();

        abort_unless($file !== null && $thumbnails->redraws($file), 404);

        return $thumbnails->server($file->disk)->getImageResponse($path, $request->query());
    }
}
