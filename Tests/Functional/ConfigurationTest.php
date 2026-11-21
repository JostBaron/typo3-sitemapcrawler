<?php

declare(strict_types=1);

namespace Jbaron\Sitemapcrawler\Tests\Functional;

use TYPO3\CMS\Core\DataHandling\PageDoktypeRegistry;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Migrations\TcaMigration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ConfigurationTest extends AbstractFunctionalTestCase
{
    /**
     * @test
     */
    public function tableIsAllowedOnStandardPages(): void
    {
        self::assertTrue(
            GeneralUtility::makeInstance(PageDoktypeRegistry::class)
                ->isRecordTypeAllowedForDoktype(self::TABLE, PageRepository::DOKTYPE_DEFAULT)
        );
    }

    /**
     * TYPO3 migrates outdated TCA on the fly and only logs it, so outdated settings
     * would otherwise go unnoticed.
     *
     * @test
     */
    public function tcaNeedsNoMigration(): void
    {
        $tca = require __DIR__ . '/../../Configuration/TCA/' . self::TABLE . '.php';
        self::assertIsArray($tca);

        $migration = new TcaMigration();
        $migration->migrate([self::TABLE => $tca]);

        self::assertSame([], $migration->getMessages());
    }
}
