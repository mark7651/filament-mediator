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

        $file = Mediator::query()->where('path', $path)->first();

        abort_unless($file !== null && $thumbnails->redraws($file), 404);

        return $thumbnails->server($file->disk)->getImageResponse($path, $request->query());
    }
}
