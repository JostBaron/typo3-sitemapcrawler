
CREATE table tx_jbaron_sitemapcrawler_domain_model_sitemapurl (
    # Mandatory fields
    uid int(11) NOT NULL auto_increment,
    pid int(11) DEFAULT '0' NOT NULL,

    # The domain model fields
    url varchar(255) DEFAULT '' NOT NULL,
    last_crawled int(11) DEFAULT '0' NOT NULL,
    last_status_code int(11) DEFAULT NULL,

    PRIMARY KEY (uid),
    KEY url (url),
    KEY last_crawled (last_crawled),
);
