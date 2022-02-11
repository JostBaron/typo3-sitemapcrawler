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
    public const STATUSCODE_FAILED = -1;

    protected ?string $url = null;
    protected ?int $lastCrawled;
    protected ?int $lastStatusCode;
    protected ?float $lastRequestTime;

    public function __construct(
        string $url,
        ?int $lastCrawled,
        ?int $lastStatusCode,
        ?float $lastRequestTime
    ) {
        $this->url = $url;
        $this->lastCrawled = $lastCrawled;
        $this->lastStatusCode = $lastStatusCode;
        $this->lastRequestTime = $lastRequestTime;
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

    public function hasLastStatusCode(): bool
    {
        return null !== $this->lastStatusCode;
    }

    public function getLastStatusCode(): int
    {
        return $this->lastStatusCode;
    }

    public function setLastStatusCode(?int $lastStatusCode): void
    {
        $this->lastStatusCode = $lastStatusCode;
    }

    public function getLastRequestTime(): ?float
    {
        return $this->lastRequestTime;
    }

    public function setLastRequestTime(?float $lastRequestTime): void
    {
        $this->lastRequestTime = $lastRequestTime;
    }
}
