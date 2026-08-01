# Product charter

## Purpose

CTSMD Connect will give Children’s Theatre of Southern Maryland a private, organization-managed place for reliable communication among students, families, staff, volunteers, instructors, and administrators.

## Product promise

**A safer, smarter way to keep our theatre community connected.**

The product combines straightforward channel communication, group updates, and community tools without becoming a public social network.

## Non-negotiable principles

1. **Private by default.** Accounts are invitation-only. There is no anonymous access or public member discovery.
2. **Safety is structural.** An adult and student cannot have a private conversation. Every approved guardian for the student is added by the server as a required participant.
3. **The server is authoritative.** Client requests express intent; authorization and final participant sets are calculated server-side inside a transaction.
4. **Auditable, not erasable.** Sensitive records use deactivation or soft deletion, and important actions produce append-only audit events.
5. **One foundation, multiple clients.** Business rules stay outside page templates so the same services can later support a Flutter app through versioned JSON endpoints.
6. **Earn complexity.** Build for Bluehost today with plain PHP, MySQL, polling, and queued notification records. Introduce infrastructure only when demand warrants it.

## MVP users

Owner, Administrator, Safeguarding Administrator, Production Staff, Instructor, Volunteer, Parent/Guardian, Student, and General Member. Accounts may hold multiple roles.

## MVP outcomes

- Organization administrators can invite and manage members, roles, family relationships, groups, and channels.
- Members receive relevant announcements and participate in permitted discussions.
- Adults can message adults directly.
- Adult–student conversations always include every active approved guardian as a protected participant.
- Student–student direct messaging is disabled.
- Administrators have safety visibility, reporting hooks, and an audit trail.

## Explicitly deferred

Events and schedules, attendance, digital Playbills, registrations, waivers, volunteer shifts, public website replacement, payments, and native mobile applications are later modules. Their future presence influences boundaries and naming but not Build 001 behavior.

## Build 001 acceptance criteria

- Framework-free PHP project structure and single web entry point
- Named routes for landing, admin preview, and health check
- Environment-based configuration with no committed secrets
- Responsive CTSMD visual language
- Baseline MySQL schema draft representing roles, families, organizations, channels, conversations, messages, audit logs, and notifications
- Recorded architecture and Bluehost deployment assumptions

