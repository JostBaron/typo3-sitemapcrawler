<?php

declare(strict_types=1);

namespace Jbaron\Sitemapcrawler\Tests\Unit\Service;

use Jbaron\Sitemapcrawler\Service\SitemapParser;
use Jbaron\Sitemapcrawler\Tests\Fixtures\FakeHttpClient;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class SitemapParserTest extends UnitTestCase
{
    private const SITEMAP_URL = 'https://example.com/sitemap.xml';

    private FakeHttpClient $httpClient;
    private SitemapParser $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->httpClient = new FakeHttpClient();
        $this->subject = new SitemapParser($this->httpClient, new RequestFactory());
    }

    /**
     * @return string[]
     */
    private function parse(string $url = self::SITEMAP_URL): array
    {
        return \iterator_to_array($this->subject->getUrlsFromSitemapUrl($url), false);
    }

    /**
     * @test
     */
    public function returnsUrlsOfSitemapInDocumentOrder(): void
    {
        $urls = ['https://example.com/', 'https://example.com/b', 'https://example.com/a?x=1&y=2'];
        $this->httpClient->respondWith(self::SITEMAP_URL, FakeHttpClient::sitemap($urls));

        self::assertSame($urls, $this->parse());
    }

    /**
     * @test
     */
    public function returnsNothingForEmptySitemap(): void
    {
        $this->httpClient->respondWith(self::SITEMAP_URL, FakeHttpClient::sitemap([]));

        self::assertSame([], $this->parse());
    }

    /**
     * @test
     */
    public function ignoresOtherSitemapElementsAndExtensions(): void
    {
        $this->httpClient->respondWith(
            self::SITEMAP_URL,
            '<?xml version="1.0" encoding="UTF-8"?>'
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'
            . ' xmlns:xhtml="http://www.w3.org/1999/xhtml">'
            . '<url><loc>https://example.com/de</loc><lastmod>2022-01-01</lastmod>'
            . '<changefreq>daily</changefreq><priority>0.5</priority>'
            . '<xhtml:link rel="alternate" hreflang="en" href="https://example.com/en"/></url>'
            . '</urlset>'
        );

        self::assertSame(['https://example.com/de'], $this->parse());
    }

    /**
     * @test
     */
    public function followsSitemapIndexToAllItsSitemaps(): void
    {
        $this->httpClient
            ->respondWith(self::SITEMAP_URL, FakeHttpClient::sitemapIndex([
                'https://example.com/sitemap-1.xml',
                'https://example.com/sitemap-2.xml',
            ]))
            ->respondWith('https://example.com/sitemap-1.xml', FakeHttpClient::sitemap(['https://example.com/1']))
            ->respondWith('https://example.com/sitemap-2.xml', FakeHttpClient::sitemap([
                'https://example.com/2a',
                'https://example.com/2b',
            ]));

        self::assertSame(['https://example.com/1', 'https://example.com/2a', 'https://example.com/2b'], $this->parse());
    }

    /**
     * @test
     */
    public function throwsForSitemapOfIndexThatCannotBeDownloaded(): void
    {
        $this->httpClient
            ->respondWith(self::SITEMAP_URL, FakeHttpClient::sitemapIndex([
                'https://example.com/sitemap-1.xml',
                'https://example.com/missing.xml',
            ]))
            ->respondWith('https://example.com/sitemap-1.xml', FakeHttpClient::sitemap(['https://example.com/1']));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(1795478401);

        $this->parse();
    }

    /**
     * @test
     */
    public function throwsForSitemapIndexNestedInSitemapIndex(): void
    {
        $this->httpClient
            ->respondWith(self::SITEMAP_URL, FakeHttpClient::sitemapIndex(['https://example.com/nested.xml']))
            ->respondWith('https://example.com/nested.xml', FakeHttpClient::sitemapIndex(['https://example.com/s.xml']));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1505041386);

        $this->parse();
    }

    /**
     * @test
     */
    public function throwsForUnknownRootElement(): void
    {
        $this->httpClient->respondWith(self::SITEMAP_URL, '<?xml version="1.0"?><html><body/></html>');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1505041456);

        $this->parse();
    }

    /**
     * @test
     * @dataProvider nonSuccessStatusCodes
     */
    public function throwsForNonSuccessfulResponse(int $statusCode): void
    {
        $this->httpClient->respondWith(
            self::SITEMAP_URL,
            FakeHttpClient::sitemap(['https://example.com/']),
            $statusCode
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(1795478401);
        $this->expectExceptionMessage('HTTP status ' . $statusCode);

        $this->parse();
    }

    /**
     * @return array<string, array{int}>
     */
    public function nonSuccessStatusCodes(): array
    {
        return [
            'redirect' => [301],
            'not found' => [404],
            'server error' => [500],
        ];
    }

    /**
     * @test
     */
    public function throwsForInvalidXmlWithoutEmittingWarnings(): void
    {
        $this->httpClient->respondWith(self::SITEMAP_URL, 'this is not XML');

        // PHPUnit turns warnings into exceptions of other types, so reaching the
        // expected exception also proves that no warning was emitted.
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(1795478402);
        $this->expectExceptionMessage('Start tag expected');

        $this->parse();
    }

    /**
     * @test
     */
    public function leavesLibxmlErrorHandlingAsItWas(): void
    {
        $this->httpClient->respondWith(self::SITEMAP_URL, 'this is not XML');
        $previous = \libxml_use_internal_errors(false);

        try {
            $this->parse();
        } catch (\RuntimeException $exception) {
        }

        self::assertFalse(\libxml_use_internal_errors($previous));
    }

    /**
     * @test
     */
    public function passesOnExceptionsOfHttpClient(): void
    {
        $exception = new \RuntimeException('Connection refused', 1700000000);
        $this->httpClient->failWith(self::SITEMAP_URL, $exception);

        $this->expectExceptionObject($exception);

        $this->parse();
    }

    /**
     * @test
     */
    public function downloadsLazilyWhenIterated(): void
    {
        $this->httpClient->respondWith(self::SITEMAP_URL, FakeHttpClient::sitemap(['https://example.com/']));

        $urls = $this->subject->getUrlsFromSitemapUrl(self::SITEMAP_URL);
        self::assertSame([], $this->httpClient->getRequestedUrls());

        \iterator_to_array($urls);
        self::assertSame([self::SITEMAP_URL], $this->httpClient->getRequestedUrls());
    }
}
