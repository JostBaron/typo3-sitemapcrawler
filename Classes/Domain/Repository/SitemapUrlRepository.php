<?php

declare(strict_types=1);
/*
 * Copyright (C) 2022 mein Bauernhof GbR.
 *
 * This file is subject to the terms and conditions defined in the
 * file 'LICENSE', which is part of this source code package.
 */

namespace Jbaron\Sitemapcrawler\Domain\Repository;

use Jbaron\Sitemapcrawler\Domain\Model\SitemapUrl;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

class SitemapUrlRepository extends Repository
{
    private ?array $allUrlsCache = null;
    private array $checkedUrls;

    /**
     * Returns the longest non-crawled sitemap entries.
     *
     * @param int $number
     *
     * @return \Iterator<SitemapUrl>
     */
    public function findLongestNonCrawledEntries(int $number): \Iterator
    {
        $query = $this->createQuery();
        $query->setLimit($number);
        $query->setOrderings(['lastCrawled' => QueryInterface::ORDER_ASCENDING]);
        return $query->execute();
    }

    public function existsEntryFor(string $url): bool
    {
        $this->buildAllUrlsCache();

        $this->checkedUrls[$url] = true;

        return \array_key_exists($url, $this->allUrlsCache);
    }

    public function resetCheckedUrls(): void
    {
        $this->checkedUrls = [];
    }

    public function deleteUncheckedUrls(): void
    {
        $this->persistenceManager->persistAll();

        $this->buildAllUrlsCache();;
        $uncheckedUrls = \array_diff(
            \array_keys($this->allUrlsCache),
            \array_keys($this->checkedUrls)
        );
        foreach ($uncheckedUrls as $uncheckedUrl) {
            // Not the magic findOneByUrl(): TYPO3 12 deprecates it, and its replacement
            // findOneBy() does not exist in TYPO3 11.
            $query = $this->createQuery();
            $sitemapUrlEntry = $query->matching($query->equals('url', $uncheckedUrl))->execute()->getFirst();
            if (null !== $sitemapUrlEntry) {
                $this->remove($sitemapUrlEntry);
            }
        }
        $this->persistenceManager->persistAll();
        $this->resetCheckedUrls();
    }

    public function add($object)
    {
        parent::add($object);

        $this->allUrlsCache[$object->getUrl()] = true;
    }

    /**
     * Loads all stored URLs once. Afterwards, add() keeps the cache current, so
     * callers can batch their persistAll() calls.
     */
    private function buildAllUrlsCache(): void
    {
        if (null === $this->allUrlsCache) {
            // The query only sees objects that have been persisted.
            $this->persistenceManager->persistAll();
            $this->allUrlsCache = [];

            $query = $this->createQuery();
            foreach ($query->execute(true) as $result) {
                $this->allUrlsCache[$result['url']] = true;
            }
        }
    }
}
