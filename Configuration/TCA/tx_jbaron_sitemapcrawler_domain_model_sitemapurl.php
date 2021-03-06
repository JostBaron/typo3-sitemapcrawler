<?php
/*
 * Copyright (C) 2017 mein Bauernhof GbR.
 *
 * This file is subject to the terms and conditions defined in the
 * file 'LICENSE.txt', which is part of this source code package.
 */

$GLOBALS['TCA']['tx_jbaron_sitemapcrawler_domain_model_sitemapurl'] = [
    'ctrl' => [
        'title'             => 'Sitemap URL',
        'label'             => 'url',
        'hideTable'         => true,
        'dividers2tabs'     => true,
    ],
    'types' => [
        '0' => [
            'showitem' => 'url,last_crawled',
        ],
    ],
    'columns' => [
        /**********************************************************************\
         * Shared fields
        \**********************************************************************/
        'pid' => [
            'label'   => 'pid',
            'config'  => [
                'type'     => 'passthrough',
            ],
        ],

        /**********************************************************************\
         * Domain model fields
        \**********************************************************************/
        'url' => [
            'label'   => 'The sitemap url',
            'config'  => [
                'type' => 'passthrough',
            ],
        ],
        'last_crawled' => [
            'label'   => 'The timestamp of the last crawling',
            'config'  => [
                'type' => 'passthrough',
            ],
        ],
        'last_status_code' => [
            'label' => 'Status code from last crawl. Minus one (-1) is for failed HTTP request.',
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'last_request_time' => [
            'label' => 'The time in seconds the last request took (complete download)',
            'config' => [
                'type'  => 'passthrough',
            ]
        ],
    ],
];
