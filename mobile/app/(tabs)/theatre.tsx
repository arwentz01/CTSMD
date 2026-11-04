import {TouchTarget as Pressable} from '../../src/components/TouchTarget';
import React,{useCallback,useState} from 'react';
import {router} from 'expo-router';
import {Linking,StyleSheet,Text,View} from 'react-native';
import {Ionicons} from '@expo/vector-icons';
import {Screen} from '../../src/components/Screen';
import {ProductionHero} from '../../src/components/ProductionHero';
import {LoadState,useScreenData} from '../../src/components/LoadState';
import {theatre} from '../../src/api/client';
import type {Production} from '../../src/types/ctsmd';
import {colors} from '../../src/theme/colors';

type Filter='Current'|'Upcoming';
const isPast=(p:Production)=>['completed','closed','archived','past','cancelled'].includes(p.status.toLowerCase());
const category=(p:Production):Filter|'Past'=>p.lifecycle_stage?(['completed','archived'].includes(p.lifecycle_stage)?'Past':['upcoming','planning'].includes(p.lifecycle_stage)?'Upcoming':'Current'):isPast(p)?'Past':p.is_active===1?'Current':'Upcoming';
export default function Theatre(){
 const [selected,setSelected]=useState<Filter>('Current');
 const load=useCallback(()=>theatre(),[]);
 const {data,error,loading,retry}=useScreenData(load);
 const productions=(data?.productions||[]).filter(p=>category(p)===selected);
 const upcoming=(data?.events||[]).filter(e=>e.status.toLowerCase()!=='cancelled'&&new Date(e.starts_at.replace(' ','T')).getTime()>=Date.now()).sort((a,b)=>a.starts_at.localeCompare(b.starts_at));
 return <Screen title="Theatre" compact>
  <Text style={s.subtitle}>Your productions, people, and performances.</Text>
  <View style={s.filters}>{(['Current','Upcoming'] as Filter[]).map(filter=><Pressable key={filter} accessibilityRole="button" accessibilityState={{selected:filter===selected}} style={[s.filter,filter===selected&&s.activeFilter]} onPress={()=>setSelected(filter)}><Text style={[s.filterLabel,filter===selected&&s.activeLabel]}>{filter}</Text></Pressable>)}</View>
  <LoadState loading={loading} error={error} onRetry={retry}/>
  {data&&<>
   {productions.length===0?<View style={s.card}><Text style={s.subtitle}>No {selected.toLowerCase()} productions available to your account.</Text></View>:productions.map(p=><ProductionHero key={p.id} production={p} onPress={()=>router.push({pathname:'/theatre/[id]',params:{id:String(p.id)}})}/>)}
   <View style={s.sectionHeading}><Text accessibilityRole="header" style={s.sectionTitle}>My Theatre Schedule</Text><Pressable accessibilityRole="button" onPress={()=>router.push('/calendar')}><Text style={s.link}>View all</Text></Pressable></View>
   <View style={s.agenda}>{upcoming.length===0?<Text style={s.empty}>No upcoming events are visible.</Text>:upcoming.slice(0,4).map((e,index)=>{const date=new Date(e.starts_at.replace(' ','T'));const location=e.location?.trim();return <Pressable key={e.id} accessibilityRole="button" style={[s.agendaRow,index>0&&s.divider]} onPress={()=>router.push({pathname:'/events/[id]',params:{id:String(e.id)}})}><View style={s.dateBlock}><Text style={s.month}>{date.toLocaleDateString(undefined,{month:'short'})}</Text><Text style={s.day}>{date.getDate()}</Text></View><View style={s.eventCopy}><Text style={s.eventTitle}>{e.title}</Text><Text style={s.subtitle}>{date.toLocaleTimeString(undefined,{hour:'numeric',minute:'2-digit'})}{location&&location.toLowerCase()!=='tbd'?' · '+location:''}</Text>{!!e.production_title&&<Text style={s.subtitle}>{e.production_title}</Text>}</View><Ionicons name="chevron-forward" size={18} color={colors.muted}/></Pressable>})}</View>
   <Text style={s.sectionTitle}>Explore Theatre</Text>
   <View style={s.grid}>
    <Pressable accessibilityRole="link" style={s.tile} onPress={()=>void Linking.openURL('https://ctsmd.ludus.com/index.php')}><Ionicons name="ticket-outline" color={colors.primary} size={23}/><Text style={s.title}>Tickets</Text><Text style={s.subtitle}>Shows and seats</Text></Pressable>
    <Pressable accessibilityRole="button" style={s.tile} onPress={()=>router.push('/(tabs)/community')}><Ionicons name="people-outline" color={colors.primary} size={23}/><Text style={s.title}>Channels</Text><Text style={s.subtitle}>Production discussions</Text></Pressable>
   </View>
  </>}
 </Screen>;
}
const s=StyleSheet.create({
 subtitle:{color:colors.muted,fontSize:13,lineHeight:18},filters:{flexDirection:'row',gap:7,marginVertical:2},filter:{flex:1,borderRadius:13,borderWidth:1,borderColor:colors.gold,paddingVertical:8,alignItems:'center',backgroundColor:colors.ivory},activeFilter:{backgroundColor:colors.burgundy,borderColor:colors.gold},filterLabel:{color:colors.text,fontWeight:'700',fontSize:13},activeLabel:{color:'#fff'},
 card:{padding:12,backgroundColor:colors.card,borderRadius:17,borderWidth:1,borderColor:colors.border,gap:9},heading:{flexDirection:'row',alignItems:'center',gap:12},title:{color:colors.text,fontSize:16,fontWeight:'700'},sectionHeading:{flexDirection:'row',justifyContent:'space-between',alignItems:'center',gap:8,marginTop:3},sectionTitle:{fontSize:20,fontWeight:'700',color:colors.navy,marginTop:3,flexShrink:1},grid:{flexDirection:'row',gap:10},tile:{flex:1,padding:12,backgroundColor:colors.card,borderRadius:12,borderWidth:1,borderColor:colors.border,gap:4},
 link:{color:colors.primary,fontWeight:'700'},agenda:{backgroundColor:colors.card,borderWidth:1,borderColor:colors.border,borderRadius:12,overflow:'hidden'},agendaRow:{flexDirection:'row',alignItems:'center',gap:12,paddingHorizontal:12,paddingVertical:9,minHeight:68},divider:{borderTopWidth:StyleSheet.hairlineWidth,borderTopColor:colors.border},dateBlock:{width:42,alignItems:'center'},month:{fontSize:12,fontWeight:'700',color:colors.primary},day:{fontSize:20,fontWeight:'700',color:colors.navy},eventCopy:{flex:1,gap:2},eventTitle:{fontSize:15,fontWeight:'700',color:colors.text},empty:{padding:12,color:colors.muted}
});
