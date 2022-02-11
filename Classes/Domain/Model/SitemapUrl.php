<?php

declare(strict_types=1);
/*
 * Copyright (C) 2022 mein Bauernhof GbR.
 *
 * This file is subject to the terms and conditions defined in the
 * file 'LICENSE', which is part of this source code package.
 */

namespace Jbaron\Sitemapcrawler\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class SitemapUrl extends AbstractEntity
{
    private string $url;
    private ?int $lastCrawled;

    public function __construct(string $url, ?int $lastCrawled = null)
    {
        $this->url = $url;
        $this->lastCrawled = $lastCrawled;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    public function wasCrawled(): bool
    {
        return null !== $this->lastCrawled;
    }

    public function getLastCrawled(): int
    {
        return $this->lastCrawled;
    }

    public function setLastCrawled(int $lastCrawled): void
    {
        $this->lastCrawled = $lastCrawled;
    }
}
