import {TouchTarget as Pressable} from '../../src/components/TouchTarget';
import React,{useCallback} from 'react';
import {router} from 'expo-router';
import {Ionicons} from '@expo/vector-icons';
import {Image,StyleSheet,Text,View} from 'react-native';
import {Screen} from '../../src/components/Screen';
import {ProductionArtwork} from '../../src/components/ProductionArtwork';
import {LoadState,useScreenData} from '../../src/components/LoadState';
import {useAuth} from '../../src/auth/AuthProvider';
import {theatre,tasks,conversations,notifications,moderationAlerts,ApiError} from '../../src/api/client';
import type {ModerationAlerts} from '../../src/api/client';
import {colors} from '../../src/theme/colors';
import type {TheatreEvent} from '../../src/types/ctsmd';

const toTime=(date:string)=>new Date(date.replace(' ','T')).getTime();
const readable=(date:string)=>{const value=new Date(date.replace(' ','T'));return Number.isFinite(value.getTime())?value.toLocaleString(undefined,{weekday:'short',month:'short',day:'numeric',hour:'numeric',minute:'2-digit'}):date;};
function EventRow({event,featured=false,hasArtwork=false}:{event:TheatreEvent;featured?:boolean;hasArtwork?:boolean}){
  return <Pressable accessibilityRole="button" accessibilityLabel={'View theatre schedule for '+event.title} onPress={()=>router.push({pathname:'/events/[id]',params:{id:String(event.id)}})} style={featured?styles.hero:styles.event}>
    {featured&&<ProductionArtwork productionId={event.production_id} enabled={hasArtwork}/>}
    <View style={featured?styles.heroContent:styles.eventContent}>
      {featured&&<View style={styles.heroHeader}><Ionicons name="sparkles-outline" size={18} color={colors.gold}/><Text style={styles.heroEyebrow}>UP NEXT</Text></View>}
      <Text style={featured?styles.heroTitle:styles.rowTitle} numberOfLines={2}>{event.title}</Text>
      <Text style={featured?styles.heroText:styles.sub} numberOfLines={1}>{event.production_title}</Text>
      <Text style={featured?styles.heroText:styles.sub}>{readable(event.starts_at)}</Text>
      {!!event.location&&<Text style={featured?styles.heroText:styles.sub} numberOfLines={1}>{event.location}</Text>}
    </View>
    {featured&&<View style={styles.heroArrow}><Ionicons name="arrow-forward" size={19} color={colors.stage}/></View>}
  </Pressable>;
}
function Shortcut({icon,title,onPress}:{icon:React.ComponentProps<typeof Ionicons>['name'];title:string;onPress:()=>void}){
  return <Pressable accessibilityRole="button" accessibilityLabel={title} onPress={onPress} style={styles.shortcut}><Ionicons name={icon} size={22} color={colors.primary}/><Text style={styles.shortcutTitle}>{title}</Text></Pressable>;
}
export default function Home(){
  const {user}=useAuth();
  if(user?.caregiver_only)return <Screen title="Welcome back">
    <Text style={styles.intro}>Family logistics and messages, in one place.</Text>
    <Pressable accessibilityRole="button" onPress={()=>router.push('/caregiver-schedule')} style={styles.panel}>
      <View style={{flexDirection:'row',alignItems:'center',gap:12}}>
       <Ionicons name="calendar-outline" size={25} color={colors.primary}/>
       <View style={{flex:1,gap:4}}><Text style={styles.sectionTitle}>Caregiver schedule</Text><Text style={styles.sub}>Upcoming authorized pickup and rehearsal calls</Text></View>
       <Ionicons name="chevron-forward" size={20} color={colors.primary}/>
      </View>
    </Pressable>
    <Pressable accessibilityRole="button" onPress={()=>router.push('/family')} style={styles.panel}>
      <Text style={styles.rowTitle}>My family connections</Text><Text style={styles.sub}>View active caregiver relationships</Text>
    </Pressable>
    <Pressable accessibilityRole="button" onPress={()=>router.push('/(tabs)/messages')} style={styles.panel}>
      <Text style={styles.rowTitle}>Messages</Text><Text style={styles.sub}>Contact people available to your account</Text>
    </Pressable>
  </Screen>;
  return <MemberHome/>;
}
function MemberHome(){
 const {user}=useAuth();
 const load=useCallback(async()=>{
    const [t,a,m,n,mod]=await Promise.all([theatre(),tasks(),conversations(),notifications(),moderationAlerts().then(value=>({value,error:null as string|null})).catch(e=>({value:null as ModerationAlerts|null,error:e instanceof ApiError&&e.status===404?'The moderation service has not been deployed to CTSMD.me yet.':e instanceof Error?e.message:'Moderation alerts unavailable.'}))]);
    return {t,a,m,n,mod};
  },[]);
  const {data,error,loading,retry}=useScreenData(load);
  const now=Date.now();
  const events=(data?.t.events||[]).filter(e=>Number.isFinite(toTime(e.starts_at))&&toTime(e.starts_at)>=now).sort((a,b)=>toTime(a.starts_at)-toTime(b.starts_at)).slice(0,3);
  const openForms=(data?.a.forms||[]).filter(f=>f.status!=='completed'&&f.submission_status!=='approved');
  const unread=(data?.m.conversations||[]).reduce((total,c)=>total+Math.max(0,c.unread_count||0),0);
  const canReviewCasting=!!user?.roles?.some(role=>role==='administrator'||role==='production_staff')||!!user?.permissions?.includes('production.manage');
  const castingResponses=canReviewCasting?(data?.n.notifications||[]).filter(item=>item.source_type==='casting_offer_response'&&!item.read_at):[];
  const moderationCount=data?.mod.value?.pending_count??0;
  const actionCount=(data?.a.production_todos.length??0)+(data?.a.casting_offers.length??0)+openForms.length+moderationCount+castingResponses.length;
  const moderationAccess=!!user?.permissions?.some(permission=>permission==='community.moderate'||permission==='safeguarding.manage')||!!user?.roles?.includes('administrator');
  const moderationError=data?.mod.error;
  return <Screen compact title={'Hello'+(user?.first_name?', '+user.first_name:'')+'!'}>
    <Text style={styles.intro}>Your theatre community, all in one place.</Text>
    <LoadState loading={loading} error={error} onRetry={retry}/>
    {data&&<>
      <View style={styles.heading}><Text style={styles.sectionTitle}>Coming Up</Text><Pressable accessibilityRole="button" onPress={()=>router.push('/calendar')}><Text style={styles.link}>View calendar  →</Text></Pressable></View>
      {events.length?<><EventRow event={events[0]} featured hasArtwork={!!data.t.productions.find(p=>p.id===events[0].production_id)?.has_artwork}/>{events.slice(1).map(event=><EventRow key={event.id} event={event}/>)}</>:<View style={styles.panel}><Text style={styles.sub}>No upcoming events on your visible theatre calendar.</Text></View>}
      <View style={styles.panel}>
        <Pressable accessibilityRole="button" accessibilityLabel="Open Action Center and review items needing attention" onPress={()=>router.push('/action-center')} style={styles.heading}>
          <View style={{flex:1,flexDirection:'row',alignItems:'center',gap:8}}>
            <Text style={styles.sectionTitle}>Needs Your Attention</Text>
            {actionCount>0&&<View style={styles.attentionBadge}><Text style={styles.attentionBadgeText}>{actionCount}</Text></View>}
          </View>
          <Ionicons name="chevron-forward" size={22} color={colors.primary}/>
        </Pressable>
        {moderationCount>0&&<Pressable accessibilityRole="button" onPress={()=>router.push('/action-center')} style={styles.task}>
          <Ionicons name="shield-checkmark-outline" color={colors.primary} size={23}/>
          <View style={{flex:1}}><Text style={styles.rowTitle}>Moderation concerns</Text><Text style={styles.sub}>{moderationCount} awaiting acknowledgement · Open Action Center</Text></View>
          <Ionicons name="chevron-forward" color={colors.muted} size={20}/>
        </Pressable>}
        {openForms.slice(0,3).map(f=><Pressable key={f.id} accessibilityRole="button" onPress={()=>router.push({pathname:'/forms/[id]',params:{id:String(f.id)}})} style={styles.task}>
          <Ionicons name="document-text-outline" color={colors.primary} size={21}/>
          <View style={{flex:1}}><Text style={styles.rowTitle}>{f.title}</Text><Text style={styles.sub}>Assigned form · Action required</Text></View>
          <Ionicons name="chevron-forward" color={colors.muted} size={20}/>
        </Pressable>)}
        {castingResponses.map(item=><Pressable key={'casting-response-'+item.id} accessibilityRole="button" accessibilityLabel={'Review casting response: '+item.title} onPress={()=>router.push('/notifications')} style={styles.task}>
          <Ionicons name="star-outline" color={colors.primary} size={21}/>
          <View style={{flex:1}}><Text style={styles.rowTitle} numberOfLines={2}>{item.title}</Text><Text style={styles.sub} numberOfLines={2}>{item.body}</Text></View>
          <Ionicons name="chevron-forward" color={colors.muted} size={20}/>
        </Pressable>)}
        {data.a.casting_offers.length>0&&<Pressable accessibilityRole="button" onPress={()=>router.push('/action-center')} style={styles.task}>
          <Ionicons name="star-outline" color={colors.primary} size={21}/>
          <View style={{flex:1}}><Text style={styles.rowTitle}>Casting offers</Text><Text style={styles.sub}>{data.a.casting_offers.length} awaiting your response</Text></View>
          <Ionicons name="chevron-forward" color={colors.muted} size={20}/>
        </Pressable>}
        {data.a.production_todos.length>0&&<Pressable accessibilityRole="button" onPress={()=>router.push('/action-center')} style={styles.task}>
          <Ionicons name="checkbox-outline" color={colors.primary} size={21}/>
          <View style={{flex:1}}><Text style={styles.rowTitle}>Production tasks</Text><Text style={styles.sub}>{data.a.production_todos.length} outstanding</Text></View>
          <Ionicons name="chevron-forward" color={colors.muted} size={20}/>
        </Pressable>}
        {!!moderationError&&moderationAccess&&<View style={styles.issueRow}>
          <Ionicons name="alert-circle-outline" size={20} color={colors.danger}/>
          <Text style={styles.issueText}>Moderation alerts couldn't load: {moderationError}. Open Action Center to retry.</Text>
        </View>}
        {actionCount===0&&!(moderationError&&moderationAccess)&&<Text style={styles.sub}>You're all caught up.</Text>}
        <Pressable accessibilityRole="button" onPress={()=>router.push('/action-center')} style={styles.actionFooter}><Text style={styles.link}>Open Action Center</Text><Ionicons name="arrow-forward" size={17} color={colors.primary}/></Pressable>
      </View>
      <Pressable accessibilityRole="button" accessibilityLabel="View unread notifications and notification history" onPress={()=>router.push('/notifications')} style={styles.panel}><View style={{flexDirection:'row',alignItems:'center',gap:12}}><Ionicons name="notifications-outline" size={24} color={colors.primary}/><View style={{flex:1}}><Text style={styles.sectionTitle}>Recent updates</Text><Text style={styles.sub}>Unread notifications and collapsible history</Text></View><Ionicons name="chevron-forward" size={23} color={colors.primary}/></View></Pressable>
      <Text style={styles.sectionTitle}>Quick Actions</Text>
      <View style={styles.shortcuts}>
        <Shortcut icon="chatbubble-outline" title={'Messages'+(unread?' ('+unread+')':'')} onPress={()=>router.push('/(tabs)/messages')}/>
        <Shortcut icon="document-text-outline" title="Forms" onPress={()=>router.push('/(tabs)/forms')}/>
        <Shortcut icon="people-outline" title="My Theatre" onPress={()=>router.push('/(tabs)/theatre')}/>
        {data.a.volunteer_enabled&&<Shortcut icon="heart-outline" title="Volunteer" onPress={()=>router.push('/(tabs)/volunteer')}/>}
      </View>
    </>}
  </Screen>;
}
const styles=StyleSheet.create({
 attentionBadge:{backgroundColor:colors.primary,borderRadius:12,minWidth:24,height:24,alignItems:'center',justifyContent:'center',paddingHorizontal:6},attentionBadgeText:{color:'#fff',fontWeight:'800',fontSize:12},issueRow:{flexDirection:'row',gap:8,paddingVertical:8,alignItems:'center'},issueText:{color:colors.danger,fontSize:13,flex:1},actionFooter:{flexDirection:'row',alignItems:'center',gap:7,paddingVertical:8,marginTop:4},
 offerCount:{color:colors.primary,fontWeight:'800'},offerRow:{flexDirection:'row',alignItems:'center',gap:13,paddingVertical:12,borderTopWidth:1,borderTopColor:colors.border},offerProduction:{fontSize:17,fontWeight:'700',color:colors.navy},offerRole:{fontSize:16,fontWeight:'800',color:colors.primary,marginVertical:3},
  intro:{fontSize:13,color:colors.muted,marginTop:-2,marginBottom:6},
  heading:{flexDirection:'row',justifyContent:'space-between',alignItems:'center'},
  sectionTitle:{fontSize:21,fontWeight:'700',color:colors.navy},
  link:{color:colors.primary,fontSize:13,fontWeight:'700'},
  hero:{position:'relative',backgroundColor:colors.stage,borderRadius:17,borderWidth:1,borderColor:colors.gold,borderBottomWidth:3,minHeight:140,overflow:'hidden'},
  heroContent:{paddingVertical:13,paddingLeft:16,paddingRight:67,gap:5,justifyContent:'center'},eventContent:{gap:3},
  heroHeader:{flexDirection:'row',alignItems:'center',gap:8},heroArrow:{position:'absolute',right:13,bottom:13,width:31,height:31,borderRadius:16,backgroundColor:colors.gold,alignItems:'center',justifyContent:'center'},
  heroEyebrow:{fontSize:12,fontWeight:'800',color:colors.gold,letterSpacing:2},
  heroTitle:{fontSize:23,fontWeight:'700',color:'#fff'},
  heroText:{fontSize:12,color:'#fff4e6'},
  event:{backgroundColor:colors.card,padding:13,borderRadius:14,borderWidth:1,borderColor:colors.border,gap:3},
  rowTitle:{fontSize:17,fontWeight:'700',color:colors.text},
  sub:{color:colors.muted,fontSize:12},
  panel:{backgroundColor:colors.card,borderRadius:14,borderWidth:1,borderColor:colors.border,padding:11,gap:6},
  task:{flexDirection:'row',alignItems:'center',gap:12,paddingVertical:5},
  shortcuts:{flexDirection:'row',flexWrap:'wrap',gap:9},
  shortcut:{width:'47%',backgroundColor:colors.card,borderWidth:1,borderColor:colors.border,borderRadius:12,padding:13,gap:8},
  shortcutTitle:{fontSize:13,fontWeight:'700',color:colors.text}
});
