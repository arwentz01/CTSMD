# CTSMD Connect Product Charter

## Purpose

CTSMD Connect is a private theatre community and operations platform for the Children’s Theatre of Southern Maryland. It is intended to reduce communication friction, centralize production information, support volunteers, and create safer digital interactions for students and adults.

## Product promise

**A safer, smarter way to keep our theatre community connected.**

The product should answer a simple daily question for every user: **What do I need to know or do today?**

## Who it serves

Conceptual roles include:

- Owner
- Administrator
- Safeguarding Administrator
- Production Staff
- Instructor
- Volunteer Coordinator
- Volunteer
- Parent / Guardian
- Student
- Board Member
- General Member

A person may hold more than one role at the same time.

## Experience principles

1. **Visual-first development**
   - Approve the product experience before building deep services.
   - Use realistic mocked data to make workflows understandable.

2. **Safety is architecture**
   - Adult/student private messaging is not allowed.
   - Approved guardians must be included in adult/student conversations.
   - Future server logic, not the client, decides the final participant list.

3. **Mobile where life happens**
   - Parents, students, volunteers, and general members should have fast, app-like mobile workflows.

4. **Desktop where complexity belongs**
   - Staff/admin tools may use richer desktop layouts for filtering, batch work, review, reporting, and scheduling.
   - Desktop-optimized never means desktop-only.

5. **Theatre-specific, not generic SaaS**
   - The product should feel connected to productions, call times, families, Playbills, backstage work, and community.

6. **Grow without a rewrite**
   - Initial deployment should remain compatible with PHP 8.x and MySQL/MariaDB on shared hosting.
   - Boundaries should support later migration to a VPS/cloud environment and a future mobile app.

## Brand direction

Use a visual language inspired by CTSMD:

- black / near-black
- deep red
- gold / yellow
- white / warm paper tones
- restrained theatre curtain, spotlight, stage, and Playbill motifs

Avoid purple, generic bootstrap-dashboard styling, and dense tables as the dominant experience.

## Core product areas

### Community

- Announcements
- Channels
- Resources
- Direct adult-to-adult messages
- Safeguarded family-visible messages
- Notifications

### Families and students

- Guardian/student relationships
- Student schedule
- Forms
- Attendance / absence reporting
- Future pickup/release permissions
- Emergency contacts

### Volunteer operations

- Volunteer profiles
- Background checks
- Child safety training
- Facility education
- Role-specific training
- Shift eligibility
- Shift signup
- Coverage and gaps
- Hours / attendance / no-shows

### Productions

- Rehearsals
- Performances
- Call times
- Production groups
- Resource hub
- Cast/crew information
- Digital Playbills
- Future costume/measurement tracking

### Administration

- People
- Families
- Roles
- Productions/groups
- Channels
- Announcements
- Forms
- Schedules
- Volunteer requirements
- Training records
- Background checks
- Reports
- Audit log
- Settings

## Safety rules that must survive every implementation

- Adult-to-adult conversations may be direct.
- Adult-to-student conversations require approved guardian participants.
- Student-to-adult conversations require approved guardian participants.
- Student-to-student direct messaging is disabled for MVP unless explicitly approved later.
- A required guardian cannot be removed while the student remains in the conversation.
- The client may request a conversation, but the server must calculate the valid participant set.
- Future deletion behavior must preserve required audit history.
- Sensitive admin actions and overrides must be auditable.

## Build philosophy

The approved experience is the contract. Services are added only when they support a validated screen, interaction, or rule.
