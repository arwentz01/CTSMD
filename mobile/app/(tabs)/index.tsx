import React,{useCallback} from 'react';
import {router} from 'expo-router';
import {Ionicons} from '@expo/vector-icons';
import {Image,Pressable,StyleSheet,Text,View} from 'react-native';
import {Screen} from '../../src/components/Screen';
import {ProductionArtwork} from '../../src/components/ProductionArtwork';
import {LoadState,useScreenData} from '../../src/components/LoadState';
import {useAuth} from '../../src/auth/AuthProvider';
import {theatre,tasks,conversations} from '../../src/api/client';
import {colors} from '../../src/theme/colors';
import type {TheatreEvent} from '../../src/types/ctsmd';

const toTime=(date:string)=>new Date(date.replace(' ','T')).getTime();
const readable=(date:string)=>{const value=new Date(date.replace(' ','T'));return Number.isFinite(value.getTime())?value.toLocaleString(undefined,{weekday:'short',month:'short',day:'numeric',hour:'numeric',minute:'2-digit'}):date;};
function EventRow({event,featured=false,hasArtwork=false}:{event:TheatreEvent;featured?:boolean;hasArtwork?:boolean}){
  return <Pressable accessibilityRole="button" accessibilityLabel={'View theatre schedule for '+event.title} onPress={()=>router.push('/(tabs)/theatre')} style={featured?styles.hero:styles.event}>
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
  const load=useCallback(async()=>{const [t,a,m]=await Promise.all([theatre(),tasks(),conversations()]);return {t,a,m}},[]);
  const {data,error,loading}=useScreenData(load);
  const now=Date.now();
  const events=(data?.t.events||[]).filter(e=>Number.isFinite(toTime(e.starts_at))&&toTime(e.starts_at)>=now).sort((a,b)=>toTime(a.starts_at)-toTime(b.starts_at)).slice(0,3);
  const openForms=(data?.a.forms||[]).filter(f=>f.status!=='completed'&&f.submission_status!=='approved');
  const unread=(data?.m.conversations||[]).reduce((total,c)=>total+Math.max(0,c.unread_count||0),0);
  return <Screen title={'Hello'+(user?.first_name?', '+user.first_name:'')+'!'}>
    <Text style={styles.intro}>Your theatre community, all in one place.</Text>
    <LoadState loading={loading} error={error}/>
    {data&&<>
      <View style={styles.heading}><Text style={styles.sectionTitle}>Coming Up</Text><Pressable accessibilityRole="button" onPress={()=>router.push('/(tabs)/theatre')}><Text style={styles.link}>View calendar  →</Text></Pressable></View>
      {events.length?<><EventRow event={events[0]} featured hasArtwork={!!data.t.productions.find(p=>p.id===events[0].production_id)?.has_artwork}/>{events.slice(1).map(event=><EventRow key={event.id} event={event}/>)}</>:<View style={styles.panel}><Text style={styles.sub}>No upcoming events on your visible theatre calendar.</Text></View>}
      {openForms.length>0&&<View style={styles.panel}><Text style={styles.sectionTitle}>Needs Your Attention</Text>{openForms.slice(0,3).map(f=><Pressable key={f.id} accessibilityRole="button" onPress={()=>router.push({pathname:'/forms/[id]',params:{id:String(f.id)}})} style={styles.task}><Ionicons name="document-text-outline" color={colors.primary} size={21}/><View style={{flex:1}}><Text style={styles.rowTitle}>{f.title}</Text><Text style={styles.sub}>Assigned form · Action required</Text></View><Ionicons name="chevron-forward" color={colors.muted}/></Pressable>)}{openForms.length>3&&<Pressable onPress={()=>router.push('/(tabs)/forms')} accessibilityRole="button"><Text style={styles.link}>View all {openForms.length} forms →</Text></Pressable>}</View>}
      <Pressable accessibilityRole="button" accessibilityLabel="Open Action Center" onPress={()=>router.push('/action-center')} style={styles.panel}><View style={{flexDirection:'row',alignItems:'center',justifyContent:'space-between'}}><View style={{flex:1}}><Text style={styles.sectionTitle}>Action Center</Text><Text style={styles.sub}>Production To-Dos and casting offers</Text></View><Ionicons name="chevron-forward" size={23} color={colors.primary}/></View></Pressable>
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
  intro:{fontSize:13,color:colors.muted,marginTop:-2,marginBottom:6},
  heading:{flexDirection:'row',justifyContent:'space-between',alignItems:'center'},
  sectionTitle:{fontFamily:'serif',fontSize:21,fontWeight:'700',color:colors.navy},
  link:{color:colors.primary,fontSize:13,fontWeight:'700'},
  hero:{position:'relative',backgroundColor:colors.stage,borderRadius:17,borderWidth:1,borderColor:colors.gold,borderBottomWidth:3,height:166,overflow:'hidden'},
  heroContent:{flex:1,paddingVertical:13,paddingLeft:16,paddingRight:67,gap:5,justifyContent:'center'},eventContent:{gap:3},
  heroHeader:{flexDirection:'row',alignItems:'center',gap:8},heroArrow:{position:'absolute',right:13,bottom:13,width:31,height:31,borderRadius:16,backgroundColor:colors.gold,alignItems:'center',justifyContent:'center'},
  heroEyebrow:{fontSize:10,fontWeight:'800',color:colors.gold,letterSpacing:2},
  heroTitle:{fontFamily:'serif',fontSize:23,fontWeight:'700',color:'#fff'},
  heroText:{fontSize:12,color:'#fff4e6'},
  event:{backgroundColor:colors.card,padding:13,borderRadius:14,borderWidth:1,borderColor:colors.border,gap:3},
  rowTitle:{fontFamily:'serif',fontSize:17,fontWeight:'700',color:colors.text},
  sub:{color:colors.muted,fontSize:12},
  panel:{backgroundColor:colors.card,borderRadius:14,borderWidth:1,borderColor:colors.border,padding:13,gap:8},
  task:{flexDirection:'row',alignItems:'center',gap:12,paddingVertical:5},
  shortcuts:{flexDirection:'row',flexWrap:'wrap',gap:9},
  shortcut:{width:'47%',backgroundColor:colors.card,borderWidth:1,borderColor:colors.border,borderRadius:12,padding:13,gap:8},
  shortcutTitle:{fontSize:13,fontWeight:'700',color:colors.text}
});
