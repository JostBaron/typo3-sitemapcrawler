<?php

declare(strict_types=1);

namespace Jbaron\Sitemapcrawler\Tests\Functional;

use TYPO3\CMS\Core\DataHandling\PageDoktypeRegistry;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ConfigurationTest extends AbstractFunctionalTestCase
{
    /**
     * TYPO3 11 configures this in ext_tables.php, TYPO3 12 in the TCA.
     *
     * @test
     */
    public function tableIsAllowedOnStandardPages(): void
    {
        if ((new Typo3Version())->getMajorVersion() < 12) {
            $allowedTables = $GLOBALS['PAGES_TYPES']['default']['allowedTables'] ?? '';
            self::assertContains(self::TABLE, GeneralUtility::trimExplode(',', $allowedTables, true));
            return;
        }

        self::assertTrue(
            GeneralUtility::makeInstance(PageDoktypeRegistry::class)
                ->isRecordTypeAllowedForDoktype(self::TABLE, PageRepository::DOKTYPE_DEFAULT)
        );
    }
}
