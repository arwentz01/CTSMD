import React,{useCallback,useMemo,useState} from 'react';
import {StyleSheet,Text,View} from 'react-native';
import {Ionicons} from '@expo/vector-icons';
import {router} from 'expo-router';
import {Screen} from '../src/components/Screen';
import {TouchTarget} from '../src/components/TouchTarget';
import {LoadState,useScreenData} from '../src/components/LoadState';
import {theatre} from '../src/api/client';
import type {TheatreEvent} from '../src/types/ctsmd';
import {colors} from '../src/theme/colors';

type Scope='Upcoming'|'All visible';
const localInstant=(value:string)=>new Date(value.replace(' ','T'));
const dateKey=(event:TheatreEvent)=>event.starts_at.slice(0,10);
function dateHeader(key:string){
 const parts=key.split('-').map(Number);
 if(parts.length!==3||!parts.every(Number.isInteger))return key;
 const day=new Date(parts[0],parts[1]-1,parts[2],12);
 return Number.isFinite(day.getTime())?day.toLocaleDateString(undefined,{weekday:'long',month:'long',day:'numeric'}):key;
}
function localTime(value:string){
 const date=localInstant(value);
 return Number.isFinite(date.getTime())?date.toLocaleTimeString(undefined,{hour:'numeric',minute:'2-digit'}):value.slice(11,16);
}
export default function Calendar(){
 const [scope,setScope]=useState<Scope>('Upcoming');
 const load=useCallback(()=>theatre(),[]);
 const {data,error,loading,retry}=useScreenData(load);
 const days=useMemo(()=>{
  const now=Date.now();
  const visible=[...(data?.events||[])].filter(event=>scope==='All visible'||localInstant(event.starts_at).getTime()>=now).sort((a,b)=>a.starts_at.localeCompare(b.starts_at));
  const sections=new Map<string,TheatreEvent[]>();
  for(const event of visible){const key=dateKey(event);if(!sections.has(key))sections.set(key,[]);sections.get(key)!.push(event)}
  return [...sections.entries()];
 },[data,scope]);
 return <Screen title="My calendar">
  <Text style={s.intro}>The calls and events currently visible to your CTSMD account.</Text>
  <View style={s.filter} accessibilityRole="radiogroup">
   {(['Upcoming','All visible'] as Scope[]).map(value=><TouchTarget key={value} accessibilityRole="radio" accessibilityState={{checked:scope===value}} onPress={()=>setScope(value)} style={[s.filterButton,scope===value&&s.filterActive]}><Text style={[s.filterLabel,scope===value&&s.filterTextActive]}>{value}</Text></TouchTarget>)}
  </View>
  <LoadState loading={loading} error={error} onRetry={retry}/>
  {data&&days.length===0&&<View style={s.empty}><Ionicons name="calendar-outline" color={colors.muted} size={26}/><Text style={s.emptyTitle}>Nothing scheduled here yet</Text><Text style={s.intro}>Check back when your production publishes more calls.</Text></View>}
  {days.map(([key,events])=><View key={key} style={s.day}>
   <Text accessibilityRole="header" style={s.dayTitle}>{dateHeader(key)}</Text>
   <View style={s.dayItems}>{events.map(event=><TouchTarget key={event.id} accessibilityRole="button" accessibilityLabel={'Open '+event.title+', '+localTime(event.starts_at)} onPress={()=>router.push({pathname:'/events/[id]',params:{id:String(event.id)}})} style={s.event}>
    <View style={s.time}><Text style={s.timeText}>{localTime(event.starts_at)}</Text></View>
    <View style={s.eventCopy}><Text style={s.title}>{event.title}</Text><Text style={s.meta}>{event.production_title}</Text>{!!event.location&&<Text style={s.meta} numberOfLines={1}>{event.location}</Text>}</View>
    <Ionicons name="chevron-forward" color={colors.muted} size={19}/>
   </TouchTarget>)}</View>
  </View>)}
 </Screen>;
}
const s=StyleSheet.create({
 intro:{color:colors.muted,fontSize:13,lineHeight:20},
 filter:{flexDirection:'row',padding:3,backgroundColor:colors.ivory,borderRadius:12,gap:3},
 filterButton:{flex:1,alignItems:'center',paddingHorizontal:12,minHeight:44,borderRadius:9},
 filterActive:{backgroundColor:colors.card,borderWidth:1,borderColor:colors.border},
 filterLabel:{fontSize:13,fontWeight:'600',color:colors.muted},filterTextActive:{color:colors.primary,fontWeight:'800'},
 day:{gap:8},dayTitle:{fontSize:15,color:colors.navy,fontWeight:'800'},
 dayItems:{borderWidth:1,borderColor:colors.border,borderRadius:12,backgroundColor:colors.card,overflow:'hidden'},
 event:{flexDirection:'row',alignItems:'center',paddingHorizontal:12,paddingVertical:9,gap:13,borderBottomWidth:StyleSheet.hairlineWidth,borderBottomColor:colors.border},
 time:{width:76,alignItems:'flex-start'},timeText:{fontSize:13,fontWeight:'800',color:colors.primary},
 eventCopy:{flex:1,gap:3},title:{color:colors.text,fontSize:15,fontWeight:'700'},meta:{fontSize:12,color:colors.muted},
 empty:{borderWidth:1,borderColor:colors.border,borderRadius:12,alignItems:'center',padding:24,gap:8,backgroundColor:colors.card},emptyTitle:{fontSize:16,fontWeight:'700',color:colors.navy}
});
