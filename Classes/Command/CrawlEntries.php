<?php

declare(strict_types=1);
/*
 * Copyright (C) 2022 mein Bauernhof GbR.
 *
 * This file is subject to the terms and conditions defined in the
 * file 'LICENSE', which is part of this source code package.
 */

namespace Jbaron\Sitemapcrawler\Command;

use Jbaron\Sitemapcrawler\Service\SitemapCrawler;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CrawlEntries extends Command
{
    private const ARGUMENT_NUMBER = 'number';

    private SitemapCrawler $sitemapCrawler;

    public function __construct(string $name, SitemapCrawler $sitemapCrawler)
    {
        parent::__construct($name);

        $this->sitemapCrawler = $sitemapCrawler;
    }

    protected function configure()
    {
        $this->setDescription(
            'Crawls sitemap entries. Crawls entries that were not crawled for the longest time first.'
        );
        $this->addArgument(self::ARGUMENT_NUMBER, InputArgument::REQUIRED, 'Number of entries to crawl');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $numberToCrawl = (int)$input->getArgument(self::ARGUMENT_NUMBER);
        $numberToCrawl = \max(1, \min($numberToCrawl, 100000));

        $responseCodes = $this->sitemapCrawler->crawlUrls($numberToCrawl);

        $output->writeln('<info>Finished crawling.</info>');
        foreach ($responseCodes as $statusCode => $numberCrawled) {
            $output->writeln(
                \sprintf(' - Got %d entries with status code %s', $numberCrawled, $statusCode)
            );
        }

        return 0;
    }
}
