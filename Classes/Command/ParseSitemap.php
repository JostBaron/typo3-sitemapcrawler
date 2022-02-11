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

class ParseSitemap extends Command
{
    private const ARGUMENT_URL = 'url';

    private SitemapCrawler $sitemapCrawler;

    public function __construct(string $name, SitemapCrawler $sitemapCrawler)
    {
        parent::__construct($name);
        $this->sitemapCrawler = $sitemapCrawler;
    }

    protected function configure(): void
    {
        $this->setDescription('Parses the sitemap at the given URL and adds the URLs from it for crawling.');
        $this->addArgument(
            self::ARGUMENT_URL,
            InputArgument::REQUIRED,
            'The URL where the sitemap or sitemap index is located.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $url = $input->getArgument(self::ARGUMENT_URL);
        $numberUrls = $this->sitemapCrawler->updateSitemapData($url);

        $output->writeln(\sprintf('<info>Found %d URLs in sitemap</info>', $numberUrls));

        return 0;
    }
}
