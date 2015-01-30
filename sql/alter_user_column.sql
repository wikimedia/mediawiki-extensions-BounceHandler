-- MySQL version of the database schema for renaming br_user to br_user_email for the BounceHandler extension.
-- Licence: GNU GPL v2+
-- Author: Tony Thomas, Legoktm, Jeff Green

ALTER TABLE /*_*/bounce_records CHANGE br_user br_user_email VARCHAR(255) NOT NULL;