import {Linking} from 'react-native';

/** Audited browser handoffs. These do not imply native authorization or shared login cookies. */
export const webRoutes={
 tickets:'/tickets',
 publicProductions:'/productions',
 registration:'/register',
 productionWorkspace:'/production',
 productionCalendar:'/calendar',
 productionDay:'/production/day',
 attendance:'/attendance',
 productionPeople:'/production/people',
 productionTasks:'/production/tasks',
 productionReadiness:'/production/readiness',
 productionResources:'/resources',
 playbills:'/playbills',
 volunteerTraining:'/volunteer/training',
 volunteerHistory:'/volunteer/history',
 familyManagement:'/family/manage',
 household:'/family-hub',
 performerProfile:'/student-profile',
 theatreHistory:'/theatre-history',
 volunteerReadiness:'/volunteer-readiness',
 adminProductions:'/admin/productions',
 adminAccounts:'/admin/accounts',
 adminOperations:'/admin/operations',
 commerce:'/admin/commerce',
} as const;

const base=(process.env.EXPO_PUBLIC_CTSMD_API_BASE_URL||'https://ctsmd.me').replace(/\/$/,'');
if(!/^https:\/\/[^/?#]+(?::\d+)?(?:\/[^?#]*)?$/.test(base))throw new Error('CTSMD website requires a configured HTTPS origin.');
export type WebRoute=keyof typeof webRoutes;
export function websiteUrl(route:WebRoute):string{return base+webRoutes[route];}
export async function openWebsite(route:WebRoute):Promise<void>{
 const url=websiteUrl(route);
 if(!await Linking.canOpenURL(url))throw new Error('The CTSMD website cannot be opened on this device.');
 await Linking.openURL(url);
}
