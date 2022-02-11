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
        $query->setOrderings(['lastCrawled' => QueryInterface::ORDER_DESCENDING]);
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
        $this->buildAllUrlsCache();;
        $uncheckedUrls = \array_diff(
            \array_keys($this->allUrlsCache),
            \array_keys($this->checkedUrls)
        );
        foreach ($uncheckedUrls as $uncheckedUrl) {
            $sitemapUrlEntry = $this->findByUrl($uncheckedUrl);
            $this->remove($sitemapUrlEntry);
        }
        $this->persistenceManager->persistAll();
    }

    public function add($object)
    {
        parent::add($object);

        $this->allUrlsCache[$object->getUrl()] = true;
    }

    private function buildAllUrlsCache(): void
    {
        if (null === $this->allUrlsCache) {
            $this->allUrlsCache = [];

            $query = $this->createQuery();
            foreach ($query->execute(true) as $result) {
                $this->allUrlsCache[$result['url']] = true;
            }
        }
    }
}
