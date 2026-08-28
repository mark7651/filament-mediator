<?php

namespace Mediator\Glide;

use League\Flysystem\FilesystemOperator;
use League\Glide\Responses\ResponseFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The drawn picture handed to the browser.
 *
 * Glide ships a factory for PSR-7 responses only, and this application speaks
 * Symfony, so the response is built here. A drawn picture never changes under
 * its address, because a different size is a different address, so it is given
 * a year to live in every cache between here and the reader.
 */
class StreamedResponseFactory implements ResponseFactoryInterface
{
    public function __construct(private readonly ?Request $request = null) {}

    public function create(FilesystemOperator $cache, string $path): StreamedResponse
    {
        $stream = $cache->readStream($path);

        $response = new StreamedResponse;
        $response->headers->set('Content-Type', (string) $cache->mimeType($path));
        $response->headers->set('Content-Length', (string) $cache->fileSize($path));
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->setPublic();
        $response->setMaxAge(31536000);

        if ($this->request instanceof Request) {
            $response->setLastModified(date_create_immutable()->setTimestamp($cache->lastModified($path)));
            $response->isNotModified($this->request);
        }

        $response->setCallback(function () use ($stream): void {
            if (ftell($stream) !== 0) {
                rewind($stream);
            }

            fpassthru($stream);
            fclose($stream);
        });

        return $response;
    }
}
