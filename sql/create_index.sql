-- MySQL version of the database schema for the BounceHandler extension.
-- Licence: GNU GPL v2+
-- Author: Tony Thomas, Legoktm, Jeff Green


CREATE INDEX /*i*/br_mail_timestamp ON /*_*/bounce_records(br_user_email(50), br_timestamp);


