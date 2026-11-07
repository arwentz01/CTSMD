/** Release notes shown in the native app.
 * Add an entry when preparing each store release and mark it published
 * only after the build is available to members. Keep newest first.
 */
export type ReleaseNoteSection={title:string;items:string[]};
export type ReleaseNote={
 releaseNumber:number;
 version:string;
 title:string;
 publishedOn?:string;
 status:'published'|'upcoming';
 introduction:string;
 sections:ReleaseNoteSection[];
};

export const releaseNotes:ReleaseNote[]=[
 {
  releaseNumber:2,
  version:'0.2.0',
  title:'More control, clearer updates',
  status:'upcoming',
  introduction:'Here is what we have been working on to make CTSMD Connect easier to use and help our theatre community stay connected.',
  sections:[
   {title:'Messages',items:[
    'Search your conversations, including messages and archived threads.',
    'Search for a word or person inside a conversation.',
    'Archive a conversation to tidy your inbox without removing it for anyone else.',
    'Restore an archived conversation whenever you need it. New messages bring archived conversations back to your inbox.',
    'Remove a message from your own view, or remove an unread message you sent from everyone’s view. Messages are retained securely for safety and recordkeeping.',
    'Report an individual message when something does not feel right.'
   ]},
   {title:'Your home and Action Center',items:[
    'Find forms, casting offers, production tasks, and other things that need your attention together on Home.',
    'Open the Action Center to take care of outstanding items in one place.',
    'Authorized staff can see and acknowledge Community moderation concerns from the app.',
    'Safeguarding team members can review reported private messages without exposing those reports to everyone.',
    'Acknowledged moderation concerns remain in the history so patterns can be recognized over time.'
   ]},
   {title:'Sign-in and accounts',items:[
    'A refreshed sign-in screen that matches the CTSMD Connect look and feel.',
    'A password visibility button to make signing in easier.',
    'A more comfortable sign-in layout when the on-screen keyboard is open.',
    'Quick links to reset your password or create a parent or guardian account.'
   ]},
   {title:'Notifications',items:[
    'A first-time invitation to enable device notifications for messages and theatre updates.',
    'The option to turn on device alerts later in Settings.',
    'Clearer guidance when an app build cannot enable notifications yet.'
   ]},
   {title:'Behind the scenes',items:[
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
   {title:'Getting started',items:[
    'Sign in to stay connected with your CTSMD account.',
    'Find your theatre activities, conversations and important updates in one app.',
    'Explore your productions, schedules, Community and Messages from the main navigation.'
   ]}
  ]
 }
];

export function releaseStatusLabel(note:ReleaseNote):string{
 return note.status==='published'?'Released'+(note.publishedOn?' · '+note.publishedOn:''):'Preparing for release';
}
