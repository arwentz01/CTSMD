/** Display only. The server authorizes every workflow independently. */
export function participantIdentityLabel(classification?:'adult'|'student'|'unclassified'):string {
 return classification==='adult'?'Adult participant':classification==='student'?'Student':'General member';
}
