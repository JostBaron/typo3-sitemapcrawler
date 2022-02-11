<?php

declare(strict_types=1);
/*
 * Copyright (C) 2022 mein Bauernhof GbR.
 *
 * This file is subject to the terms and conditions defined in the
 * file 'LICENSE', which is part of this source code package.
 */

namespace Jbaron\Sitemapcrawler\Service;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use TYPO3\CMS\Core\SingletonInterface;

class SitemapParser implements SingletonInterface
{
    private const TYPE_UNKNOWN = 'unknown';
    private const TYPE_INDEX = 'sitemap-index';
    private const TYPE_SITEMAP = 'sitemap';

    private const NS_PREFIX = 'si';
    const NS_SITEMAP = 'http://www.sitemaps.org/schemas/sitemap/0.9';

    private ClientInterface $httpClient;
    private RequestFactoryInterface $requestFactory;

    public function __construct(
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory
    ) {
        $this->httpClient = $httpClient;
        $this->requestFactory = $requestFactory;
    }

    public function getUrlsFromSitemapUrl(string $sitemapUrl, bool $isRecursion = false): \Iterator
    {
        $domDocument = $this->downloadAndParseUrl($sitemapUrl);
        if (null === $domDocument) {
            return [];
        }

        $type = $this->getType($domDocument);
        switch ($type) {
            case self::TYPE_SITEMAP:
                foreach ($this->getUrlsFromParsedSitemap($domDocument) as $sitemapUrl) {
                    yield $sitemapUrl;
                }
                break;
            case self::TYPE_INDEX:
                if ($isRecursion) {
                    throw new \InvalidArgumentException(
                        'Sitemap index contained links to sitemap index.',
                        1505041386
                    );
                }

                $sitemapIndexUrls = $this->getSitemapUrlsFromSitemapIndex($domDocument);
                foreach ($sitemapIndexUrls as $sitemapIndexUrl) {
                    foreach ($this->getUrlsFromSitemapUrl($sitemapIndexUrl, true) as $sitemapUrl) {
                        yield $sitemapUrl;
                    }
                }
                break;
            case self::TYPE_UNKNOWN:
            default:
                throw new \InvalidArgumentException(
                    'Unknown sitemap type.',
                    1505041456
                );
        }
    }

    private function downloadAndParseUrl(string $url): ?\DOMDocument
    {
        $content = $this->downloadUrl($url);
        if (null === $content) {
            return null;
        }

        $domDocument = new \DOMDocument();
        if (!$domDocument->loadXML($content)) {
            return null;
        }

        return $domDocument;
    }

    private function getType(\DOMDocument $domDocument): string
    {
        $urlXPath = $this->getXPath($domDocument);

        $rootNodes = $urlXPath->query('/*');
        if (0 === $rootNodes->length) {
            return self::TYPE_UNKNOWN;
        }

        switch ($rootNodes->item(0)->localName) {
            case 'sitemapindex':
                return self::TYPE_INDEX;
            case 'urlset':
                return self::TYPE_SITEMAP;
            default:
                return self::TYPE_UNKNOWN;
        }
    }

    private function getSitemapUrlsFromSitemapIndex(\DOMDocument $domDocument): \Iterator
    {
        $urlXPath = $this->getXPath($domDocument);
        $urlNodes = $urlXPath->query(
            \sprintf(
                '/%1$s:sitemapindex/%1$s:sitemap/%1$s:loc/text()',
                self::NS_PREFIX
            )
        );

        foreach ($urlNodes as $urlNode) {
            if (\XML_TEXT_NODE !== $urlNode->nodeType) {
                continue;
            }
            yield $urlNode->nodeValue;
        }
    }

    private function getUrlsFromParsedSitemap(\DOMDocument $domDocument)
    {
        $urlXPath = $this->getXPath($domDocument);
        $urlNodes = $urlXPath->query(sprintf('/%1$s:urlset/%1$s:url/%1$s:loc/text()', self::NS_PREFIX));

        $urls = [];
        foreach ($urlNodes as $urlNode) {
            if (XML_TEXT_NODE !== $urlNode->nodeType) {
                continue;
            }
            $urls[] = $urlNode->nodeValue;
        }

        return $urls;
    }

    private function downloadUrl(string $url): ?string
    {
        $response = $this->httpClient->sendRequest(
            $this->requestFactory->createRequest('GET', $url)
        );
        if (200 > $response->getStatusCode() || 300 <= $response->getStatusCode()) {
            return null;
        }
        return (string)$response->getBody();
    }

    private function getXPath(\DOMDocument $domDocument): \DOMXPath
    {
        $xPath = new \DOMXPath($domDocument);
        $xPath->registerNamespace(self::NS_PREFIX, self::NS_SITEMAP);
        return $xPath;
    }
}
