# Community tools

This release adds five practical features to the original eight writing apps. All use the existing PHP session, CSRF checks, account suspension and PDO database layer. No payment account, background worker, third-party API, or JavaScript build is needed.

## Private start page — `/start`

Save up to 100 HTTP(S) shortcuts, with named groups and numeric ordering. Edit or remove each shortcut and keep a private 4,000-byte note. Recent entries and your five most recently updated projects appear alongside it. Addresses open directly in the member's browser; the server never probes LAN addresses or fetches a saved URL. The start page is private; there is no public-sharing switch in this release.

## Project workbench — `/workbench`, `/projects`

Each account can keep 100 projects, each with up to 500 updates. A project has a description, optional source URL, stage (planning/building/paused/finished), and private/unlisted/public visibility. New projects start private.

Updates are build logs, milestones (open or complete), or parts/expenses with CAD amounts. Totals use integer cents. All updates inherit the project's visibility: publishing a project publishes its notes **and costs**. Public projects from discoverable, active members appear in `/projects`; up to 12 also appear on their profile. Unlisted projects remain available by address but stay out of discovery. Private projects require their owner; suspension hides all public project access. Owners can edit and delete both projects and updates. Signed-in readers can report public content.

There are no uploads, images, attachments, project RSS feeds, collaborative editors, or project API endpoints yet. Use plain text and a project/source link. Costs are member-entered records, not receipts or financial advice.

## Webrings — `/rings`

A member can tend up to ten public rings. Creating a ring adds its owner as an approved member using their Superapp profile. Other members request to join using their profile or a HTTP(S) website address. The owner or instance administrator approves or removes membership. External domain ownership is **not verified**.

Only approved, unsuspended members with profile discovery enabled enter public membership/navigation. Previous/next wrap around the ordered membership list; random excludes the starting member. At least two eligible members are needed. A hop shows the chosen destination before leaving the instance; no arbitrary destination query parameter is accepted. Members receive a plain HTML navigation snippet for their own websites.

Rings from owners who opt out of discovery disappear from the ring directory but remain public by address. The signed-in `Your circles` section lets owners and applicants find their own rings. Owners can edit descriptions and delete rings; members can leave. Deleting an owner's account removes their rings and all associated memberships. Ownership transfer is not implemented. Rings can be reported through the existing moderation desk, and administrators can remove them. Each ring is limited to 500 memberships/requests.

## Open funding — `/funding`

Administrators publish one aggregate report per calendar month: hosting, backups, other operating costs, contributions received, and explanatory notes. All amounts are CAD. The page shows the latest report's costs, contributions and remaining gap or surplus, plus up to 120 historical reports. Months without reports have no invented figures. Current-month reports may be partial.

Figures are manually entered and explicitly labelled as operator-published, unaudited reports. Use actual costs and settled contributions; explain fees, accounting basis, and incomplete months in the notes. Editing replaces the month's report; deletion removes it. This is **not an immutable accounting ledger**, payment reconciliation tool or donor database. Reports do not contain a donor identity field and survive administrator account deletion. No demo funding reports are seeded.

The existing `/support` page links to the reports and, when configured, an external pay-what-you-can payment page. There are no premium features, paid placement or contribution requirements.

## Canadian directory evidence

Keep the existing primary connection/source, and optionally document each of ownership, hosting/data location, and manufacturing separately. Each supplied description requires its own HTTP(S) source and a real date checked (today or earlier). Blank dimensions display as not separately documented. Old listings are preserved without inferring new claims.

An optional `Suggested alternative to` label is searchable alongside listing names, categories, provinces and notes. It is a community suggestion, not a claim of feature parity. All initial evidence remains behind the existing approval queue.

Signed-in members can submit a sourced correction on an approved listing. Corrections appear privately on the admin desk and do not automatically change public claims. Administrators can edit evidence and primary notes; an edit moves the listing back to pending, requiring explicit reapproval. Marking a correction reviewed only closes the request. Administrators should update/reapprove the listing or unpublish it as appropriate. No automated source fetching, corporate verification, or scheduled re-review is provided.

## Data and upgrades

`php bin/console.php migrate` adds the tables in `database/community.sql` on SQLite or MariaDB without altering/deleting original rows. See the deployment guide for backup and rollout order. All member-owned additions are included in JSON exports, including nested project updates, private shortcuts/notes and authored corrections. Authentication credentials remain excluded. The export format remains v1 with additive fields; the existing importer still restores **entries only**. A complete database backup is needed for full-instance restoration.

Member-owned data uses cascading foreign keys. Public funding reports are instance records, not owned by any individual account. Existing users receive empty start pages and workbenches until they create content. New demo seeds include a clearly fictional project and webring; existing demo seeds are not overwritten.
