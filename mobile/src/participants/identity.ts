/** Display only. The server authorizes every workflow independently. */
export function authorizationRoleLabels(roles:string[]=[]):string {
 const labels:Record<string,string>={adult_performer:'Adult Performer',student:'Student',member:'Member',volunteer:'Volunteer',production_staff:'Production Staff',administrator:'Administrator',moderator:'Moderator',safeguarding:'Safeguarding'};
 return roles.length?roles.map(role=>labels[role]||role.replace(/_/g,' ')).join(', '):'No roles assigned';
}
