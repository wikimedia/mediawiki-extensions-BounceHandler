-- PostgreSQL version of the database schema for the BounceHandler extension.
-- Licence: GNU GPL v2+

DROP SEQUENCE IF EXISTS bounce_records_br_id_seq CASCADE;

CREATE SEQUENCE bounce_records_br_id_seq;

CREATE TABLE bounce_records (
	br_id INTEGER NOT NULL PRIMARY KEY DEFAULT nextval('bounce_records_br_id_seq'),
	br_user_email TEXT,
	br_timestamp TIMESTAMPTZ NOT NULL,
	br_reason TEXT NOT NULL
);

ALTER SEQUENCE bounce_records_br_id_seq OWNED BY bounce_records.br_id;

CREATE INDEX br_mail_timestamp ON bounce_records(br_user_email, br_timestamp);
CREATE INDEX br_timestamp ON bounce_records(br_timestamp);
