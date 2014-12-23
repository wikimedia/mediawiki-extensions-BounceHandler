-- MySQL version of the database schema for removing br_mail_timestamp index for the BounceHandler extension.
-- Licence: GNU GPL v2+

ALTER TABLE /*_*/bounce_records DROP INDEX /*i*/br_mail_timestamp;
