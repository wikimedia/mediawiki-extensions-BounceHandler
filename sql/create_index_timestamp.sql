-- MySQL version of the database schema for creating br_timestamp index for the BounceHandler extension.
-- Licence: GNU GPL v2+
-- Author: Tony Thomas, Legoktm, Jeff Green

CREATE INDEX /*i*/br_timestamp ON /*_*/bounce_records(br_timestamp);