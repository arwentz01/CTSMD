# Build 001 Checkpoint

## Status

Visual-first foundation created on `main`.

Build 001 is intentionally mocked and non-persistent. Its purpose is to establish the CTSMD Connect product experience before backend services are implemented.

## Created

### Foundation

- PHP front controller
- simple route map
- shared mocked theatre data
- responsive CTSMD design system
- minimal vanilla JavaScript
- MAMP-friendly Apache routing
- local database defaults documented for future persistence
- JSON health endpoint

### Public/member screens

- public landing page
- main “what do I need today?” dashboard
- parent/member dashboard variation
- channels experience
- safeguarded messaging concept
- volunteer shift signup
- schedule/events preview
- digital Playbills
- forms/acknowledgments

### Staff/admin screens

- staff operations dashboard
- volunteer operations dashboard
- volunteer profile/compliance view
- volunteer shift editor/management preview
- administration module directory
- WordPress integration concept

## Visual direction established

- near-black theatre shell
- deep CTSMD red
- gold accent color
- warm paper/white content surfaces
- editorial/Playbill-influenced serif headings
- app-like member views
- desktop-rich operations views
- responsive collapse behavior for tablets and phones

## Safety concepts made visible

The messaging prototype explicitly differentiates normal adult direct messages from safeguarded family-visible conversations.

A safeguarded conversation visibly includes:

- staff member
- student
- approved guardian
- guardian visibility banner
- explanation that required guardians cannot be removed while the student remains

The future server-side enforcement requirement is documented in the product and architecture notes.

## Deliberately not implemented

- authentication
- authorization enforcement
- MySQL persistence
- real user accounts
- message creation/delivery
- real guardian enforcement
- volunteer eligibility engine
- background-check verification
- notifications/push
- file uploads
- production registration
- payment processing
- WordPress plugin
- mobile application

## Build 002 recommendation

Keep Build 002 primarily visual and interaction-focused.

Priority areas:

1. Replace temporary brand mark with approved CTSMD logo assets.
2. Refine desktop and mobile navigation based on hands-on review.
3. Add Student, Volunteer, Board, and Safeguarding Admin dashboard variants.
4. Add announcement/channel creation and management mockups.
5. Expand volunteer shift editor and eligibility explanations.
6. Add people/family/guardian linking screens.
7. Add schedule event detail, absence reporting, and staff schedule-builder states.
8. Add notification center and preference mockups.
9. Add empty/loading/error/permission-denied states.
10. Perform accessibility and keyboard/focus refinement.

Database wiring should begin only after the central user journeys and information architecture are approved.
