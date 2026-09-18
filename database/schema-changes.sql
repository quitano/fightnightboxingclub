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

-- Per-login gallery access (2026-09-14). Kristen runs the photo gallery but
-- should not see settings, promotions or anyone else's profile, so it is a
-- switch on the one account rather than something every coach gets.
ALTER TABLE users ADD COLUMN can_manage_photos TINYINT(1) NOT NULL DEFAULT 0 AFTER coach_id;

-- Memberships get their own admin screen and a public page (2026-09-17).
--
-- Until now the two rows were typed straight into the database and the home
-- page showed every published one, so adding a Summer or Kids membership would
-- have landed it next to the two main ones. These columns separate "exists" from
-- "belongs on the home page", and give a seasonal membership the same date
-- window promotions already have.
ALTER TABLE memberships
    -- The two main ones are ticked; extras live only on /memberships.
    ADD COLUMN show_on_home TINYINT(1) NOT NULL DEFAULT 0 AFTER is_published,
    -- Both optional: empty means always on, which is what the two main ones
    -- want. A Summer Membership gets both and comes and goes on its own,
    -- staying in the admin so next year is a date change, not a retype.
    ADD COLUMN starts_on DATE NULL AFTER show_on_home,
    ADD COLUMN ends_on   DATE NULL AFTER starts_on,
    -- A Kids membership also belongs on the Kids Boxing page. A class link
    -- rather than a hardcoded "kids", so any class can carry one later.
    ADD COLUMN class_id INT UNSIGNED NULL AFTER ends_on,
    -- One per line, shown as a checklist on the card — same editor pattern as
    -- coach certifications and rates.
    ADD COLUMN includes TEXT NULL AFTER description,
    -- Free text, not a fixed "Most popular" flag, so it can also read
    -- "Best value" or "Summer only". Blank means no ribbon.
    ADD COLUMN badge VARCHAR(40) NULL AFTER includes,
    ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

CREATE INDEX idx_memberships_live ON memberships (is_published, sort_order);
CREATE INDEX idx_memberships_class ON memberships (class_id);

-- Deleting a class should not take its membership with it — the membership is
-- still sellable, it just loses the extra placement.
ALTER TABLE memberships
    ADD CONSTRAINT fk_memberships_class FOREIGN KEY (class_id) REFERENCES classes (id)
        ON DELETE SET NULL;

-- Put the two main memberships on the home page, and only those two.
--
-- Matched on their PunchPass membership ids rather than "every row that exists
-- today": memberships have been edited on the live site, so a blanket update
-- would tick whatever else has been added since and move it onto the home page.
-- Anything this misses is one tick in the admin, which is the safe way to be
-- wrong here.
UPDATE memberships SET show_on_home = 1
 WHERE punchpass_url LIKE '%membership/65703%'
    OR punchpass_url LIKE '%membership/60763%';

-- The line above the cards on the Memberships page. INSERT IGNORE so re-running
-- this never overwrites wording that has since been edited in the admin.
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
    ('memberships_intro',
     'Pick the membership that fits how you train. Sign up takes a minute and you can start today.');

-- Each coach gets their own URL (2026-09-18).
--
-- Coaches advertise themselves — a card, an Instagram bio, a flyer — and had
-- nowhere to point people except the team page, which lands you on everyone.
-- The slug is its own column rather than derived from the name at render time,
-- because the whole value of the URL is that it keeps working: fixing a typo in
-- a name must not quietly invalidate what is already printed on a card.
ALTER TABLE coaches ADD COLUMN slug VARCHAR(80) NULL AFTER name;

-- Backfill from the names that are already there: "Kristen Alcime" ->
-- "kristen-alcime". Done in SQL so the URLs exist the moment this runs, rather
-- than waiting for someone to open and re-save each profile.
UPDATE coaches
   SET slug = TRIM(BOTH '-' FROM LOWER(REGEXP_REPLACE(name, '[^a-zA-Z0-9]+', '-')))
 WHERE slug IS NULL OR slug = '';

-- Unique only after the backfill, so the index is added to data that already
-- satisfies it. Two coaches with the same name would collide here; the app's
-- uniqueSlug() appends -2 and takes over from this point on.
CREATE UNIQUE INDEX idx_coaches_slug ON coaches (slug);
