<?php

declare(strict_types=1);
/*
 * Copyright (C) 2022 mein Bauernhof GbR.
 *
 * This file is subject to the terms and conditions defined in the
 * file 'LICENSE', which is part of this source code package.
 */

namespace Jbaron\Sitemapcrawler\Service;

use Jbaron\Sitemapcrawler\Domain\Model\SitemapUrl;
use Jbaron\Sitemapcrawler\Domain\Repository\SitemapUrlRepository;
use Psr\Http\Client\ClientInterface;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;

class SitemapCrawler implements SingletonInterface
{
    private ClientInterface $httpClient;
    private RequestFactory $requestFactory;
    private SitemapUrlRepository $sitemapUrlRepository;
    private SitemapParser $sitemapParser;
    private PersistenceManagerInterface $persistenceManager;

    public function __construct(
        ClientInterface $httpClient,
        RequestFactory $requestFactory,
        SitemapUrlRepository $sitemapUrlRepository,
        SitemapParser $sitemapParser,
        PersistenceManagerInterface $persistenceManager
    ) {
        $this->httpClient = $httpClient;
        $this->requestFactory = $requestFactory;
        $this->sitemapUrlRepository = $sitemapUrlRepository;
        $this->sitemapParser = $sitemapParser;
        $this->persistenceManager = $persistenceManager;
    }

    public function updateSitemapData(string $url): int
    {
        $numberUrls = 0;

        $numberAddedUrls = 0;
        $this->sitemapUrlRepository->resetCheckedUrls();
        foreach ($this->sitemapParser->getUrlsFromSitemapUrl($url) as $sitemapUrl) {
            $numberUrls++;
            if ($this->sitemapUrlRepository->existsEntryFor($sitemapUrl)) {
                continue;
            }

            $numberAddedUrls++;
            $this->sitemapUrlRepository->add(new SitemapUrl($sitemapUrl));
            $numberAddedUrls++;
            if (0 === $numberAddedUrls % 100) {
                $this->persistenceManager->persistAll();
            }
        }

        $this->persistenceManager->persistAll();
        $this->sitemapUrlRepository->deleteUncheckedUrls();
        $this->persistenceManager->persistAll();

        return $numberUrls;
    }

    public function crawlUrls(int $maxNumber): array
    {
        $responseCodes = [
            'failed' => 0,
        ];

        /** @var SitemapUrl $entry */
        foreach ($this->sitemapUrlRepository->findLongestNonCrawledEntries($maxNumber) as $entry) {
            try {
                $response = $this->httpClient->sendRequest(
                    $this->requestFactory->createRequest('GET', $entry->getUrl())
                );
                if (!\array_key_exists($response->getStatusCode(), $responseCodes)) {
                    $responseCodes[(string)$response->getStatusCode()] = 0;
                }
                $responseCodes[(string)$response->getStatusCode()]++;
            } catch (\Throwable $throwable) {
                $responseCodes['failed']++;
                continue;
            }

            $entry->setLastCrawled(\time());
            $this->sitemapUrlRepository->update($entry);

            \usleep(500000);
        }

        return $responseCodes;
    }
}
