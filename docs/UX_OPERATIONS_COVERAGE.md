# Operations UX route coverage

Baseline: `c6c7852`, existing `audit/board-pilot-readiness`. Inventory resolved against `front.php` dispatch order, handlers and Operations/contextual links before editing. A parent page never proves a detail view. This is a presentation pass, not release approval.

## Route checklist

Batch A implemented: quiet Operations tool rows, four permission-filtered hubs, explicit Staff Overview panel adoption, shared semantic page/header/panel/form roles and progressively enhanced scrollable native tables. Authenticated synthetic local browser captures at 1440/768/390px: all five routes render; no page overflow. Screenshot review of root hub desktop/phone completed. No domain-changing form submissions were performed. PHP behavior contract: 47 renderers match baseline; lifecycle presentation regression passes. Details remain pending until their batch.

| Route | Owner / category | Baseline | Implementation | Responsive / accessibility / regression |
|---|---|---|---|---|
| `/admin/accounts` | AccountManagementExperience / B | existing B2; inspect subview | Pending | Not yet verified |
| `/admin/accounts/view` | AccountManagementExperience / B | existing B2; inspect subview | Pending | Not yet verified |
| `/admin/registrations` | RegistrationOperationsExperience / B | legacy; explicit adoption needed | Pending | Not yet verified |
| `/admin/registrations/edit` | RegistrationOperationsExperience / B | legacy; explicit adoption needed | Pending | Not yet verified |
| `/admin/registrations/view` | RegistrationOperationsExperience / B | legacy; explicit adoption needed | Pending | Not yet verified |
| `/admin/registrations/intake` | RegistrationOperationsExperience / B | legacy; explicit adoption needed | Pending | Not yet verified |
| `/admin/email` | EmailOperationsExperience / F | legacy; explicit adoption needed | Pending | Not yet verified |
| `/notification-preferences` | NotificationPreferenceExperience / F | legacy; explicit adoption needed | Pending | Not yet verified |
| `/push-settings` | NotificationPreferenceExperience / F | legacy; explicit adoption needed | Pending | Not yet verified |
| `/push/subscribe` | NotificationPreferenceExperience / F | legacy; explicit adoption needed | Transport; no UI rewrite | Not yet verified |
| `/push/unsubscribe` | NotificationPreferenceExperience / F | legacy; explicit adoption needed | Transport; no UI rewrite | Not yet verified |
| `/push/test` | NotificationPreferenceExperience / F | legacy; explicit adoption needed | Transport; no UI rewrite | Not yet verified |
| `/staff` | StaffDashboardExperience / A | existing B2; inspect subview | Pending | Not yet verified |
| `/admin/operations` | OperationsHubExperience / A | legacy; explicit adoption needed | Pending | Not yet verified |
| `/admin/operations/community` | OperationsHubExperience / A | legacy; explicit adoption needed | Pending | Not yet verified |
| `/admin/operations/resources` | OperationsHubExperience / A | legacy; explicit adoption needed | Pending | Not yet verified |
| `/admin/operations/forms` | OperationsHubExperience / A | legacy; explicit adoption needed | Pending | Not yet verified |
| `/calendar` | CalendarExperience / E | existing B2; inspect subview | Pending | Not yet verified |
| `/calendar/feed` | CalendarExperience / E | existing B2; inspect subview | Transport; no UI rewrite | Not yet verified |
| `/student-profile` | StudentProfileExperience / C | legacy; explicit adoption needed | Pending | Not yet verified |
| `/student-profile/headshot` | StudentProfileExperience / C | legacy; explicit adoption needed | Transport; no UI rewrite | Not yet verified |
| `/student-profile/family` | FamilyPickupExperience / C | existing B2; inspect subview | Pending | Not yet verified |
| `/student-profile/family/review` | FamilyPickupExperience / C | existing B2; inspect subview | Pending | Not yet verified |
| `/family/caregiver-schedule` | CaregiverLogisticsExperience / C | existing B2; inspect subview | Pending | Not yet verified |
| `/theatre-history/resume` | ActingResumeExperience / C | legacy; explicit adoption needed | Pending | Not yet verified |
| `/theatre-history` | TheatreHistoryExperience / C | legacy; explicit adoption needed | Pending | Not yet verified |
| `/archive` | ProductionArchiveExperience / D | legacy; explicit adoption needed | Pending | Not yet verified |
| `/archive/production` | ProductionArchiveExperience / D | legacy; explicit adoption needed | Pending | Not yet verified |
| `/archive/community` | ProductionArchiveExperience / D | legacy; explicit adoption needed | Pending | Not yet verified |
| `/archive/file` | ProductionArchiveExperience / D | legacy; explicit adoption needed | Transport; no UI rewrite | Not yet verified |
| `/admin/member-resources` | OrganizationResourceExperience / F | legacy; explicit adoption needed | Pending | Not yet verified |
| `/admin/member-resources/edit` | OrganizationResourceExperience / F | legacy; explicit adoption needed | Pending | Not yet verified |
| `/member-resources/download` | OrganizationResourceExperience / F | legacy; explicit adoption needed | Transport; no UI rewrite | Not yet verified |
| `/files` | AccountLibraryExperience / F | legacy; explicit adoption needed | Pending | Not yet verified |
| `/files/view` | AccountLibraryExperience / F | legacy; explicit adoption needed | Pending | Not yet verified |
| `/resources` | AccountLibraryExperience / F | legacy; explicit adoption needed | Pending | Not yet verified |
| `/resources/view` | AccountLibraryExperience / F | legacy; explicit adoption needed | Pending | Not yet verified |
| `/people` | PeopleExperience / B | legacy; explicit adoption needed | Pending | Not yet verified |
| `/people/view` | PeopleExperience / B | legacy; explicit adoption needed | Pending | Not yet verified |
| `/volunteer/history` | VolunteerServiceRecordExperience / E | existing B2; inspect subview | Pending | Not yet verified |
| `/volunteer/service-record` | VolunteerServiceRecordExperience / E | existing B2; inspect subview | Pending | Not yet verified |
| `/volunteer/verifications` | VolunteerVerificationExperience / E | existing B2; inspect subview | Pending | Not yet verified |
| `/volunteer/verification` | VolunteerVerificationExperience / E | existing B2; inspect subview | Pending | Not yet verified |
| `/admin/volunteer-verification` | VolunteerVerificationExperience / E | existing B2; inspect subview | Pending | Not yet verified |
| `/volunteer/training` | VolunteerDevelopmentExperience / E | existing B2; inspect subview | Pending | Not yet verified |
| `/admin/volunteer-development` | VolunteerDevelopmentExperience / E | existing B2; inspect subview | Pending | Not yet verified |
| `/admin/volunteer-shifts` | VolunteerShiftManagementExperience / E | existing B2; inspect subview | Pending | Not yet verified |
| `/admin/volunteer-shifts/new` | VolunteerShiftManagementExperience / E | existing B2; inspect subview | Pending | Not yet verified |
| `/admin/volunteer-shifts/view` | VolunteerShiftManagementExperience / E | existing B2; inspect subview | Pending | Not yet verified |
| `/volunteer/approvals` | VolunteerApprovalExperience / E | existing B2; inspect subview | Pending | Not yet verified |
| `/admin/volunteer-approvals` | VolunteerApprovalExperience / E | existing B2; inspect subview | Pending | Not yet verified |
| `/admin/volunteer-approvals/review` | VolunteerApprovalExperience / E | existing B2; inspect subview | Pending | Not yet verified |
| `/volunteer-readiness` | VolunteerExperience / E | existing B2; inspect subview | Pending | Not yet verified |
| `/volunteer-shifts` | VolunteerExperience / E | existing B2; inspect subview | Pending | Not yet verified |
| `/volunteer/shift` | VolunteerExperience / E | existing B2; inspect subview | Pending | Not yet verified |
| `/admin/teams` | TeamExperience / B | legacy; explicit adoption needed | Pending | Not yet verified |
| `/admin/teams/view` | TeamExperience / B | legacy; explicit adoption needed | Pending | Not yet verified |
| `/admin/private-channel` | TeamExperience / B | legacy; explicit adoption needed | Pending | Not yet verified |
| `/admin/moderation/terms` | ModerationExperience / F | legacy; explicit adoption needed | Pending | Not yet verified |
| `/admin/moderation/terms/edit` | ModerationExperience / F | legacy; explicit adoption needed | Pending | Not yet verified |
| `/admin/moderation/queue` | ModerationExperience / F | legacy; explicit adoption needed | Pending | Not yet verified |
| `/admin/channels` | CommunityManagementExperience / F | legacy; explicit adoption needed | Pending | Not yet verified |
| `/admin/channels/edit` | CommunityManagementExperience / F | legacy; explicit adoption needed | Pending | Not yet verified |
| `/admin/productions` | ProductionLifecycleExperience / D | existing B2; inspect subview | Pending | Not yet verified |
| `/admin/productions/view` | ProductionLifecycleExperience / D | existing B2; inspect subview | Pending | Not yet verified |
| `/admin/productions/artwork-image` | ProductionLifecycleExperience / D | existing B2; inspect subview | Transport; no UI rewrite | Not yet verified |
| `/production/casting` | CastingExperience / D | existing B2; inspect subview | Pending | Not yet verified |
| `/production/readiness` | ProductionReadinessExperience / D | existing B2; inspect subview | Pending | Not yet verified |
| `/production/tasks` | ProductionTasksExperience / D | existing B2; inspect subview | Pending | Not yet verified |
| `/production/day` | ProductionDayExperience / E | existing B2; inspect subview | Pending | Not yet verified |
| `/production` | ProductionWorkspaceExperience / D | existing B2; inspect subview | Pending | Not yet verified |
| `/production/artwork-image` | ProductionWorkspaceExperience / D | existing B2; inspect subview | Transport; no UI rewrite | Not yet verified |
| `/production/groups` | ProductionGroupExperience / D | legacy; explicit adoption needed | Pending | Not yet verified |
| `/production/groups/view` | ProductionGroupExperience / D | legacy; explicit adoption needed | Pending | Not yet verified |
| `/production/people` | ProductionPeopleExperience / D | legacy; explicit adoption needed | Pending | Not yet verified |
| `/production/support-needs` | ProductionSupportNeedsExperience / D | legacy; explicit adoption needed | Pending | Not yet verified |
| `/attendance` | AttendanceExperience / E | existing B2; inspect subview | Pending | Not yet verified |
| `/attendance/take` | AttendanceExperience / E | existing B2; inspect subview | Pending | Not yet verified |
| `/attendance/report` | AttendanceExperience / E | existing B2; inspect subview | Pending | Not yet verified |
| `/production/late-pickups` | LatePickupExperience / C | existing B2; inspect subview | Pending | Not yet verified |
| `/production/schedule/new` | ScheduleCreateExperience / E | existing B2; inspect subview | Pending | Not yet verified |
| `/production/notices` | ScheduleNoticeExperience / E | existing B2; inspect subview | Pending | Not yet verified |
| `/production/notice` | ScheduleNoticeExperience / E | existing B2; inspect subview | Pending | Not yet verified |
| `/videos` | RehearsalVideoExperience / F | legacy; explicit adoption needed | Pending | Not yet verified |
| `/videos/view` | RehearsalVideoExperience / F | legacy; explicit adoption needed | Pending | Not yet verified |
| `/videos/play` | RehearsalVideoExperience / F | legacy; explicit adoption needed | Transport; no UI rewrite | Not yet verified |
| `/videos/archive-download` | RehearsalVideoExperience / F | legacy; explicit adoption needed | Transport; no UI rewrite | Not yet verified |
| `/videos/inventory` | RehearsalVideoExperience / F | legacy; explicit adoption needed | Pending | Not yet verified |
| `/production/rehearsal-library` | RehearsalVideoExperience / F | legacy; explicit adoption needed | Pending | Not yet verified |
| `/production/rehearsal-video` | RehearsalVideoExperience / F | legacy; explicit adoption needed | Pending | Not yet verified |
| `/files/download` | ProductionFileExperience / D | legacy; explicit adoption needed | Transport; no UI rewrite | Not yet verified |
| `/admin/files` | ProductionFileExperience / D | legacy; explicit adoption needed | Pending | Not yet verified |
| `/admin/files/edit` | ProductionFileExperience / D | legacy; explicit adoption needed | Pending | Not yet verified |
| `/admin/resources` | ResourceExperience / D | legacy; explicit adoption needed | Pending | Not yet verified |
| `/admin/resources/edit` | ResourceExperience / D | legacy; explicit adoption needed | Pending | Not yet verified |
| `/playbill` | Playbill2Experience / F | legacy; explicit adoption needed | Public rendering retained | Not yet verified |
| `/playbill/asset` | Playbill2Experience / F | legacy; explicit adoption needed | Transport; no UI rewrite | Not yet verified |
| `/playbill/qr` | Playbill2Experience / F | legacy; explicit adoption needed | Transport; no UI rewrite | Not yet verified |
| `/playbill/sign` | Playbill2Experience / F | legacy; explicit adoption needed | Transport; no UI rewrite | Not yet verified |
| `/admin/playbill/media` | Playbill2Experience / F | legacy; explicit adoption needed | Pending | Not yet verified |
| `/playbills` | PlaybillExperience / F | legacy; explicit adoption needed | Pending | Not yet verified |
| `/admin/playbill` | PlaybillExperience / F | legacy; explicit adoption needed | Pending | Not yet verified |
| `/schedule` | ProductionExperience / E | legacy; explicit adoption needed | Pending | Not yet verified |
| `/production/edit` | ProductionExperience / E | legacy; explicit adoption needed | Pending | Not yet verified |
| `/safeguarding/conversations` | SafeguardingConversationReviewExperience / C | legacy; explicit adoption needed | Pending | Not yet verified |
| `/safeguarding/conversations/attachment` | SafeguardingConversationReviewExperience / C | legacy; explicit adoption needed | Transport; no UI rewrite | Not yet verified |
| `/safeguarding/cases` | SafeguardingCaseExperience / C | legacy; explicit adoption needed | Pending | Not yet verified |
| `/safeguarding/case` | SafeguardingCaseExperience / C | legacy; explicit adoption needed | Pending | Not yet verified |
| `/safeguarding` | SafeguardingExperience / C | legacy; explicit adoption needed | Pending | Not yet verified |
| `/safeguarding/review` | SafeguardingExperience / C | legacy; explicit adoption needed | Pending | Not yet verified |
| `/safeguarding/audit` | SafeguardingExperience / C | legacy; explicit adoption needed | Pending | Not yet verified |

## Embedded and absent surfaces

### Batch B evidence

Accounts (directory and detail), People (directory/detail), Teams (directory/membership/private room), and registrations (opportunities/edit/submissions/intake) explicitly adopt page/header/panel/form roles. All 11 route samples rendered at 1440/768/390px without page overflow or PHP errors. Desktop and phone screenshots reviewed for account detail, registration edit/intake and team membership. Ten comparable pre/post form inventories match actions, methods and named field/type contracts. Intake baseline initially used the wrong query key; its corrected populated review was checked independently, not claimed as a matching baseline. The PHP behavior contract and closed-form/roster boundary tests pass. Communicating account/registration decisions were not submitted.

### Batch C evidence

Student profile, family/pickup, relationship requests, caregiver-only calls, theatre history/résumé, Late pickup, safeguarding queues/case detail/conversation review/audit explicitly adopt the shared presentation. All 13 route samples rendered at 1440/768/390px with no page overflow or PHP errors after correcting the conversation template's base-path argument. The caregiver view was tested with an actual synthetic active caregiver relationship, not administrator access. Credential review initially exercises its missing-record fallback; a populated decision needs a credential fixture. Screenshot review includes restricted case detail and phone conversation review. Gold-on-white panel labels were replaced with readable muted text. Résumé styles are conditional on non-print mode; public letters are not opted in. PHP behavior contracts, safeguarding review regression and lifecycle presentation tests pass. No case decision, statement invitation or pickup relationship was submitted through the UI.

### Batch D evidence

Production directory/settings/artwork/lifecycle, workspace/casting/readiness/tasks, roster/groups, restricted support needs, file/resource administration and archive renderers explicitly adopt the standard. All 17 route samples render at 1440/768/390px without page overflow or PHP errors. Reviewed desktop group membership/settings and phone archive production screenshots. Neutralized dark-banner leftovers including an unreadable white return link, pale archive labels and roster status text. Existing working-production hero artwork and casting interaction model remain intact; no confidential summary was added. New-file form and resource edit were sampled; binary file/version and artwork-present samples require dedicated assets. PHP behavior contract and lifecycle boundary tests pass. Completion/archive/destructive actions were not submitted.

### Batch E evidence

25 screen routes adopt or refine existing B2 participation components, including schedule edit/create, unsent notice review, attendance take/report, production day, volunteer approval detail, shifts, eligibility/training and verification/coordinator administration. Each was captured at 1440/768/390px with no document overflow. Reviewed desktop populated notice draft and phone shift creation/date controls. Fixed inherited white return links and pale eligibility/coverage/status text. Service record styling is conditional on non-print mode; `/volunteer/verification` is a printable certificate, not an app-shell screen, and is intentionally retained. Synthetic metadata/unsent drafts were added only to the isolated local schema; no publishing, notification permission request, worker or real certificate issuance occurred. Lifecycle/closed-form and PHP behavior checks pass. Actual submitted attendance and volunteer decisions are covered by the separate isolated backend acceptance suite, not these screenshots.

### Batch F evidence and functional blocker

Email operations, notification preferences, delegated `PushExperience` settings, member resources, shared file/resource libraries, channel management, moderation terms/queue, videos and Playbill controls adopt explicit components. Phone/desktop screenshots reviewed for channels, Playbill editing and notification diagnostics. Public programs, QR/sign/asset streams and printable service letters retain their own rendering. Video inventory and `/production/rehearsal-video` are transport/download routes, not unstyled screens. Media fixtures are metadata-only: no playback, upload or binary delivery claim.

**Blocked actual route:** `/admin/playbill/media` fails before its template in the existing cast-profile synchronization query (`cr.role_title` references an undefined alias). Fully migrated isolated schema reproduces this. Backend correction is outside the authorized UX scope and tracked in TODO. To review the template without misrepresenting controller success, a temporary authenticated, GET-only loopback presentation fixture invoked the existing `adminPage` renderer without synchronization. It rendered at 1440/768/390px with no page overflow; screenshots are explicitly named `playbill-media-fixture-*`. These are template-only evidence, not a passing route or successful sponsor/cast/artwork save. Production code has no fixture route or bypass.

Keyboard interaction review identified and fixed Volunteer Leads focus restoration/trapping and a narrow-screen Casting drawer sizing issue. No notification permission was requested, device subscribed, test notification queued, worker activated or publication submitted. PHP behavior contracts include delegated Push rendering (48 renderers); source contracts are not a claim that every workflow was executed.

- Account detail owns contact editing, roles, invitations, account lifecycle and relationship links; no separate roles/settings screen is invented. People detail retains staff relationship/roster context.
- Registrations owns opportunity edit, submitted applications and person/household intake review. Teams owns member editing and private-channel creation.
- Production detail owns artwork, lifecycle configuration, typed completion/archive confirmations. Casting drawers, attendance drawers and late-pickup review remain separate interaction checks.
- Playbill media owns sponsors, approved biographies, cast profiles, edition/publication controls; public pages and protected asset routes remain separate.
- There is no standalone general organization-settings, staff-access, message-administration or notification-delivery-administration route. Existing accounts, safeguarding conversation review, email operations and personal notification settings remain their real destinations. No feature is invented.
- `ScheduleImportExperience` exists but `/production/schedule/import` is not registered in the dispatch table; record the navigation dependency, do not activate an unregistered feature during UX work.
- Member Home/Family/Community/Messages/Notifications retain prior scoped modernization; shared resources/history/print views are checked explicitly when an operational flow reaches them.
