<?php

declare(strict_types=1);

namespace Jbaron\Sitemapcrawler\Tests\Functional\Command;

use Jbaron\Sitemapcrawler\Command\CrawlEntries;
use Jbaron\Sitemapcrawler\Command\ParseSitemap;
use Jbaron\Sitemapcrawler\Tests\Fixtures\FakeHttpClient;
use Jbaron\Sitemapcrawler\Tests\Functional\AbstractFunctionalTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Console\CommandRegistry;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class CommandsTest extends AbstractFunctionalTestCase
{
    /**
     * @test
     * @dataProvider registeredCommands
     */
    public function commandIsRegisteredUnderItsName(string $name, string $className): void
    {
        $command = GeneralUtility::makeInstance(CommandRegistry::class)->getCommandByIdentifier($name);

        self::assertInstanceOf($className, $command);
        self::assertSame($name, $command->getName());
    }

    /**
     * @return array<string, array{string, class-string}>
     */
    public function registeredCommands(): array
    {
        return [
            'parse' => ['sitemapcrawler:parse', ParseSitemap::class],
            'crawl' => ['sitemapcrawler:crawl', CrawlEntries::class],
        ];
    }

    /**
     * @test
     */
    public function parseCommandStoresUrlsAndReportsTheirNumber(): void
    {
        $this->httpClient->respondWith(self::SITEMAP_URL, FakeHttpClient::sitemap([
            'https://example.com/a',
            'https://example.com/b',
        ]));
        $tester = new CommandTester(new ParseSitemap('sitemapcrawler:parse', $this->createCrawler()));

        $exitCode = $tester->execute(['url' => self::SITEMAP_URL]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Found 2 URLs in sitemap', $tester->getDisplay());
        self::assertSame(['https://example.com/a', 'https://example.com/b'], $this->getStoredUrls());
    }

    /**
     * @test
     */
    public function crawlCommandReportsStatusCodes(): void
    {
        $this->insertUrl('https://example.com/a', 1);
        $this->insertUrl('https://example.com/b', 2);
        $this->httpClient->respondWith('https://example.com/a', 'content');
        $tester = new CommandTester(new CrawlEntries('sitemapcrawler:crawl', $this->createCrawler()));

        $exitCode = $tester->execute(['number' => '2']);

        self::assertSame(0, $exitCode);
        $display = $tester->getDisplay();
        self::assertStringContainsString('Finished crawling.', $display);
        self::assertStringContainsString('Got 1 entries with status code 200', $display);
        self::assertStringContainsString('Got 1 entries with status code 404', $display);
        self::assertStringContainsString('Got 0 entries with status code failed', $display);
    }

    /**
     * @test
     * @dataProvider numbersBelowOne
     */
    public function crawlCommandCrawlsAtLeastOneUrl(string $number): void
    {
        $this->insertUrl('https://example.com/a', 1);
        $this->insertUrl('https://example.com/b', 2);
        $tester = new CommandTester(new CrawlEntries('sitemapcrawler:crawl', $this->createCrawler()));

        $tester->execute(['number' => $number]);

        self::assertSame(['https://example.com/a'], $this->httpClient->getRequestedUrls());
    }

    /**
     * @return array<string, array{string}>
     */
    public function numbersBelowOne(): array
    {
        return [
            'zero' => ['0'],
            'negative' => ['-5'],
            'not a number' => ['all'],
        ];
    }
}
