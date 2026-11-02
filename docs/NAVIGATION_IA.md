# CTSMD Connect Navigation & Information Architecture

## Status

This document is the canonical navigation contract for CTSMD Connect after Visual Passes 1–3.

The exploratory prototype pages intentionally tested different shells and navigation patterns. Those differences are not product requirements. Going forward, new implementation work should use the consolidated architecture below unless a later approved product decision updates this document.

## Governing Principle

One app. Different responsibilities. Predictable places.

The global shell remains consistent across member, volunteer, production staff, and administrative experiences. Role and permission determine which destinations are visible; they do not create a different application structure.

## Global Destinations

### Home
Personal starting point for the signed-in person.

Includes:
- Today / immediate priorities
- Family context
- Assigned forms and acknowledgments
- Notifications and recent changes
- Relevant upcoming schedule items
- Relevant volunteer opportunities

### Production
Contextual workspace for an assigned or selected production.

Local section navigation:
- Overview
- Schedule
- Resources
- Playbill
- Volunteers
- Production channels when appropriate

Staff-only production controls should appear as page actions or permissioned section actions, not as an entirely separate navigation shell.

### Community
Shared group communication.

Includes:
- Channels
- Announcements
- Production/community updates

Community is distinct from direct messaging.

### Messages
Person-to-person or small-group communication.

Includes:
- Ordinary eligible conversations
- Safeguarded adult/student conversations
- Guardian-visible protected threads

Child-safety rules are structural and ultimately server-side enforced. Student-to-student direct messaging remains disabled for MVP.

### Volunteer
Readiness-first volunteer experience.

Local section navigation:
- Readiness
- Opportunities
- My commitments
- Training / requirements

A volunteer should understand eligibility before attempting signup.

## Permissioned Operations Destinations

### People
Staff/admin destination for:
- People
- Families and guardian relationships
- Roles
- Production assignments
- Access context
- Volunteer context

### Volunteer Operations
Staff/admin destination for:
- Coverage
- Shift management
- Compliance review
- Credential exceptions
- Training status

This may share the global Volunteer destination while exposing additional staff-only tabs/actions rather than becoming an unrelated application area.

### Safeguarding
Restricted destination for authorized safety/administrative roles.

Includes:
- Exception queues
- Safeguarded conversation review
- Credential/safety escalations
- Evidence and disposition
- Audit history

### Organization Settings
Restricted administrative configuration.

Includes:
- Roles and permissions
- Organization configuration
- Integrations
- WordPress/public-site integration settings where applicable

## Hierarchy Rules

Every authenticated screen should use the same hierarchy:

1. Global shell
2. Global destination
3. Optional section navigation/tabs
4. Page title/context
5. Context-specific actions

Specialized workflows must not invent a new primary navigation system.

Examples:
- Production > Schedule > Production Day > Edit Schedule
- Volunteer > Opportunities > Shift Detail > Confirm Signup
- People > Person Detail > Edit Relationships
- Safeguarding > Review Queue > Case Review > Disposition

## Desktop Navigation

Desktop uses a persistent left navigation shell with:
- CTSMD Connect identity
- Global destinations
- Permissioned Operations section when applicable
- Notification access
- User identity/profile context

The sidebar should remain visually and behaviorally consistent across routes.

## Mobile Navigation

Mobile should not reproduce the full desktop sidebar as a permanent list.

Recommended primary bottom destinations:
- Home
- Production
- Messages
- Volunteer when relevant
- More

`More` contains:
- Community
- Forms
- Playbills when not already production-contextual
- Profile/settings
- Permissioned staff/admin destinations

A mobile drawer may supplement the bottom navigation for deeper role-specific destinations.

## Role Visibility

### Parent / Guardian
Primary:
- Home
- Production when assigned/relevant
- Community when allowed
- Messages
- Volunteer if participating

No general access to People, Safeguarding, or administrative settings.

### Student
Primary:
- Home
- Assigned Production
- Allowed Community spaces
- Safeguarded Messages only

Student-to-student direct messaging is disabled for MVP.

### Volunteer
Primary:
- Home
- Relevant Production context
- Community when allowed
- Messages when allowed
- Volunteer

### Production Staff
Primary member destinations plus permissioned operational tools appropriate to assignment.

### Admin / Safeguarding Roles
Full relevant operational destinations subject to explicit permission.

## Page Pattern Library

Implementation should converge on a small repeatable set of page patterns:

- Dashboard / Today
- Index / list
- Detail
- Workspace / review
- Action / edit
- Public/theatrical presentation

Public Playbill and public event experiences may intentionally depart visually from the authenticated app shell, but authenticated management of those objects should remain inside the standard shell.

## Prototype Debt

Visual Pass 1, Pass 2, and Pass 3 contain intentionally inconsistent navigation shells because they were used for product exploration.

Do not copy those shells directly into implementation.

The `/navigation` review route and this document supersede their navigation choices while preserving approved page-level visual concepts.

## Implementation Rule

Before wiring substantial backend behavior into a screen, place that screen inside the consolidated navigation hierarchy. New routes should identify:

- global destination
- local section/tab, if any
- page pattern
- role/permission visibility
- mobile placement

This keeps navigation debt from accumulating as implementation progresses.
