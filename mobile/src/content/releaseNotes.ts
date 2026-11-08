/** Release notes shown in the native app.
 * Add an entry for each store release and indicate whether it is in closed
 * testing or generally available. Keep newest first.
 */
export type ReleaseNoteSection={title:string;summary:string;items:string[]};
export type ReleaseNote={
 releaseNumber:number;
 version:string;
 title:string;
 publishedOn?:string;
 status:'published'|'closed_testing'|'upcoming';
 introduction:string;
 sections:ReleaseNoteSection[];
};

/** Features built for an upcoming app update, not yet assigned a store version.
 * Move items into a numbered release only when that build is ready to ship.
 */
export const nextReleasePreview={
 title:'Coming in the next app update',
 introduction:'We are working on a few improvements to make important updates easier to find and act on. These are not available in every installed app yet.',
 sections:[
  {title:'Student invitations for families',summary:'Parents can tell when a student still needs to activate their own login.',items:[
   'Home and Action Center highlight student invitations awaiting acceptance or expired.',
   'Open Family to resend an expired invitation, or add an email to a managed child who wants their own login.',
   'The student must accept the email invitation and choose a password within 48 hours.',
   'Once activated, the alert clears automatically. Student activation grants general membership only; production access remains separately assigned by staff.'
  ]},
  {title:'A clearer first-login experience',summary:'Your next steps appear on Home based on what is actually ready.',items:[
   'A Getting Started checklist helps new staff, parents and students find their next step.',
   'Completed steps are based on real account setup and production access, not on whether someone tapped a button.',
   'Items awaiting CTSMD approval or production assignment are clearly marked, so you know when staff need to act.',
   'Relevant actions link to the correct account, household, production or Help page.',
   'The checklist steps out of the way once your setup is ready.',
   'You can hide the checklist for seven days and bring it back from Home whenever you want.'
  ]},
  {title:'Help for getting started',summary:'Clear, step-by-step guidance for families, students and staff.',items:[
   'A Help & Guides link from the app opens the CTSMD website with searchable, step-by-step instructions.',
   'New getting-started guides explain the first steps for staff, parents and students, including account verification, student invitations and finding your production.',
   'Guides explain why creating an account, receiving membership approval and joining a production are separate steps.',
   'Help topics are shown according to your responsibilities, with direct links to the relevant web pages.'
  ]},
  {title:'Casting responses for production teams',summary:'See accepted and declined casting offers without hunting for updates.',items:[
   'Authorized production staff can see new casting responses under Needs Your Attention.',
   'Open Action Center to review and acknowledge those responses. Once acknowledged, they no longer appear as outstanding for that person.'
  ]},
  {title:'New member applications',summary:'A shared list of new registrations waiting for CTSMD approval.',items:[
   'Staff with account review permission will see a pending registration count on Home and in Action Center.',
   'Open an application directly from the alert to review membership access.',
   'When someone is approved or declined, the pending alert clears for everyone who can review applications.'
  ]},
  {title:'Easier everyday navigation',summary:'Less clutter in conversations and clearer activity in Community.',items:[
   'A more compact Messages inbox, faster search, and easier ways to start a conversation.',
   'Clearer unread badges on Community channels, plus a New Posts divider and temporary highlights when you open a channel.',
   'Eligible Android test builds can show when a newer app update is available.'
  ]}
 ] as ReleaseNoteSection[]
};

export const releaseNotes:ReleaseNote[]=[
 {
  releaseNumber:2,
  version:'0.2.0',
  title:'More control, clearer updates',
  status:'closed_testing',
  introduction:'Here is what we have been working on to make CTSMD Connect easier to use and help our theatre community stay connected.',
  sections:[
   {title:'Messages',summary:'More ways to find, organize, and manage your conversations.',items:[
    'Search your conversations, including messages and archived threads.',
    'Search for a word or person inside a conversation.',
    'Archive a conversation to tidy your inbox without removing it for anyone else.',
    'Restore an archived conversation whenever you need it. New messages bring archived conversations back to your inbox.',
    'Remove a message from your own view, or remove an unread message you sent from everyone’s view. Messages are retained securely for safety and recordkeeping.',
    'Report an individual message when something does not feel right.'
   ]},
   {title:'Your home and Action Center',summary:'See what needs attention and handle important tasks in one place.',items:[
    'Find forms, casting offers, production tasks, and other things that need your attention together on Home.',
    'Open the Action Center to take care of outstanding items in one place.',
    'Authorized staff can see and acknowledge Community moderation concerns from the app.',
    'Safeguarding team members can review reported private messages without exposing those reports to everyone.',
    'Acknowledged moderation concerns remain in the history so patterns can be recognized over time.'
   ]},
   {title:'Sign-in and accounts',summary:'An easier sign-in experience with better account access.',items:[
    'A refreshed sign-in screen that matches the CTSMD Connect look and feel.',
    'A password visibility button to make signing in easier.',
    'A more comfortable sign-in layout when the on-screen keyboard is open.',
    'Quick links to reset your password or create a parent or guardian account.'
   ]},
   {title:'Notifications',summary:'More control over alerts about theatre activity.',items:[
    'A first-time invitation to enable device notifications for messages and theatre updates.',
    'The option to turn on device alerts later in Settings.',
    'Clearer guidance when an app build cannot enable notifications yet.'
   ]},
   {title:'Behind the scenes',summary:'Improved reliability, navigation, and privacy protections.',items:[
    'Improved reliability and navigation across the app.',
    'Better safeguards for private conversations and moderation records.',
    'Clearer information when a feature is temporarily unavailable.'
   ]}
  ]
 },
 {
  releaseNumber:1,
  version:'0.1.0',
  title:'Welcome to CTSMD Connect',
  status:'published',
  introduction:'Our first app release brought your CTSMD theatre community together in one place.',
  sections:[
   {title:'Getting started',summary:'The essentials for staying connected with your theatre community.',items:[
    'Sign in to stay connected with your CTSMD account.',
    'Find your theatre activities, conversations and important updates in one app.',
    'Explore your productions, schedules, Community and Messages from the main navigation.'
   ]}
  ]
 }
];

export function releaseStatusLabel(note:ReleaseNote):string{
 if(note.status==='closed_testing')return 'Closed testing';
 return note.status==='published'?'Released'+(note.publishedOn?' · '+note.publishedOn:''):'Coming soon';
}
