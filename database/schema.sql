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
-- Membership tiers shown on the home page
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
    punchpass_url VARCHAR(500) NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB;

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
    ('cta_label',  'START TODAY!');

-- Names and URLs taken from PunchPass itself, so the site says the same thing
-- the checkout page does. Nothing here charges anyone — these are links.
INSERT INTO memberships (name, price, period, description, punchpass_url, sort_order) VALUES
    ('Monthly Membership', 35.00, 'month',
     'Unlimited access to the club at all times.',
     'https://fightnight.punchpass.com/catalogs/purchase/membership/65703', 0),
    ('The Ultimate', 65.00, 'month',
     'Unlimited access to the club at all times, plus unlimited training and classes.',
     'https://fightnight.punchpass.com/catalogs/purchase/membership/60763', 1);
