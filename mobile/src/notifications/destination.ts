/** Allowlisted navigation only. Notification data never grants resource access. */
export function notificationDestination(data:Record<string,unknown>):string{
 const path=typeof data.action_path==='string'?data.action_path:typeof data.url==='string'?data.url:'';
 if(!path.startsWith('/')||path.startsWith('//'))return '/notifications';
 const [route,query='']=path.split('?');
 const params=new URLSearchParams(query.split('#')[0]);
 const id=params.get('id');
 const valid=id&&/^[1-9]\d{0,9}$/.test(id);
 if(valid&&route==='/messages/thread')return '/messages/'+id;
 if(valid&&route==='/channels/view')return '/community/'+id;
 if(valid&&route==='/forms/view')return '/forms/'+id;
 if(valid&&route==='/calendar/event')return '/events/'+id;
 if(route==='/calendar')return '/calendar';
 if(route==='/notifications')return params.has('offer')?'/action-center':'/notifications';
 return '/notifications';
}
