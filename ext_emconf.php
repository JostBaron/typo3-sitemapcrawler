<?php

$EM_CONF[$_EXTKEY] = array(
	'title' => 'Sitemap crawler',
	'description' => 'Extension for parsing a sitemap and fetching its entries to warm up caches.',

	'author' => 'Jost Baron',
	'author_email' => 'jost.baron@mein-bauernhof.de',
	'author_company' => 'Mein Bauernhof GbR',
	'state' => 'stable',
	'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'typo3'     => '11.5.0-12.4.99',
            'php'       => '7.4.0-8.3.99',
        ],
    ],
);
