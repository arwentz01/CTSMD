# Playbill Studio

The existing `/admin/playbill` editor now provides seven focused stages: Overview, Cover & Design, Cast & Crew, Biographies & Headshots, Program Content, Sponsors & Advertising, and Preview & Publish. `/admin/playbill/media` delegates to the same Studio. Public `/playbills` remains the audience directory; `/playbill/consent?profile=ID` is an authenticated, owner/current-guardian-only consent review.

## Existing sources and boundaries

- Accepted casting enrollments and eligible production team functions populate the existing unique account/Playbill profile. Multiple functions do not create separate identities. Team publication still requires reviewing its source fingerprint.
- Manual participants remain tied to their original production credit. Transferred manual credits disappear from current editorial lists without deleting their publication history. Manual source consent and program consent must both allow public content.
- Shared performer biographies/headshots can be explicitly copied into the program. Editorial overrides update the Playbill snapshot, never the account profile, casting record or Acting CV. Changed visible profiles require renewed approval. Student audience names are always first name and last initial, including team credits.
- Editing requires existing Playbill permission and either Administrator role or active Staff membership in that production. Account-management access alone grants no new editorial permission. The server checks scope on POSTs, previews and private images.
- Artwork, portraits and sponsor uploads reuse private StorageService files. Sponsor library metadata remains shared; placement/order/removal belong to the program. Shared sponsors cannot be edited while attached to any live program. Premium sponsor treatment and print pagination reuse the existing publication builder.

## Preview and publication

Saved private preview uses the actual public renderer, including publication pagination, sponsor placements and print CSS. Pending visible profiles may appear in authorized preview; that is not publication consent. Unsaved changes are explicitly marked and are absent from preview.

Public production visibility does not publish its program. Explicit publish validates existing approvals, enables public access and records the existing edition audit. Editorial changes require unpublishing first, saving, previewing and explicitly publishing again. The reader still uses current approved publication tables, not immutable edition snapshot rendering. Consent withdrawal remains immediate.

Public program, images, QR and printable sign enforce publication gates. Program/image/QR responses use no-store. Published URLs cannot be renamed after first publication. Archive preserves existing visibility: an intentionally published historical program stays available, while archiving a private draft cannot publish it. Archived programs are read-only; authorized unpublishing remains possible. Closed production lifecycle rules remain authoritative.

Organization identity comes from existing OrganizationBranding, while the publication layout remains reusable. No schema changes, new publishing engine or PDF dependency.

## Verification

Executed locally using PHP 8.3 and a guarded, migrated `ctsmd_rc1_*` loopback MySQL schema; only synthetic identities/files. No production data or notification workers used.

- Studio MySQL: 20 assertions for synchronization, scoped authorization, Student names, snapshot isolation, approval, editions, unpublish/archive and manual transfer/history.
- Studio authenticated HTTP: 46 assertions covering all seven tabs, legacy entry point, actual renderer preview, current/former guardian consent, real sponsor image upload, scoped/private/public image access, publish, QR and unpublish revocation.
- RC1 real HTTP acceptance: 173 assertions, with stage-specific form selection updated for the Studio.
- Existing publication: 10; pagination: 22 (30 pages); manual participant boundaries; Operations contracts: 52 renderers; Adult participation MySQL: 83; Production People MySQL: 56.
- PHP lint, Studio JavaScript syntax and Git whitespace checks.

These are source/service/HTTP checks, not visual browser, assistive-technology, phone or print-device verification. CI runs the new integration tests with the existing isolated schema job; a local pass does not imply hosted CI passed.

## Remaining limitations

Manual portraits continue to use the original manual participant record rather than a separate program image override. Editorial changes temporarily remove audience access until republished. Existing edition rows provide audit history, not an immutable public edition reader. Concurrent publish/edit requests are not covered by a serialization stress test. No new theme editor, drag-and-drop ordering, PDF service, consent email delivery or native editor was introduced. Keyboard/mobile layout is implemented with native controls and responsive CSS, but visual/device acceptance remains outstanding.
