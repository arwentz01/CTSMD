<?php
declare(strict_types=1);

/**
 * CTSMD Connect task guides. Keep labels and routes synchronized with actual screens.
 * Guides describe the web workflow; native app steps are not implied.
 * No account data, private URLs, or credentials are included here.
 */
final class HelpGuideCatalog
{
    public static function availableFor(array $user):array
    {
        require_once __DIR__.'/AccessPolicy.php';
        return array_values(array_filter(self::all(),static function(array $guide)use($user):bool{
            return match($guide['permission']){
                'all'=>true,
                'production'=>AccessPolicy::canManageProduction($user),
                'accounts'=>AccessPolicy::canManageAccounts($user),
                'moderation'=>AccessPolicy::canModerateCommunity($user),
                'schedule'=>AccessPolicy::canManageSchedule($user),
                default=>false
            };
        }));
    }

    public static function all(): array
    {
        return [
            ['id'=>'staff-first-day','category'=>'Getting started','title'=>'Your first day as CTSMD staff','audience'=>'Authorized production staff','permission'=>'production','where'=>'Website','route'=>'/production',
             'intro'=>'Find the right show and the tools you need without hunting through menus.',
             'steps'=>[
                'Sign in and open Home to check anything that needs your attention.',
                'Use Working Production to choose the show you are helping with. Confirm the title before making changes.',
                'Open Production for the grouped work areas: Plan & Prepare, Rehearsals & Show Days, and People & Communication.',
                'Start with Schedule and People to understand the show and who is involved.',
                'Use Casting for decisions and offers, Attendance for calls, and Tasks for assigned work only when your role allows it.',
                'Return to Home and Action Center for new notifications and follow-up items.'
             ],'tip'=>'If a tool is missing, ask an administrator to check your assigned role and production access. Do not share another staff member’s login.'],
            ['id'=>'family-first-week','category'=>'Getting started','title'=>'Your first steps as a parent or guardian','audience'=>'Parents & guardians','permission'=>'all','where'=>'Website and app','route'=>'/family/manage',
             'intro'=>'Learn what to do after creating your account and adding children.',
             'steps'=>[
                'Verify your email and sign in to CTSMD Connect.',
                'Open Family or Manage household, then add each participating child separately.',
                'Add a child’s email only if you want them invited to create their own login.',
                'Check Home for membership approval status and items needing attention.',
                'When your family is assigned to a production, use My Theatre or Family for schedules, forms and casting updates.',
                'Open Messages or Community to see conversations and announcements available to your family.'
             ],'tip'=>'Your login, general membership approval, and each child’s production participation are separate. Safeguarded messages have extra protections for younger members.'],
            ['id'=>'student-first-login','category'=>'Getting started','title'=>'Activate your student account and get started','audience'=>'Students & families','permission'=>'all','where'=>'Invitation website, then website or app','route'=>'/login',
             'intro'=>'Use the email invitation from your parent or guardian to activate an existing student profile.',
             'steps'=>[
                'Open the CTSMD student invitation sent to your email address.',
                'Check that the invitation is for you and choose a password to activate your account.',
                'Sign in with your student email and new password.',
                'Open Home to see updates and tasks. Check My Theatre for any shows and schedules assigned to you.',
                'Visit Community for channels you can access, and Messages for conversations allowed by your age and family safeguards.',
                'Ask your parent or guardian to contact CTSMD staff if an invitation has expired or your production is missing.'
             ],'tip'=>'Activation does not create a second student profile or automatically add you to a show. Some conversations are safeguarded and include guardian protections.'],
            ['id'=>'adult-member-invitation','category'=>'Accounts & families','title'=>'Invite an adult to CTSMD Connect','audience'=>'Account administrators','permission'=>'accounts','where'=>'Website','route'=>'/admin/accounts#member-invitations',
             'intro'=>'Invite an adult as a General Member without a second approval step.',
             'steps'=>[
                'Open Accounts & Access and find Invite an adult.',
                'Enter the adult’s email address and choose Send invitation.',
                'CTSMD emails a branded, private seven-day link. The adult opens it, enters their full name, date of birth and phone number, then creates a password.',
                'Acceptance verifies their email and activates General Membership, including general member Community channels.',
                'Production memberships, staff roles and permissions are added separately by authorized CTSMD staff.',
                'Return to the invitation list to resend an unaccepted link or revoke it.'
             ],'tip'=>'These invitations are for adults only. Students are invited exclusively by their linked parent or legal guardian.'],
            ['id'=>'create-account','category'=>'Accounts & families','title'=>'Create your CTSMD account','audience'=>'Everyone','permission'=>'all','where'=>'Website','route'=>'/join',
             'intro'=>'Set up your sign-in first, then add family members if you need to.',
             'steps'=>[
                'Open Join CTSMD Connect and enter your name, date of birth, email address, phone number and password.',
                'Submit the form, then open the verification email and follow its link.',
                'Sign in and complete household setup. If you do not have children to add, choose Continue without adding children.',
                'Wait for staff to approve general CTSMD membership. Creating a login does not automatically approve membership or add you to a production.'
             ],'tip'=>'If your verification email does not arrive, check spam or junk before requesting another email.'],
            ['id'=>'student-invitation-status','category'=>'Accounts & families','title'=>'Help your student activate their login','audience'=>'Parents & legal guardians','permission'=>'all','where'=>'Website and app','route'=>'/family/manage',
             'intro'=>'Check an invitation, resend an expired link, or give an existing managed child their own login.',
             'steps'=>[
                'Open Home and look in Action Center for a student invitation alert. Pending means your student has not accepted the email yet.',
                'Ask your student to open the invitation email and use the link within 48 hours to create a password.',
                'If the invitation has expired, open the alert, find the student in Manage household, and choose Resend invitation.',
                'If the child has no email and you want them to sign in separately, use Send student invitation in their household card to add an email address.',
                'Once the student activates the existing account, the pending or expired alert disappears. There is no second account approval.',
                'The student receives General Membership, but their production access is still assigned and removed separately by CTSMD staff.'
             ],'tip'=>'A managed child without an email does not need to activate a separate login. Only their linked parent or legal guardian can send the student invitation.'],
            ['id'=>'add-children','category'=>'Accounts & families','title'=>'Add a child to your household','audience'=>'Parents & guardians','permission'=>'all','where'=>'Website','route'=>'/family/manage',
             'intro'=>'You can manage more than one child from the same family account.',
             'steps'=>[
                'Open Family, then Manage household.',
                'Expand Add a child.',
                'Enter the child’s name and date of birth and select your relationship.',
                'Optionally add the child’s email address to invite them to set up their own login.',
                'Choose Add child to my family. Repeat for another child when needed.'
             ],'tip'=>'An added child is not automatically assigned to a show. Production staff handle participation separately.'],
            ['id'=>'review-members','category'=>'Accounts & families','title'=>'Approve or deny a new registration','audience'=>'Account administrators','permission'=>'accounts','where'=>'Website','route'=>'/admin/accounts#pending-membership-reviews',
             'intro'=>'Review new member accounts after email verification.',
             'steps'=>[
                'Open Accounts & Access and find Pending Membership Reviews.',
                'Choose the person awaiting review to open their account.',
                'Check the account details and any information needed for your decision.',
                'Use the Membership Review controls near the top to Approve or Deny. Pending means no decision has been made.',
                'Return to the review queue. A decided application no longer appears as pending for other reviewers.'
             ],'tip'=>'Membership approval and production assignments are separate decisions.'],
            ['id'=>'create-production','category'=>'Productions & casting','title'=>'Create and set up a production','audience'=>'Production administrators','permission'=>'production','where'=>'Website','route'=>'/admin/productions',
             'intro'=>'Start with a show record before building auditions, schedules or casting.',
             'steps'=>[
                'Open Operations, then Productions & seasons.',
                'Create a production and enter the show details requested on the form.',
                'Open the production’s management page to complete its details and dates.',
                'Use the Working Production selector to switch to this show before continuing.',
                'Open Production to access the show’s work areas. Review its visibility and publication settings before sharing anything publicly.'
             ],'tip'=>'A production can be prepared privately. Saving details is not the same as publishing an announcement.'],
            ['id'=>'production-dates','category'=>'Productions & casting','title'=>'Add or update production dates','audience'=>'Production administrators','permission'=>'production','where'=>'Website','route'=>'/admin/productions',
             'intro'=>'Keep audition and performance dates together with the correct production.',
             'steps'=>[
                'Open Productions & seasons and select the show you want to update.',
                'Open its details or dates controls.',
                'Enter the relevant audition and performance date ranges and save.',
                'Open the Production overview to confirm that the dates are shown for the correct show.',
                'Use Schedule when you need individual rehearsals, calls, performances, times and locations.'
             ],'tip'=>'Show date ranges are not a substitute for detailed schedule entries.'],
            ['id'=>'create-audition','category'=>'Productions & casting','title'=>'Create an audition registration','audience'=>'Production staff','permission'=>'production','where'=>'Website','route'=>'/production?view=auditions#workspace-auditions',
             'intro'=>'Invite performers to register for an audition linked to the selected production.',
             'steps'=>[
                'Select the correct Working Production.',
                'Open Production and locate its Auditions section.',
                'Choose Create audition.',
                'Complete the audition details and registration window, then save.',
                'Review the audition’s status and public page. A draft is not automatically available to families.',
                'Open Registrants to review submitted registrations.'
             ],'tip'=>'When registrations are closed, participants may not be able to submit until the window is reopened.'],
            ['id'=>'casting-decisions','category'=>'Productions & casting','title'=>'Make casting decisions and send results','audience'=>'Casting staff','permission'=>'production','where'=>'Website','route'=>'/production/casting',
             'intro'=>'Casting decisions, offers and family communications are distinct steps.',
             'steps'=>[
                'Select the correct Working Production and open Casting.',
                'Review audition registrations or add an existing participant when appropriate.',
                'Open the participant’s casting details and choose the decision and applicable character roles.',
                'Save the decision. Saving alone does not send the result to a family.',
                'Use Send result when you are ready to notify the participant or household.',
                'Review offer responses in Action Center and update casting or roster records as needed.'
             ],'tip'=>'Check the person and roles carefully before sending. Family communications are deliberate actions.'],
            ['id'=>'publish-cast','category'=>'Productions & casting','title'=>'Publish callbacks or a cast list','audience'=>'Casting staff','permission'=>'production','where'=>'Website','route'=>'/production/casting',
             'intro'=>'Keep the private working cast separate from information the public can see.',
             'steps'=>[
                'Finish reviewing decisions and check that the working list is accurate.',
                'Open Release callbacks and cast on the Casting page.',
                'Choose whether you are preparing a callback announcement or the cast list.',
                'Review the names, roles and optional announcement text shown for publication.',
                'Use the publication control when the list is ready for the intended audience.',
                'Check the published result separately. Do not assume changes to the working board automatically republish it.'
             ],'tip'=>'Individual casting result messages and published lists are separate communications.'],
            ['id'=>'attendance','category'=>'Attendance & volunteers','title'=>'Take attendance for a rehearsal or call','audience'=>'Authorized production staff','permission'=>'production','where'=>'Website','route'=>'/attendance',
             'intro'=>'Take attendance against a scheduled production activity.',
             'steps'=>[
                'Choose the correct Working Production.',
                'Open Attendance and select the applicable rehearsal, performance or call.',
                'Open the attendance-taking view for that event.',
                'Mark each participant’s attendance using the options shown.',
                'Save the attendance and confirm that the recorded results appear.',
                'If you need to correct a record, return to the event and update it using the available staff controls.'
             ],'tip'=>'Check that you have the right date and call before marking anyone.'],
            ['id'=>'volunteer','category'=>'Attendance & volunteers','title'=>'Find and sign up for volunteer shifts','audience'=>'Members & volunteers','permission'=>'all','where'=>'Website or app','route'=>'/volunteer-shifts',
             'intro'=>'Shifts may have requirements and sometimes need approval.',
             'steps'=>[
                'Open Volunteer Opportunities.',
                'Choose a shift and read its date, time and requirements.',
                'Sign up or request the shift using the available button.',
                'Check the resulting status to see whether you are confirmed or awaiting approval.',
                'Review Volunteer Readiness if a requirement prevents you from taking the shift.'
             ],'tip'=>'A shift request is not necessarily a confirmed assignment.'],
            ['id'=>'moderate-posts','category'=>'Community & safety','title'=>'Review posts and moderation reports','audience'=>'Community moderators','permission'=>'moderation','where'=>'Website','route'=>'/admin/moderation/queue',
             'intro'=>'Keep discussions appropriate while following CTSMD safety procedures.',
             'steps'=>[
                'Open Operations and the Community moderation queue.',
                'Review pending posts or reports and the surrounding context available to your role.',
                'Choose the appropriate review action shown on the item.',
                'Confirm the decision and check that the item moves out of the pending queue.',
                'Escalate child-safety concerns through CTSMD’s safeguarding procedure rather than relying on ordinary post moderation.'
             ],'tip'=>'Moderation decisions and safety reports are not interchangeable.'],
            ['id'=>'moderation-terms','category'=>'Community & safety','title'=>'Add or update moderation terms','audience'=>'Community moderators','permission'=>'moderation','where'=>'Website','route'=>'/admin/moderation/terms',
             'intro'=>'Maintain the terms that help identify content needing review.',
             'steps'=>[
                'Open Operations, then Moderation Terms.',
                'Review the existing terms to avoid creating duplicates.',
                'Open the add or edit control for a term.',
                'Enter the word or phrase and choose the available moderation settings.',
                'Save and confirm the term appears in the list.',
                'Check the moderation queue for flagged content; terms do not replace human judgment.'
             ],'tip'=>'Use specific terms and periodically review false positives.'],
            ['id'=>'community-channels','category'=>'Community & safety','title'=>'Read and participate in Community channels','audience'=>'Everyone with channel access','permission'=>'all','where'=>'Website or app','route'=>'/channels',
             'intro'=>'Channels keep conversations organized by show and audience.',
             'steps'=>[
                'Open Community and choose Channels.',
                'Choose a channel you can access.',
                'Look for an unread count or New Posts divider to find newer discussion.',
                'Read the channel’s announcements and conversations.',
                'Post or reply if the channel allows your account to participate.'
             ],'tip'=>'Some channels are for announcements only; access depends on membership and production involvement.'],
            ['id'=>'schedule','category'=>'Schedules & forms','title'=>'Add a rehearsal or production call','audience'=>'Schedule managers','permission'=>'schedule','where'=>'Website','route'=>'/production/schedule/new',
             'intro'=>'Use a schedule item for each rehearsal, performance or other call.',
             'steps'=>[
                'Select the correct Working Production.',
                'Open Schedule and choose Add schedule item.',
                'Enter the date, time, location and any applicable family call time.',
                'Choose who the item applies to, then save.',
                'Review the saved schedule item for the correct audience.',
                'If a schedule change needs to be communicated, review its update before publishing the notice.'
             ],'tip'=>'Saving a schedule item and sending a schedule-change announcement are separate actions.'],
            ['id'=>'assigned-forms','category'=>'Schedules & forms','title'=>'Complete an assigned form','audience'=>'Members & families','permission'=>'all','where'=>'Website or app','route'=>'/forms',
             'intro'=>'Find the paperwork currently assigned to you or your household.',
             'steps'=>[
                'Open Forms to see your assigned requirements.',
                'Choose an outstanding form and read the instructions.',
                'Complete the requested fields and submit.',
                'Check whether the form is complete or waiting for staff review.',
                'Return to Forms if you need to see its status later.'
             ],'tip'=>'Submitting a form may not mean staff have approved it yet.'],
            ['id'=>'playbills','category'=>'Tickets & playbills','title'=>'Find a published playbill','audience'=>'Everyone with access','permission'=>'all','where'=>'Website','route'=>'/playbills',
             'intro'=>'Read published playbills or open externally hosted show programs.',
             'steps'=>[
                'Open Playbills from the show or available production resources.',
                'Choose an available published playbill or external program link.',
                'Open the program and return to the show when finished.'
             ],'tip'=>'The unfinished Playbill Builder is restricted; a published program or external link may still be available.'],
        ];
    }
}
