<?php

declare(strict_types=1);

return [
    'user' => ['name' => 'Jamie Carter', 'role' => 'Parent + Volunteer', 'initials' => 'JC'],
    'announcements' => [
        ['title' => 'Tech week schedule updated', 'meta' => '15 min ago · Current Production', 'body' => 'Thursday call time moved to 5:30 PM. Please review the updated family notes before arrival.', 'tone' => 'urgent'],
        ['title' => 'Costume fitting reminders', 'meta' => 'Today · Costumes', 'body' => 'Students with remaining fittings should arrive 20 minutes before rehearsal this Saturday.', 'tone' => 'info'],
    ],
    'schedule' => [
        ['time' => '5:30 PM', 'title' => 'Full Cast Rehearsal', 'detail' => 'Main Stage · Family call 5:15 PM', 'tag' => 'Today'],
        ['time' => '6:45 PM', 'title' => 'Parent Volunteer Orientation', 'detail' => 'Studio B · 30 minutes', 'tag' => 'Today'],
        ['time' => '10:00 AM', 'title' => 'Set Build Day', 'detail' => 'Scene Shop · Adults only', 'tag' => 'Sat'],
    ],
    'channels' => ['Announcements','General','Parent Questions','Current Production','Cast Updates','Tech and Crew','Costumes','Volunteer Opportunities','Resources'],
    'channel_posts' => [
        ['author' => 'Maya · Production Manager', 'time' => '4:12 PM', 'text' => 'Reminder: Thursday rehearsal begins 30 minutes earlier. Updated call times are now in the schedule.', 'pinned' => true, 'reactions' => '👍 18   ❤️ 7'],
        ['author' => 'Jordan · Director', 'time' => '3:44 PM', 'text' => 'Great work on Act II last night. Please review the choreography video before Saturday.', 'pinned' => false, 'reactions' => '🎭 12   👏 9'],
        ['author' => 'Alex · Parent', 'time' => '2:08 PM', 'text' => 'Will the costume team need students to bring their show shoes Saturday?', 'pinned' => false, 'reactions' => '👍 3'],
    ],
    'volunteer_stats' => [
        ['label' => 'Open shifts', 'value' => '14', 'note' => '5 this week'],
        ['label' => 'Background checks', 'value' => '6', 'note' => 'pending review'],
        ['label' => 'Training incomplete', 'value' => '9', 'note' => '3 due soon'],
        ['label' => 'Ready volunteers', 'value' => '42', 'note' => 'eligible now'],
    ],
    'shifts' => [
        ['title' => 'Front of House', 'when' => 'Fri · 5:30–9:00 PM', 'location' => 'Lobby', 'slots' => '2 of 4 open', 'status' => 'eligible', 'requirements' => 'Facility orientation'],
        ['title' => 'Dressing Room Monitor', 'when' => 'Sat · 1:00–5:00 PM', 'location' => 'Backstage', 'slots' => '1 of 2 open', 'status' => 'locked', 'requirements' => 'Background check + child safety training'],
        ['title' => 'Set Build Day', 'when' => 'Sat · 10:00 AM–2:00 PM', 'location' => 'Scene Shop', 'slots' => '4 of 8 open', 'status' => 'eligible', 'requirements' => 'Adults only'],
        ['title' => 'Concessions', 'when' => 'Sun · 12:30–4:30 PM', 'location' => 'Lobby', 'slots' => '3 of 5 open', 'status' => 'eligible', 'requirements' => 'No special requirements'],
    ],
    'forms' => [
        ['title' => 'Parent Handbook Acknowledgment', 'status' => 'Completed', 'due' => 'Aug 1'],
        ['title' => 'Media / Photo Release', 'status' => 'Due soon', 'due' => 'Aug 10'],
        ['title' => 'Emergency Information', 'status' => 'Missing', 'due' => 'Aug 8'],
        ['title' => 'Volunteer Facility Education', 'status' => 'Requires review', 'due' => 'Aug 12'],
    ],
    'playbills' => [
        ['title' => 'Matilda Jr.', 'season' => 'Summer 2026', 'status' => 'Current'],
        ['title' => 'The Lion, the Witch and the Wardrobe', 'season' => 'Spring 2026', 'status' => 'Archived'],
        ['title' => 'Frozen Jr.', 'season' => 'Winter 2025', 'status' => 'Archived'],
    ],
];
