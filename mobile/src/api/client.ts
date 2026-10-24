import {clearToken,readToken} from '../auth/session';
import type {MeResult,MessagesResult,ChannelsResult,TheatreResult,TasksResult,Thread,ChannelFeed} from '../types/ctsmd';
const configured=process.env.EXPO_PUBLIC_CTSMD_API_BASE_URL||'https://ctsmd.me';
if(!/^https:\/\//i.test(configured))throw new Error('CTSMD API must use HTTPS.');
const BASE=configured.replace(/\/$/,'');
export class ApiError extends Error {constructor(message:string,public status:number){super(message)}}
export async function api<T>(path:string,opts:{method?:'GET'|'POST';body?:object;token?:string|null}={}):Promise<T>{
 const token=opts.token===undefined?await readToken():opts.token;
 const res=await fetch(BASE+'/api/mobile/'+path,{method:opts.method||'GET',headers:{'Accept':'application/json',...(token?{'Authorization':'Bearer '+token}:{}),...(opts.body?{'Content-Type':'application/json'}:{})},body:opts.body?JSON.stringify(opts.body):undefined});
 let body:unknown;try{body=await res.json()}catch{throw new ApiError('CTSMD returned an unreadable response.',res.status)}
 if(!res.ok){const message=(body && typeof body==='object' && 'error' in body && body.error!==undefined)?String(body.error):'CTSMD request failed.';if(res.status===401 && opts.token===undefined)await clearToken();throw new ApiError(message,res.status)}
 return body as T;
}
export async function login(email:string,password:string){return api<{token:string;user:MeResult['user']}>('login',{method:'POST',body:{email,password},token:null})}
export const me=()=>api<MeResult>('me');
export const conversations=()=>api<MessagesResult>('messages');
export const channels=()=>api<ChannelsResult>('channels');
export type CommunityFeedPost={id:number;channel_id:number;channel_name:string;production_id:number|null;production_title:string|null;body:string;created_at:string;pinned:number;author_user_id:number;initials:string;author:string};
export const communityFeed=()=>api<{posts:CommunityFeedPost[]}>('community-feed');
export const theatre=()=>api<TheatreResult>('theatre');
export const tasks=()=>api<TasksResult>('daily-tasks');
export const thread=(id:number)=>api<Thread>('message-thread?id='+id);
export const channel=(id:number)=>api<ChannelFeed>('channel?id='+id);
export type CommunityReply={id:number;post_id:number;body:string;created_at:string;author_user_id:number;initials:string;author:string};
export const channelReplies=(post_id:number)=>api<{replies:CommunityReply[]}>('channel-replies?post_id='+encodeURIComponent(String(post_id)));
export const createChannelReply=(post_id:number,body:string)=>api<{ok:boolean;status:string;reply_id:number}>('channel-replies',{method:'POST',body:{post_id,body}});
export const createChannelPost=(channel_id:number,body:string)=>api<{ok:boolean;post_id:number;status:string}>('channel-post',{method:'POST',body:{channel_id,body}});
export const sendMessage=(id:number,body:string)=>api<unknown>('message-send',{method:'POST',body:{conversation_id:id,body}});
export type FormDetail={id:number;status:string;due_at:string|null;title:string;instructions:string|null;completion_mode:string;review_required:number;submission_status:string|null;reviewer_note:string|null};
export const formDetail=(id:number)=>api<{form:FormDetail}>('form-detail?id='+encodeURIComponent(String(id)));
export const submitForm=(input:{assignment_id:number;acknowledgment:boolean;typed_signature?:string;response_text?:string})=>api<{ok:boolean}>('form-submit',{method:'POST',body:input});
export const signupVolunteer=(shift_id:number)=>api<{ok:boolean}>('volunteer-signup',{method:'POST',body:{shift_id}});
export const cancelVolunteer=(shift_id:number)=>api<{ok:boolean}>('volunteer-cancel',{method:'POST',body:{shift_id}});
export const approvalVolunteer=(input:{action:'request';shift_id:number;note:string}|{action:'withdraw';request_id:number})=>api<{ok:boolean}>('volunteer-approval',{method:'POST',body:input});
export type Recipient={id:number;name:string;role:string;selectable:boolean};
export const recipients=()=>api<{recipients:Recipient[]}>('message-recipients');
export const createConversation=(recipient_id:number,body:string)=>api<{conversation_id:number}>('message-create',{method:'POST',body:{recipient_id,body}});
