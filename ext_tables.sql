CREATE TABLE tx_rteckeditorpack_domain_model_comment
(
    uid int(11) NOT NULL auto_increment,
    id varchar(255) DEFAULT NULL,
    thread_id varchar(255) DEFAULT NULL,
    content_id int(11) DEFAULT '0' NOT NULL,
    rte_id varchar(255),
    user_id int(11) DEFAULT '0' NOT NULL,
    content text DEFAULT NULL,
    created_at bigint(20) DEFAULT '0' NOT NULL,
    created_id bigint(20) DEFAULT '0' NOT NULL,

    PRIMARY KEY (uid),
);


CREATE TABLE tx_rteckeditorpack_domain_model_configuration (
	enable smallint(1) unsigned NOT NULL DEFAULT '0',
	config_key varchar(255) NOT NULL UNIQUE DEFAULT '',
    fields text NOT NULL DEFAULT '',
    preset text,
    UNIQUE KEY configuration_key (config_key)
);

CREATE TABLE tx_rteckeditorpack_domain_model_suggestions
(
    uid int(11) NOT NULL auto_increment,
    id varchar(255) DEFAULT NULL,
    user_id int(11) DEFAULT '0' NOT NULL,
    type varchar(255) DEFAULT NULL,
    created_at bigint(20) DEFAULT '0' NOT NULL,
    has_comments varchar(5) DEFAULT NULL,
    data text DEFAULT NULL,

    PRIMARY KEY (uid),
);

CREATE TABLE tx_rteckeditorpack_domain_model_revisionhistory
(
    uid int(11) NOT NULL auto_increment,
    name varchar(255) DEFAULT NULL,
    id varchar(255) DEFAULT NULL,
    authors longtext DEFAULT '0' NOT NULL,
    created_at bigint(20) DEFAULT '0' NOT NULL,
    content_id varchar(255) DEFAULT NULL,
    diff_data longtext DEFAULT NULL,
    attributes longtext DEFAULT NULL,
    current_version int(11) DEFAULT '0' NOT NULL,
    previous_version int(11) DEFAULT '0' NOT NULL,

    PRIMARY KEY (uid),
);

CREATE TABLE tx_rteckeditorpack_domain_model_toolbaritems (
    uid int(11) NOT NULL auto_increment,
	items text NOT NULL DEFAULT '',
    preset varchar(255) DEFAULT NULL UNIQUE,
    PRIMARY KEY (uid)
);

CREATE TABLE tx_rteckeditorpack_domain_model_toolbargroups
(
    uid int(11) NOT NULL auto_increment,
    label varchar(255) DEFAULT NULL,
    tooltip varchar(255) DEFAULT NULL,
    icon varchar(255) DEFAULT NULL,
    custom_icon text DEFAULT NULL,
    items text NOT NULL DEFAULT '',
    PRIMARY KEY (uid),
);