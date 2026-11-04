/** Format the wall-clock values provided by CTSMD without shifting their timezone. */
export function formatLocalDate(value:string, options:Intl.DateTimeFormatOptions):string {
 const date=new Date(value.replace(' ','T'));
 return Number.isFinite(date.getTime())?date.toLocaleString(undefined,options):value;
}
