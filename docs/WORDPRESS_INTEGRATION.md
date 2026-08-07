# WordPress Integration Concept

WordPress is optional infrastructure for CTSMD Connect, not a required runtime dependency for Build 001.

## Good future uses for WordPress

WordPress may eventually manage:

- public CTSMD pages
- show/class/news content
- search-engine-facing pages
- general website navigation
- public media/content blocks
- selected public Connect-powered content
- an optional administrative shell through a CTSMD Connect plugin

## What CTSMD Connect must continue to own

The Connect application should remain authoritative for:

- guardian/student relationships
- safeguarded messaging rules
- organization/app roles and permissions
- family relationships
- volunteer requirements and eligibility
- training/compliance records
- messaging data
- audit logs
- application API behavior
- safety-sensitive workflow invariants

## Possible plugin responsibilities

A future WordPress plugin could provide:

- a Connect admin menu
- single-click navigation into Connect admin tools
- channel/announcement controls through APIs
- family/guardian administration views
- volunteer shift and compliance views
- event/content synchronization
- shortcodes or blocks for selected public Connect content
- account/login bridge where appropriate

The plugin should call documented Connect APIs rather than duplicating domain logic inside WordPress.

## Boundary principle

**WordPress may expose controls. CTSMD Connect owns the rules.**

That boundary keeps the platform portable if CTSMD changes website CMS, hosting, or public-site strategy later.
