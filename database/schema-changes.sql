-- Changes after the coaches were asked what they wanted (2026-09-01).
-- Run these against an existing database; database/schema.sql has them folded in
-- for a fresh install.

-- Coaches set their own training rates rather than the gym publishing one list.
-- One per line, same as certifications — a textarea is the right editor and
-- these are displayed, never calculated.
ALTER TABLE coaches ADD COLUMN rates TEXT NULL AFTER specialties;

-- Socials, asked for on profile pages. Named columns rather than one blob so
-- each renders with its own icon and label, and so a bad value in one does not
-- break the others.
ALTER TABLE coaches ADD COLUMN instagram VARCHAR(200) NULL AFTER rates;
ALTER TABLE coaches ADD COLUMN facebook  VARCHAR(200) NULL AFTER instagram;
ALTER TABLE coaches ADD COLUMN tiktok    VARCHAR(200) NULL AFTER facebook;

-- Wherever a client books this coach — PunchPass if it does 1:1 sessions,
-- otherwise the coach's own Calendly. A plain URL either way, so switching
-- between them is a text edit and not a migration.
ALTER TABLE coaches ADD COLUMN booking_url VARCHAR(500) NULL AFTER tiktok;

-- Classes.
--
-- Covers two separate asks with one table: Gabe wanted "a description of what
-- each class entails", and Kids Boxing wanted its own page and its own nav tab.
-- A class is a description of what a session IS. PunchPass still owns when it
-- runs and who booked it.
CREATE TABLE classes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    -- Its own URL: /classes/kids-boxing
    slug VARCHAR(80) NOT NULL UNIQUE,
    summary VARCHAR(255) NULL,           -- one line, for the listing
    description TEXT NULL,               -- the full explanation
    -- Kristen asked for a minimum age. Nullable because most classes have none,
    -- and a 0 would read as "all ages welcome" rather than "not specified".
    age_min TINYINT UNSIGNED NULL,
    age_max TINYINT UNSIGNED NULL,
    photo_path VARCHAR(255) NULL,
    punchpass_url VARCHAR(500) NULL,     -- book this class
    -- Kids Boxing gets its own nav tab; the rest live on the classes page.
    -- A flag rather than hardcoding the slug, so the next one costs nothing.
    show_in_nav TINYINT(1) NOT NULL DEFAULT 0,
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;
CREATE INDEX idx_classes_published ON classes (is_published, sort_order);
