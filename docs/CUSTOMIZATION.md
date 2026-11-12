# Make Superapp your own

Superapp remains one PHP/PDO LAMP application. Features are optional services, not separate deployments or paid tiers. Donations never unlock functionality.

## Operator controls

Sign in as an administrator, then open **Instance features** in the workspace sidebar (`/admin/features`). Select individual services or start with the personal, maker, all-features or core-only preset. Save to apply. Choose a default theme and a short welcome message on the same page. Stale settings forms are rejected so one administrator cannot silently overwrite another's changes.

The 23 switches cover the eight writing apps plus Start page, Projects, Webrings, Canadian directory, /now garden, /uses, Guestbook, Commons, RSS, Support, Funding, API, Pocket list, Little ideas and Focus room. Disabled services disappear from navigation and their endpoints return 404. Disabled writing kinds are excluded from public profiles, discovery, RSS and API reads. Existing records remain stored and return when enabled again. Exports include saved records even while their feature is disabled.

Authentication, public profile basics, appearance, settings, moderation, exports, account deletion and API-token revocation remain available. Switches do not change an entry's public/private setting and are not per-member permission roles. Moderators can still review stored directory records.

For a server-enforced off switch, use `SUPERAPP_DISABLED_FEATURES=api,rings` or set `disabled_features` to that comma-separated string in `config/local.php`. Environment overrides the local config; these hard-off keys override dashboard choices. Use registry keys from `app/customization.php` (writing keys live in `APPS`). An empty string adds no server restriction. Docker operators must pass this environment variable through to the web service if using it.

## Member controls

**Customize** (`/customize`) is always available. New accounts arrive at the optional `/welcome` setup and can skip it.

- Seven palettes: Cedar, Maple, Terminal, Coast, Prairie, Midnight and Paper. Try the public `/themes` gallery before registering.
- Bookish serif, clean sans-serif or Linux-style monospace headings; comfortable or compact spacing.
- Contour, graph-paper, dot or blank profile banners; initials or five built-in icon marks.
- Favourite apps pinned to the sidebar and start page. Unpinned apps remain in All apps. Preferences for temporarily disabled apps are retained.
- Six optional start-page widgets: shortcuts, note, recent entries, projects, pocket tasks and ideas.
- Start page or workspace as the after-login destination. A disabled Start page falls back to the workspace.

Starter kits offer a simple setup, Linux tinkerer, small-web writer or wandering maker. They select a theme, font and favourites; they do not create or publish content. Changes preview locally and only persist when saved. Your theme, heading style, avatar and banner decorate your public profile. Arbitrary CSS, uploaded avatars and HTML are not accepted.

## Three small reasons to come back

**Pocket list** (`/pocket`) holds up to 200 private tasks with edit, complete, reopen and remove controls. Other members cannot read or alter them. They export with the account and are removed by account deletion.

**Little ideas** (`/ideas`) rotates through 12 original prompts about homelab notes, preservation, Canadian alternatives, photography and the small web. Another idea cycles through the set. Signed-in members can create a private entry draft; writing tools must be enabled. URL-based prompts fall back to a notebook draft when available. No scheduler, AI service, external fetching or automatic posting is involved.

**Focus room** (`/focus`) is a local browser timer: 5, 15, 25 or 45 minutes, pause/resume/reset and a temporary note. It works without an account. No sounds, notifications, history or server-side persistence; closing or reloading clears it. Suspended tabs/devices may delay the visible update, which catches up when the page becomes active.

These are low-pressure conveniences: no streak penalties, leaderboards or donation-driven status.

## Storage and upgrades

Run `php bin/console.php migrate` after deploying this release. `database/customization.sql` adds three tables without rewriting existing rows. See [deployment instructions](DEPLOYMENT.md). Preferences and pocket tasks appear in account JSON exports; import still restores entries only. Keep full database backups for recovery. Theme previews and focus sessions are not stored.
