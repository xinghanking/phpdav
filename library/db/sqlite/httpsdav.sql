PRAGMA encoding = 'UTF-8';
PRAGMA temp_store = 2;
PRAGMA synchronous = 0;
PRAGMA case_sensitive_like = true;
CREATE TABLE IF NOT EXISTS `dav_resource`
(
    `id`       integer PRIMARY KEY ASC AUTOINCREMENT NOT NULL,
    `creation_date`  datetime         DEFAULT CURRENT_TIMESTAMP NOT NULL COLLATE BINARY,
    `level_no`       unsigned integer DEFAULT 0   NOT NULL COLLATE BINARY,
    `path`           varchar(4096)    DEFAULT '/' NOT NULL unique COLLATE BINARY,
    `content_type`   varchar(255)     DEFAULT 'application/unknow' NOT NULL COLLATE BINARY,
    `content_length` unsigned integer DEFAULT 0   NOT NULL COLLATE BINARY,
    `etag`           varchar(255)     DEFAULT ''  NOT NULL COLLATE BINARY,
    `last_modified`  unsigned integer DEFAULT 0   NOT NULL COLLATE BINARY,
    `locked_info`    varchar(2048)    DEFAULT ''  NOT NULL COLLATE BINARY,
    `upper_id`       integer          DEFAULT 0   NOT NULL COLLATE BINARY
);
CREATE INDEX IF NOT EXISTS `upper_id` ON `dav_resource` (`upper_id`);
CREATE TABLE IF NOT EXISTS `dav_conf`
(
    `id`             integer PRIMARY KEY ASC AUTOINCREMENT NOT NULL COLLATE BINARY,
    `create_time`    datetime         DEFAULT CURRENT_TIMESTAMP NOT NULL COLLATE BINARY,
    `http_host`      varchar(255)     DEFAULT '' NOT NULL UNIQUE COLLATE BINARY,
    `resource_id`    integer          DEFAULT 0  NOT NULL REFERENCES `dav_resource` (`id`),
    `user_name`      varchar(60)      DEFAULT '' NOT NULL COLLATE BINARY COLLATE BINARY,
    `security_level` unsigned tinyint DEFAULT 1  NOT NULL COLLATE BINARY,
    `dav_status`     unsigned tinyint DEFAULT 0  NOT NULL COLLATE BINARY,
    `modify_time`    datetime         DEFAULT CURRENT_TIMESTAMP NOT NULL COLLATE BINARY
);
CREATE TABLE IF NOT EXISTS `prop_ns`
(
    `id`          integer PRIMARY KEY ASC AUTOINCREMENT NOT NULL COLLATE BINARY,
    `create_time` datetime     DEFAULT CURRENT_TIMESTAMP NOT NULL COLLATE BINARY,
    `user_agent`  varchar(255) DEFAULT '' NOT NULL COLLATE BINARY,
    `uri`         varchar(255) DEFAULT 'DAV:' NOT NULL UNIQUE COLLATE BINARY,
    `prefix`      char(3)      DEFAULT 'D' NOT NULL COLLATE BINARY
);
INSERT OR IGNORE INTO `prop_ns`(`id`,`uri`,`prefix`) VALUES (0,'DAV:','D');
CREATE TABLE IF NOT EXISTS `resource_prop`
(
    `id`          integer PRIMARY KEY ASC AUTOINCREMENT NOT NULL COLLATE BINARY,
    `create_time` datetime     DEFAULT CURRENT_TIMESTAMP NOT NULL COLLATE BINARY,
    `resource_id` integer      DEFAULT 0 NOT NULL REFERENCES `dav_resource` (`id`),
    `ns_id`       integer      default 0 NOT NULL REFERENCES `prop_ns` (`id`),
    `prop_name`   varchar(255) DEFAULT '' NOT NULL COLLATE BINARY,
    `prop_value`  varchar(255) DEFAULT '' NOT NULL COLLATE BINARY,
    `modify_time` datetime     DEFAULT CURRENT_TIMESTAMP NOT NULL COLLATE BINARY
);
CREATE UNIQUE INDEX IF NOT EXISTS `property` ON `resource_prop` (`resource_id`, `ns_id`, `prop_name`);