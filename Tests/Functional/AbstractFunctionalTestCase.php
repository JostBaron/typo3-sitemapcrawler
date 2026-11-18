<?php

declare(strict_types=1);

namespace Jbaron\Sitemapcrawler\Tests\Functional;

use Jbaron\Sitemapcrawler\Domain\Repository\SitemapUrlRepository;
use Jbaron\Sitemapcrawler\Service\SitemapCrawler;
use Jbaron\Sitemapcrawler\Service\SitemapParser;
use Jbaron\Sitemapcrawler\Tests\Fixtures\FakeHttpClient;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Loads the extension and provides a {@see SitemapCrawler} wired to a
 * {@see FakeHttpClient}, so no test touches the network.
 */
abstract class AbstractFunctionalTestCase extends FunctionalTestCase
{
    protected const TABLE = 'tx_jbaron_sitemapcrawler_domain_model_sitemapurl';
    protected const SITEMAP_URL = 'https://example.com/sitemap.xml';

    /**
     * @var string[]
     */
    protected array $testExtensionsToLoad = [
        'typo3conf/ext/sitemapcrawler',
    ];

    protected FakeHttpClient $httpClient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->httpClient = new FakeHttpClient();
    }

    protected function createCrawler(): SitemapCrawler
    {
        $requestFactory = new RequestFactory();
        return new SitemapCrawler(
            $this->httpClient,
            $requestFactory,
            $this->getRepository(),
            new SitemapParser($this->httpClient, $requestFactory),
            GeneralUtility::makeInstance(PersistenceManagerInterface::class)
        );
    }

    protected function getRepository(): SitemapUrlRepository
    {
        return GeneralUtility::makeInstance(SitemapUrlRepository::class);
    }

    /**
     * Writes a URL record directly, bypassing Extbase, so tests of one method
     * don't depend on other methods working.
     */
    protected function insertUrl(string $url, int $lastCrawled = 0, ?int $lastStatusCode = null): void
    {
        $this->getConnection()->insert(self::TABLE, [
            'pid' => 0,
            'url' => $url,
            'last_crawled' => $lastCrawled,
            'last_status_code' => $lastStatusCode,
        ]);
    }

    /**
     * @return array<string, array<string, mixed>> All stored rows, keyed by URL, sorted by URL.
     */
    protected function getRowsByUrl(): array
    {
        $rows = $this->getConnection()->select(['*'], self::TABLE)->fetchAllAssociative();
        $rowsByUrl = [];
        foreach ($rows as $row) {
            self::assertArrayNotHasKey($row['url'], $rowsByUrl, 'URL is stored twice: ' . $row['url']);
            $rowsByUrl[$row['url']] = $row;
        }
        \ksort($rowsByUrl, SORT_STRING);
        return $rowsByUrl;
    }

    /**
     * @return string[] All stored URLs, sorted.
     */
    protected function getStoredUrls(): array
    {
        return \array_keys($this->getRowsByUrl());
    }

    protected function getConnection(): Connection
    {
        return $this->getConnectionPool()->getConnectionForTable(self::TABLE);
    }
}
