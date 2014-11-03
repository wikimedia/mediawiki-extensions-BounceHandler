-- MySQL version of the database schema for the BounceHandler extension.
-- Licence: GNU GPL v2+
-- Author: Tony Thomas, Legoktm, Jeff Green

CREATE TABLE /*_*/bounce_records (
	br_id 		INT unsigned        NOT NULL PRIMARY KEY auto_increment,
	br_user_email		VARCHAR(255)	NOT NULL, -- Email address of the failing recipient
	br_timestamp   	varbinary(14)   NOT NULL,
	br_reason	VARCHAR(255)	NOT NULL  -- Failure reasons
)/*$wgDBTableOptions*/;

CREATE INDEX /*i*/br_mail_timestamp ON /*_*/bounce_records(br_user_email(50), br_timestamp);
