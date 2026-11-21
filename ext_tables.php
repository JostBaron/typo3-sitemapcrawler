<?php

use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') || die();

// TYPO3 12 reads ['ctrl']['security']['ignorePageTypeRestriction'] from the TCA
// instead, and deprecates this call.
if ((new Typo3Version())->getMajorVersion() < 12) {
    ExtensionManagementUtility::allowTableOnStandardPages(
        'tx_jbaron_sitemapcrawler_domain_model_sitemapurl'
    );
}
