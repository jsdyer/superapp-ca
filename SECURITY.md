# Security and operating boundaries

This first release includes prepared PDO statements, ownership checks on entry writes and reads, private-by-default entries, escaped content, CSRF on every browser POST, session ID rotation, strict/HttpOnly/SameSite cookies, configurable Secure cookies, CSP, request size limits, password hashing, authentication-version invalidation, token hashing and rotation, rate limits, report handling and guestbook/directory moderation.

The application never executes member code, fetches saved URLs, accepts arbitrary HTML/CSS, proxies requests, or uploads files. Archive entries catalogue links only. Short links show their full destination before navigation. http/https are the only accepted user URL schemes; embedded credentials and control characters are rejected. These choices avoid turning an early release into a general fetcher or upload service.

All profile fields, /now and /uses are public by address. Listing opt-out is not access control. Unlisted entries are readable by anyone with the URL. Private content is excluded from public feeds, community queries and public profile entries. Suspended users’ profiles and entries return not-found, and their listings/messages are removed from public results.

Limitations: no email verification, self-service reset, MFA, token scopes/expiry, automatic malware analysis, IP reputation service, dedicated moderation audit log or sophisticated anti-spam system. Rate limiting is keyed to the connecting IP (plus member ID for authenticated scopes), uses fixed windows, and is not a substitute for edge controls. Passwords use PHP's default password-hashing algorithm; input length is bounded to 72 bytes. Raw member emails are stored for the operator but never displayed publicly or included in exports. Account export intentionally omits them.

Install only `public/` as the web root. Keep configuration, runtime data and backups outside it. Deploy on HTTPS with `session_secure=true`. Do not enable demo mode or seed data in production. The optional contribution URL is trusted operator configuration; use a legitimate HTTPS provider and do not collect card data in this app.

If reporting a vulnerability, contact the instance operator privately through their configured contact channel or use the repository’s private vulnerability reporting feature if enabled. Do not post credentials, real private records, or exploit details involving another member in a public issue. Include the affected version, route and a reproduction using test accounts.


## Community tool boundaries

Private start links are never fetched by the server. Projects apply one visibility to all logs, milestones and costs. Webring website ownership is not verified; navigation excludes pending, suspended and discovery-disabled members, and external hops show the destination first. Directory corrections remain private to their author in export and to administrators in review. All new browser mutations require the existing session, CSRF, demo protection and rate limits. Funding reports require an administrator and are manually entered aggregates; no payment credentials are handled.

Public project and ring reports use the moderation desk. Administrators can remove rings and suspend owners; project deletion remains an owner operation. Funding/evidence edits do not have an immutable audit history. Resource quotas are for a small pilot and are not guaranteed under concurrent write races. The new tables cascade account-owned deletions, except instance-wide aggregate funding reports, which survive account deletion. Full restoration needs database backups; JSON import still restores entries only.
