<?php

declare(strict_types=1);

namespace Jbaron\Sitemapcrawler\Tests\Functional\Domain\Repository;

use Jbaron\Sitemapcrawler\Domain\Model\SitemapUrl;
use Jbaron\Sitemapcrawler\Tests\Functional\AbstractFunctionalTestCase;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;

class SitemapUrlRepositoryTest extends AbstractFunctionalTestCase
{
    /**
     * @test
     */
    public function findLongestNonCrawledEntriesOrdersByLastCrawledAndRespectsLimit(): void
    {
        $this->insertUrl('https://example.com/c', 300);
        $this->insertUrl('https://example.com/a', 100);
        $this->insertUrl('https://example.com/d', 400);
        $this->insertUrl('https://example.com/b', 200);

        $urls = [];
        foreach ($this->getRepository()->findLongestNonCrawledEntries(3) as $entry) {
            $urls[] = $entry->getUrl();
        }

        self::assertSame(['https://example.com/a', 'https://example.com/b', 'https://example.com/c'], $urls);
    }

    /**
     * @test
     */
    public function findLongestNonCrawledEntriesMapsAllFields(): void
    {
        $this->insertUrl('https://example.com/a', 1600000000, 404);

        $entries = \iterator_to_array($this->getRepository()->findLongestNonCrawledEntries(1), false);

        self::assertCount(1, $entries);
        /** @var SitemapUrl $entry */
        $entry = $entries[0];
        self::assertSame('https://example.com/a', $entry->getUrl());
        self::assertSame(1600000000, $entry->getLastCrawled());
        self::assertTrue($entry->hasLastStatusCode());
        self::assertSame(404, $entry->getLastStatusCode());
        self::assertNull($entry->getLastRequestTime());
    }

    /**
     * @test
     */
    public function findLongestNonCrawledEntriesMapsNeverCrawledEntry(): void
    {
        $this->insertUrl('https://example.com/new');

        $entries = \iterator_to_array($this->getRepository()->findLongestNonCrawledEntries(1), false);

        self::assertCount(1, $entries);
        /** @var SitemapUrl $entry */
        $entry = $entries[0];
        self::assertFalse($entry->hasLastStatusCode());
        self::assertNull($entry->getLastRequestTime());
    }

    /**
     * @test
     */
    public function existsEntryForFindsStoredAndNewlyAddedUrls(): void
    {
        $this->insertUrl('https://example.com/stored');
        $repository = $this->getRepository();

        self::assertTrue($repository->existsEntryFor('https://example.com/stored'));
        self::assertFalse($repository->existsEntryFor('https://example.com/added'));

        $repository->add(new SitemapUrl('https://example.com/added', null, null, null));

        self::assertTrue($repository->existsEntryFor('https://example.com/added'));
    }

    /**
     * @test
     */
    public function deleteUncheckedUrlsRemovesOnlyUrlsNotCheckedSinceReset(): void
    {
        $this->insertUrl('https://example.com/checked');
        $this->insertUrl('https://example.com/checked-before-reset');
        $this->insertUrl('https://example.com/unchecked');
        $repository = $this->getRepository();

        $repository->resetCheckedUrls();
        $repository->existsEntryFor('https://example.com/checked-before-reset');
        $repository->resetCheckedUrls();
        $repository->existsEntryFor('https://example.com/checked');
        $repository->deleteUncheckedUrls();

        self::assertSame(['https://example.com/checked'], $this->getStoredUrls());
    }

    /**
     * @test
     */
    public function updatePersistsChangedFields(): void
    {
        $this->insertUrl('https://example.com/a');
        $repository = $this->getRepository();

        $entries = \iterator_to_array($repository->findLongestNonCrawledEntries(1), false);
        /** @var SitemapUrl $entry */
        $entry = $entries[0];
        $entry->setLastCrawled(1600000000);
        $entry->setLastStatusCode(SitemapUrl::STATUSCODE_FAILED);
        $entry->setLastRequestTime(1.5);
        $repository->update($entry);
        GeneralUtility::makeInstance(PersistenceManagerInterface::class)->persistAll();

        $row = $this->getRowsByUrl()['https://example.com/a'];
        self::assertEquals(1600000000, $row['last_crawled']);
        self::assertEquals(-1, $row['last_status_code']);
        self::assertEqualsWithDelta(1.5, (float)$row['last_request_time'], 0.0001);
    }
}
