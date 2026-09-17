# Memberships — plan (built 2026-09-17)

Noted 2026-09-14, built 2026-09-17. Everything under "Decided" and "Proposed
shape" is in. What follows is kept as the record of why it is shaped this way;
see the bottom for what actually shipped and what is still outstanding.

## What Quitano wants

1. **Memberships managed in the admin.** Create, edit, hide and reorder them there,
   the same way classes and promotions work.
2. **Two main memberships on the home page**, exactly like today:
   - Monthly Membership: $35/month, PunchPass `.../membership/65703`
   - The Ultimate: $65/month, PunchPass `.../membership/60763`
3. **A Memberships page on the public site** listing every membership.
4. **Extra memberships on top of the two main ones**, for example a Summer
   Membership or a Kids Membership. These appear on the Memberships page, but they
   do not replace the two main ones on the home page.

## Where things stand today

- The `memberships` table already exists (name, price, period, description,
  punchpass_url, is_published, sort_order), and both main memberships are in it,
  on local and on the live site.
- **There is no admin screen for it.** The two rows were typed straight into
  the database.
- The home page shows **every** published membership. A Summer or Kids membership
  added today would land on the home page next to the two main ones.
- The **Membership** link in the nav goes out to PunchPass (`/passes`), not to a
  page on this site.
- Buying still happens in PunchPass. Each membership's Join button links there.
  That does not change.

## Decided (2026-09-14)

- **Every membership gets dates: "available from" and "available until".**
  Quitano asked for a timestamp on all memberships. Both are optional: blank
  means always on, so the two main memberships simply leave them empty. A Summer
  Membership gets both dates, then appears and disappears on its own, the same
  way promotions do. Past its end date it drops off the site but stays in the
  admin, so next summer's is a date change, not a retype.
- **A "Show on home page" tick on every membership.** The two main memberships
  get it: Monthly Membership $35 and The Ultimate $65. Extras are left unticked
  and only appear on the Memberships page.
- **A Kids membership also shows on the Kids Boxing page.** Proposed shape: each
  membership gets an optional "Also show on class page" dropdown listing the
  classes. That works for Kids today and for any class later, with nothing
  hardcoded to "kids".
- **"What's included" list and a "most popular" badge: yes.**
  - *What's included:* a textarea with one item per line, shown as a checklist
    on the card, the same editor pattern as coach certifications and rates.
  - *Badge:* a short text field rather than a fixed "Most popular" tick, so it
    can also say "Best value" or "Summer only". It shows as a red ribbon on the
    card. Blank means no badge.
- **The menu's Membership link goes to the new `/memberships` page**, not
  straight to PunchPass. Each card's button reads **Sign Up** (currently "Join")
  and that button is what goes to PunchPass.
- **Whole-dollar prices only: $35, $65.** No cents, ever. Keep the display
  as it is.

## Proposed shape

- **Admin → Memberships:** name, price, period (month / season / one-time),
  description, PunchPass link, available from, available until, Published,
  What's included, Badge, Show on home page, Also show on class page, sort order.
- **Public `/memberships` page:** main memberships first, then extras currently
  in date, each with a Sign Up button to PunchPass.
- **Home page:** published memberships that are ticked for the home page and
  currently in date.
- **Class page:** published, in-date memberships attached to that class, shown
  under the class description.
- **Database:** `show_on_home`, `starts_on`, `ends_on`, `class_id`, `includes`, `badge` added to
  `memberships`, plus `created_at` / `updated_at` so the admin list shows when
  each one was made or last changed.

## Rough size

About half a day: the admin screen, the new columns, the public page, the
class-page block and the nav change. Deploy includes an `ALTER TABLE`
on the live database, like the gallery switch did.

## Built (2026-09-17)

- **Admin → Memberships**, between Classes and Promotions. Same list-and-form
  shape as Promotions, including the "Published, outside its dates" status that
  explains a membership nobody can see.
- **`/memberships`**, listing everything on sale, main ones first by sort order.
  The nav's Membership link points here instead of at PunchPass; the Sign Up
  button on each card is what goes to PunchPass.
- **Home page** shows only the memberships ticked for it, with a "See all
  memberships" link underneath.
- **Class pages** show any membership attached to them, under the Book button.
- **One card renderer** (`fn_membership_card`) behind all three, so a price or a
  badge cannot drift between pages.
- **Whole dollars enforced on save** — typing 25.99 stores 26. The rounding is
  in the repository, not the form, so it holds however the row is written.
- Intro line on the memberships page is a setting (`memberships_intro`), so the
  copy above the cards is editable without a deploy.

Two things were fixed along the way because this work tripped over them:
`schema.sql` was missing the `classes` table and the coach columns added on
2026-09-01, so a fresh install did not match the live database. Both are folded
in now, which the new foreign key also needed.

### Still outstanding

- The **`ALTER TABLE` has not been run on the live database.** It is the last
  block of `database/schema-changes.sql`. Until it runs, the live site will
  error on any page that reads a membership.
- Nothing reorders memberships by drag; sort_order is a number you type, the
  same as everywhere else on the site.
