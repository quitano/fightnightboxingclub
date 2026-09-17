-- FightNight Boxing Club — schema
--
-- Classes, bookings and payments are NOT here. PunchPass owns those; this site
-- links out. A second member list that disagrees with the first is worse than
-- having none.

-- ---------------------------------------------------------------------------
-- Who can log in
-- ---------------------------------------------------------------------------
-- Two roles. An 'admin' manages the whole site. A 'coach' can edit exactly one
-- thing: their own profile. That is enforced by coach_id — a coach account is
-- tied to one coaches row and cannot reach another, so giving a coach a login
-- never means trusting them with the rest of the site.
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(60) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    display_name VARCHAR(100) NULL,
    email VARCHAR(160) NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'coach',   -- 'admin' | 'coach'
    -- Which profile this account owns. NULL for admins.
    coach_id INT UNSIGNED NULL,
    -- A coach who also looks after the gallery. Admins can do everything
    -- regardless; this only widens a coach login.
    can_manage_photos TINYINT(1) NOT NULL DEFAULT 0,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
CREATE INDEX idx_users_coach ON users (coach_id);

-- ---------------------------------------------------------------------------
-- Coaches
-- ---------------------------------------------------------------------------
-- Each coach publishes their own number and books their own clients, so phone
-- and email live on the profile rather than in site settings.
--
-- No fight record: the old site never showed one, and for personal training it
-- is qualifications that sell, not a pro record most coaches do not have.
CREATE TABLE coaches (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    role_title VARCHAR(120) NULL,        -- "Head Coach", "Strength & Conditioning"
    phone VARCHAR(40) NULL,
    email VARCHAR(160) NULL,
    photo_path VARCHAR(255) NULL,
    bio TEXT NULL,
    -- One per line. A textarea is the right editor, and three coaches do not
    -- justify two join tables — revisit only if these ever become searchable.
    certifications TEXT NULL,
    specialties TEXT NULL,
    -- Coaches set their own training rates rather than the gym publishing one
    -- list. Same one-per-line textarea: displayed, never calculated.
    rates TEXT NULL,
    -- Named columns rather than one blob, so each renders with its own icon and
    -- a bad value in one does not break the others.
    instagram VARCHAR(200) NULL,
    facebook  VARCHAR(200) NULL,
    tiktok    VARCHAR(200) NULL,
    -- PunchPass if the coach does 1:1 sessions there, otherwise their own
    -- Calendly. A plain URL either way, so switching is a text edit.
    booking_url VARCHAR(500) NULL,
    -- Hidden rather than deleted, so a coach on sabbatical keeps their profile
    -- instead of retyping it later.
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;
CREATE INDEX idx_coaches_published ON coaches (is_published, sort_order);

-- ---------------------------------------------------------------------------
-- Home page promotions — kids classes, camps, seasonal offers
-- ---------------------------------------------------------------------------
-- starts_on / ends_on exist so a summer camp stops showing itself in September
-- without anyone remembering to take it down. Both nullable: a promotion with
-- no dates simply runs until unpublished.
CREATE TABLE promotions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(160) NOT NULL,
    body TEXT NULL,
    photo_path VARCHAR(255) NULL,
    -- Usually a PunchPass pass, sometimes a phone number or another page.
    link_url VARCHAR(500) NULL,
    link_label VARCHAR(60) NULL,         -- "Sign up", "See passes"
    starts_on DATE NULL,
    ends_on DATE NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;
CREATE INDEX idx_promotions_live ON promotions (is_published, sort_order);

-- ---------------------------------------------------------------------------
-- Classes — what a session actually is
-- ---------------------------------------------------------------------------
-- Gabe asked for "a description of what each class entails"; Kids Boxing wanted
-- its own page and its own nav tab. Both are this table. PunchPass still owns
-- when a class runs and who booked it.
CREATE TABLE classes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(80) NOT NULL UNIQUE,    -- its own URL: /classes/kids-boxing
    summary VARCHAR(255) NULL,           -- one line, for the listing
    description TEXT NULL,               -- the full explanation
    -- Nullable because most classes have no limit, and a 0 would read as "all
    -- ages welcome" rather than "not specified".
    age_min TINYINT UNSIGNED NULL,
    age_max TINYINT UNSIGNED NULL,
    photo_path VARCHAR(255) NULL,
    punchpass_url VARCHAR(500) NULL,     -- book this class
    -- Kids Boxing gets its own nav tab; the rest live on the classes page. A
    -- flag rather than a hardcoded slug, so the next one costs nothing.
    show_in_nav TINYINT(1) NOT NULL DEFAULT 0,
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;
CREATE INDEX idx_classes_published ON classes (is_published, sort_order);

-- ---------------------------------------------------------------------------
-- Memberships
-- ---------------------------------------------------------------------------
-- A table rather than settings keys because each tier links to its own PunchPass
-- pass, and there are already two with more likely.
--
-- Price is DECIMAL, not cents: these are hand-typed, displayed, and never
-- arithmetic — PunchPass and Stripe do the actual charging.
CREATE TABLE memberships (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,          -- "Starter", "Unlimited"
    price DECIMAL(7,2) NULL,
    period VARCHAR(30) NULL DEFAULT 'month',
    description TEXT NULL,
    -- One per line, shown as a checklist on the card — the same editor pattern
    -- as coach certifications and rates.
    includes TEXT NULL,
    -- Free text rather than a fixed "Most popular" flag, so a card can also say
    -- "Best value" or "Summer only". Blank means no ribbon.
    badge VARCHAR(40) NULL,
    punchpass_url VARCHAR(500) NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    -- Published says it exists; this says it earns a place on the home page.
    -- Without the split, a Summer or Kids membership would land next to the two
    -- main ones the moment it was added.
    show_on_home TINYINT(1) NOT NULL DEFAULT 0,
    -- The same date window promotions have: a Summer Membership appears and
    -- disappears on its own and stays in the admin, so next year is a date
    -- change rather than a retype. Both null = always on.
    starts_on DATE NULL,
    ends_on DATE NULL,
    -- Also show this membership on one class's page — Kids Boxing was the ask,
    -- but a class link rather than a hardcoded slug, so any class can carry one.
    class_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    -- Losing a class should not delete a membership people can still buy; it
    -- just loses the extra placement.
    CONSTRAINT fk_memberships_class FOREIGN KEY (class_id) REFERENCES classes (id)
        ON DELETE SET NULL
) ENGINE=InnoDB;
CREATE INDEX idx_memberships_live ON memberships (is_published, sort_order);
CREATE INDEX idx_memberships_class ON memberships (class_id);

-- ---------------------------------------------------------------------------
-- Gallery
-- ---------------------------------------------------------------------------
CREATE TABLE photos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    photo_path VARCHAR(255) NOT NULL,
    caption VARCHAR(200) NULL,
    album VARCHAR(80) NULL,              -- free text until albums earn a table
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
CREATE INDEX idx_photos_published ON photos (is_published, sort_order);

-- ---------------------------------------------------------------------------
-- Editable page content and site-wide details
-- ---------------------------------------------------------------------------
CREATE TABLE pages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(80) NOT NULL UNIQUE,
    title VARCHAR(160) NOT NULL,
    body MEDIUMTEXT NULL,
    meta_description VARCHAR(255) NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Address, phone, hours, the home video. Key/value rather than config because
-- these are exactly the things that change without wanting a deploy.
CREATE TABLE settings (
    setting_key VARCHAR(60) NOT NULL PRIMARY KEY,
    setting_value TEXT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO settings (setting_key, setting_value) VALUES
    ('tagline',    "WE'RE IN YOUR CORNER!"),
    ('intro',      'The premier boxing club of WNY! Bridge the gap between boxing & fitness.'),
    ('phone',      '716.299.8797'),
    ('email',      'info@fightnights.com'),
    ('address',    '2421 Hyde Park Blvd, Niagara Falls, NY 14305'),
    -- Every day is the same, so one line rather than seven rows. If that ever
    -- stops being true this becomes a per-day structure.
    ('hours',      'Open 24 hours, 7 days a week'),
    ('map_url',    'https://maps.google.com/?q=2421+Hyde+Park+Blvd,+Niagara+Falls,+NY+14305'),
    ('youtube_id', ''),
    ('cta_label',  'START TODAY!'),
    -- The line above the cards on the Memberships page. Blank is allowed and
    -- simply means no intro — the page does not fall back to canned copy.
    ('memberships_intro',
     'Pick the membership that fits how you train. Sign up takes a minute and you can start today.');

-- Names and URLs taken from PunchPass itself, so the site says the same thing
-- the checkout page does. Nothing here charges anyone — these are links.
-- These two are the main ones, so they are the ones ticked for the home page.
-- Anything added later is an extra: it shows on /memberships and stays off the
-- home page unless someone deliberately ticks it.
INSERT INTO memberships (name, price, period, description, punchpass_url, show_on_home, sort_order) VALUES
    ('Monthly Membership', 35.00, 'month',
     'Unlimited access to the club at all times.',
     'https://fightnight.punchpass.com/catalogs/purchase/membership/65703', 1, 0),
    ('The Ultimate', 65.00, 'month',
     'Unlimited access to the club at all times, plus unlimited training and classes.',
     'https://fightnight.punchpass.com/catalogs/purchase/membership/60763', 1, 1);
