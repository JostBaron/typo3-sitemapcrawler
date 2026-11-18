<?php

declare(strict_types=1);

namespace Jbaron\Sitemapcrawler\Tests\Fixtures;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\Stream;

/**
 * PSR-18 client that answers from a URL => response map instead of the network,
 * and records every request it receives.
 */
final class FakeHttpClient implements ClientInterface
{
    /**
     * @var array<string, ResponseInterface|\Throwable>
     */
    private array $responses = [];

    /**
     * @var string[]
     */
    private array $requestedUrls = [];

    public function respondWith(string $url, string $body, int $statusCode = 200): self
    {
        $stream = new Stream('php://temp', 'rw');
        $stream->write($body);
        $stream->rewind();
        $this->responses[$url] = new Response($stream, $statusCode);
        return $this;
    }

    public function failWith(string $url, \Throwable $throwable): self
    {
        $this->responses[$url] = $throwable;
        return $this;
    }

    /**
     * Unknown URLs get a 404, like a real server would answer.
     */
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $url = (string)$request->getUri();
        $this->requestedUrls[] = $url;

        $response = $this->responses[$url] ?? new Response('php://temp', 404);
        if ($response instanceof \Throwable) {
            throw $response;
        }
        return $response;
    }

    /**
     * @return string[] Requested URLs, in request order.
     */
    public function getRequestedUrls(): array
    {
        return $this->requestedUrls;
    }

    /**
     * Builds a <urlset> sitemap listing the given URLs.
     *
     * @param string[] $urls
     */
    public static function sitemap(array $urls): string
    {
        $entries = '';
        foreach ($urls as $url) {
            $entries .= '<url><loc>' . \htmlspecialchars($url) . '</loc></url>';
        }
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . $entries . '</urlset>';
    }

    /**
     * Builds a <sitemapindex> pointing at the given sitemap URLs.
     *
     * @param string[] $sitemapUrls
     */
    public static function sitemapIndex(array $sitemapUrls): string
    {
        $entries = '';
        foreach ($sitemapUrls as $url) {
            $entries .= '<sitemap><loc>' . \htmlspecialchars($url) . '</loc></sitemap>';
        }
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . $entries . '</sitemapindex>';
    }
}
