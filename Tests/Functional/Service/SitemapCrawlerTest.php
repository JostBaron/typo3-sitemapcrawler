<?php

declare(strict_types=1);

namespace Jbaron\Sitemapcrawler\Tests\Functional\Service;

use Jbaron\Sitemapcrawler\Domain\Model\SitemapUrl;
use Jbaron\Sitemapcrawler\Tests\Fixtures\FakeHttpClient;
use Jbaron\Sitemapcrawler\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

class SitemapCrawlerTest extends AbstractFunctionalTestCase
{
    #[Test]
    public function updateSitemapDataStoresAllUrlsAsNotYetCrawled(): void
    {
        $this->httpClient->respondWith(self::SITEMAP_URL, FakeHttpClient::sitemap([
            'https://example.com/a',
            'https://example.com/b',
        ]));

        $numberOfUrls = $this->createCrawler()->updateSitemapData(self::SITEMAP_URL);

        self::assertSame(2, $numberOfUrls);
        $rows = $this->getRowsByUrl();
        self::assertSame(['https://example.com/a', 'https://example.com/b'], \array_keys($rows));
        foreach ($rows as $row) {
            self::assertEquals(0, $row['last_crawled']);
            self::assertNull($row['last_status_code']);
            self::assertNull($row['last_request_time']);
        }
    }

    #[Test]
    public function updateSitemapDataStoresUrlsOfAllSitemapsOfIndex(): void
    {
        $this->httpClient
            ->respondWith(self::SITEMAP_URL, FakeHttpClient::sitemapIndex([
                'https://example.com/sitemap-1.xml',
                'https://example.com/sitemap-2.xml',
            ]))
            ->respondWith('https://example.com/sitemap-1.xml', FakeHttpClient::sitemap(['https://example.com/1']))
            ->respondWith('https://example.com/sitemap-2.xml', FakeHttpClient::sitemap(['https://example.com/2']));

        self::assertSame(2, $this->createCrawler()->updateSitemapData(self::SITEMAP_URL));
        self::assertSame(['https://example.com/1', 'https://example.com/2'], $this->getStoredUrls());
    }

    #[Test]
    public function updateSitemapDataStoresUrlListedTwiceOnlyOnce(): void
    {
        $this->httpClient->respondWith(self::SITEMAP_URL, FakeHttpClient::sitemap([
            'https://example.com/a',
            'https://example.com/a',
        ]));

        self::assertSame(2, $this->createCrawler()->updateSitemapData(self::SITEMAP_URL));
        self::assertSame(['https://example.com/a'], $this->getStoredUrls());
    }

    #[Test]
    public function updateSitemapDataKeepsCrawlDataOfKnownUrls(): void
    {
        $this->insertUrl('https://example.com/known', 1600000000, 200);
        $this->httpClient->respondWith(self::SITEMAP_URL, FakeHttpClient::sitemap([
            'https://example.com/known',
            'https://example.com/new',
        ]));

        self::assertSame(2, $this->createCrawler()->updateSitemapData(self::SITEMAP_URL));

        $rows = $this->getRowsByUrl();
        self::assertSame(['https://example.com/known', 'https://example.com/new'], \array_keys($rows));
        self::assertEquals(1600000000, $rows['https://example.com/known']['last_crawled']);
        self::assertEquals(200, $rows['https://example.com/known']['last_status_code']);
    }

    #[Test]
    public function updateSitemapDataRemovesUrlsNoLongerInSitemap(): void
    {
        $this->insertUrl('https://example.com/kept');
        $this->insertUrl('https://example.com/removed', 1600000000, 200);
        $this->httpClient->respondWith(self::SITEMAP_URL, FakeHttpClient::sitemap(['https://example.com/kept']));

        $this->createCrawler()->updateSitemapData(self::SITEMAP_URL);

        self::assertSame(['https://example.com/kept'], $this->getStoredUrls());
    }

    #[Test]
    public function updateSitemapDataStoresManyUrls(): void
    {
        $urls = [];
        for ($i = 0; $i < 250; $i++) {
            $urls[] = 'https://example.com/page-' . $i;
        }
        $this->httpClient->respondWith(self::SITEMAP_URL, FakeHttpClient::sitemap($urls));

        self::assertSame(250, $this->createCrawler()->updateSitemapData(self::SITEMAP_URL));
        self::assertCount(250, $this->getStoredUrls());
    }

    /**
     * The parser fetches the sitemaps of an index lazily, so the second request
     * shows what was persisted after the 150 URLs of the first sitemap.
     */
    #[Test]
    public function updateSitemapDataPersistsNewUrlsInBatchesOfHundred(): void
    {
        $firstSitemapUrls = [];
        for ($i = 0; $i < 150; $i++) {
            $firstSitemapUrls[] = 'https://example.com/page-' . $i;
        }
        $storedWhenSecondSitemapWasRequested = null;
        $this->httpClient
            ->respondWith(self::SITEMAP_URL, FakeHttpClient::sitemapIndex([
                'https://example.com/sitemap-1.xml',
                'https://example.com/sitemap-2.xml',
            ]))
            ->respondWith('https://example.com/sitemap-1.xml', FakeHttpClient::sitemap($firstSitemapUrls))
            ->respondWith('https://example.com/sitemap-2.xml', FakeHttpClient::sitemap(['https://example.com/last']))
            ->onRequest('https://example.com/sitemap-2.xml', function () use (&$storedWhenSecondSitemapWasRequested): void {
                $storedWhenSecondSitemapWasRequested = \count($this->getStoredUrls());
            });

        self::assertSame(151, $this->createCrawler()->updateSitemapData(self::SITEMAP_URL));

        self::assertSame(100, $storedWhenSecondSitemapWasRequested);
        self::assertCount(151, $this->getStoredUrls());
    }

    /**
     * A sitemap that is temporarily unavailable must not wipe all URLs and their crawl history.
     */
    #[Test]
    public function updateSitemapDataKeepsUrlsWhenSitemapCannotBeDownloaded(): void
    {
        $this->insertUrl('https://example.com/a', 1600000000, 200);
        $this->httpClient->respondWith(self::SITEMAP_URL, 'Service Unavailable', 503);

        $this->assertUpdateFailsWithCode(1795478401);

        self::assertSame(['https://example.com/a'], $this->getStoredUrls());
        self::assertEquals(1600000000, $this->getRowsByUrl()['https://example.com/a']['last_crawled']);
    }

    #[Test]
    public function updateSitemapDataKeepsUrlsWhenSitemapIsInvalidXml(): void
    {
        $this->insertUrl('https://example.com/a');
        $this->httpClient->respondWith(self::SITEMAP_URL, '<urlset><url><loc>truncated');

        $this->assertUpdateFailsWithCode(1795478402);

        self::assertSame(['https://example.com/a'], $this->getStoredUrls());
    }

    /**
     * One unavailable sitemap of an index must not remove the URLs it lists.
     */
    #[Test]
    public function updateSitemapDataKeepsUrlsWhenSitemapOfIndexCannotBeDownloaded(): void
    {
        $this->insertUrl('https://example.com/1');
        $this->insertUrl('https://example.com/2');
        $this->httpClient
            ->respondWith(self::SITEMAP_URL, FakeHttpClient::sitemapIndex([
                'https://example.com/sitemap-1.xml',
                'https://example.com/sitemap-2.xml',
            ]))
            ->respondWith('https://example.com/sitemap-1.xml', FakeHttpClient::sitemap(['https://example.com/1']))
            ->respondWith('https://example.com/sitemap-2.xml', 'Internal Server Error', 500);

        $this->assertUpdateFailsWithCode(1795478401);

        self::assertSame(['https://example.com/1', 'https://example.com/2'], $this->getStoredUrls());
    }

    private function assertUpdateFailsWithCode(int $code): void
    {
        try {
            $this->createCrawler()->updateSitemapData(self::SITEMAP_URL);
        } catch (\Exception $exception) {
            self::assertSame($code, $exception->getCode(), $exception->getMessage());
            return;
        }
        self::fail('Expected updateSitemapData() to fail.');
    }

    #[Test]
    public function updateSitemapDataKeepsUrlsWhenParsingFails(): void
    {
        $this->insertUrl('https://example.com/a');
        $this->httpClient->respondWith(self::SITEMAP_URL, '<?xml version="1.0"?><html/>');

        $this->assertUpdateFailsWithCode(1505041456);

        self::assertSame(['https://example.com/a'], $this->getStoredUrls());
    }

    #[Test]
    public function crawlUrlsRequestsLeastRecentlyCrawledUrlsFirst(): void
    {
        $this->insertUrl('https://example.com/recent', 1600000300);
        $this->insertUrl('https://example.com/never');
        $this->insertUrl('https://example.com/old', 1600000100);
        $this->insertUrl('https://example.com/middle', 1600000200);

        $this->createCrawler()->crawlUrls(3);

        self::assertSame(
            ['https://example.com/never', 'https://example.com/old', 'https://example.com/middle'],
            $this->httpClient->getRequestedUrls()
        );
    }

    #[Test]
    public function crawlUrlsStoresResultOfEachRequest(): void
    {
        $this->insertUrl('https://example.com/ok');
        $this->insertUrl('https://example.com/missing', 1600000000, 200);
        $this->httpClient->respondWith('https://example.com/ok', 'content');

        $before = \time();
        $this->createCrawler()->crawlUrls(2);
        $after = \time();

        $rows = $this->getRowsByUrl();
        self::assertEquals(200, $rows['https://example.com/ok']['last_status_code']);
        self::assertEquals(404, $rows['https://example.com/missing']['last_status_code']);
        foreach ($rows as $url => $row) {
            self::assertGreaterThanOrEqual($before, (int)$row['last_crawled'], $url);
            self::assertLessThanOrEqual($after, (int)$row['last_crawled'], $url);
            self::assertNotNull($row['last_request_time'], $url);
            self::assertGreaterThanOrEqual(0.0, (float)$row['last_request_time'], $url);
        }
    }

    #[Test]
    public function crawlUrlsRecordsFailedRequestsAndContinues(): void
    {
        $this->insertUrl('https://example.com/failing');
        $this->insertUrl('https://example.com/ok', 1600000000);
        $this->httpClient
            ->failWith('https://example.com/failing', new \RuntimeException('Connection refused'))
            ->respondWith('https://example.com/ok', 'content');

        $responseCodes = $this->createCrawler()->crawlUrls(2);

        self::assertEquals(['failed' => 1, 200 => 1], $responseCodes);
        $rows = $this->getRowsByUrl();
        self::assertEquals(SitemapUrl::STATUSCODE_FAILED, $rows['https://example.com/failing']['last_status_code']);
        self::assertGreaterThan(0, (int)$rows['https://example.com/failing']['last_crawled']);
        self::assertEquals(200, $rows['https://example.com/ok']['last_status_code']);
    }

    #[Test]
    public function crawlUrlsCountsResponsesPerStatusCode(): void
    {
        $this->insertUrl('https://example.com/a', 1);
        $this->insertUrl('https://example.com/b', 2);
        $this->insertUrl('https://example.com/c', 3);
        $this->httpClient
            ->respondWith('https://example.com/a', 'content')
            ->respondWith('https://example.com/b', 'content')
            ->respondWith('https://example.com/c', 'error', 500);

        $responseCodes = $this->createCrawler()->crawlUrls(3);

        self::assertEquals(['failed' => 0, 200 => 2, 500 => 1], $responseCodes);
    }

    #[Test]
    public function crawlUrlsDoesNothingWithoutUrls(): void
    {
        self::assertSame(['failed' => 0], $this->createCrawler()->crawlUrls(10));
        self::assertSame([], $this->httpClient->getRequestedUrls());
    }

    /**
     * After a full round, the next run starts again with the URL crawled first.
     */
    #[Test]
    public function crawlUrlsCyclesThroughAllUrls(): void
    {
        $this->insertUrl('https://example.com/a', 1);
        $this->insertUrl('https://example.com/b', 2);

        $this->createCrawler()->crawlUrls(1);
        // Both runs can finish within the same second, so give "a" a distinctly older timestamp.
        $this->getConnection()->update(self::TABLE, ['last_crawled' => 3], ['url' => 'https://example.com/a']);
        $this->createCrawler()->crawlUrls(1);

        self::assertSame(['https://example.com/a', 'https://example.com/b'], $this->httpClient->getRequestedUrls());
    }
}
