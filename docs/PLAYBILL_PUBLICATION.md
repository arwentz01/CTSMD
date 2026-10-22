# Playbill Publication System

## Editorial workflow

Each production has one stable public slug. Staff prepare cover details, ordered editorial sections, production-specific cast entries, artwork, credits, and sponsor placements inside Connect. The lifecycle is `draft` -> `review` -> `published` -> `archived`; publication creates an immutable JSON edition in `playbill_editions`. Archiving preserves public access. Unpublish is a separate, deliberate action and does not erase editions or audit history.

Public URLs use `/playbill?slug={slug}`. Content updates never rotate this URL. Published and archived editions are available without a CTSMD session; all administrative routes remain permission-gated. The public route sends `X-Robots-Tag: noindex, nofollow` until CTSMD makes an explicit search-indexing policy decision for Student biography content.

## Student publication consent

`student_profiles` remains the reusable source for preferred name, biography, headshot, and photographer credit. `playbill_cast_profiles` stores the production-specific public copy and display order. Staff may prepare that copy, but an entry marked visible cannot be published until its Student or an authorized current guardian approves it.

Editing approved public copy returns it to pending. Withdrawing approval immediately removes the entry and headshot from public queries and asset delivery without deleting the audit record. Disabled accounts are also excluded at read time. Staff must not work around this gate by placing Student information in free-form editorial sections.

## Sponsors

Sponsors are reusable library records with Premium or Regular tier, logo, advertisement artwork, public description, website/CTA, internal contact fields, notes, and sponsorship dates. A Playbill attachment controls placement label, card/half-page/full-page format, ordering, and active state. Removing a sponsor from an edition deactivates the attachment; it does not destroy the sponsor record or publication history.

## Audience and QR access

The public reader is responsive, keyboard navigable, reduced-motion aware, and includes a table of contents, cast, team, editorial sections, sponsor hierarchy, configured engagement links, sharing, and print styling. External links use `noopener noreferrer`. Images are served only through policy-checking routes.

Every published Playbill exposes an SVG QR code at `/playbill/qr?slug={slug}`. Add `download=1` for print artwork and `background=transparent` for transparent output. `/playbill/sign?slug={slug}` provides a simple printable audience sign. QR regeneration does not alter the public URL.

## Deployment

1. Back up the CTSMD database and private file storage.
2. Apply `database/migrations/068_playbill_publication_system.sql` once, after migrations 008, 035, and 038.
3. Deploy application files and the bundled LGPL TCPDF QR encoder/license.
4. Run the runtime schema guard and full PHP lint.
5. With fake records, verify draft denial, approval/withdrawal, publish/archive/unpublish, public assets, QR destinations, and fresh-session access.
6. Confirm server upload limits support intended artwork sizes.

Migration 068 has not been deployed by this source change.
