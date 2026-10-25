import React,{useCallback,useState} from 'react';
import {router} from 'expo-router';
import {Linking,Pressable,StyleSheet,Text,View} from 'react-native';
import {Ionicons} from '@expo/vector-icons';
import {Screen} from '../../src/components/Screen';
import {LoadState,useScreenData} from '../../src/components/LoadState';
import {theatre} from '../../src/api/client';
import type {Production} from '../../src/types/ctsmd';
import {colors} from '../../src/theme/colors';

type Filter='Current'|'Upcoming'|'Past';
const isPast=(p:Production)=>['completed','closed','archived','past','cancelled'].includes(p.status.toLowerCase());
const category=(p:Production):Filter=>isPast(p)?'Past':p.is_active===1?'Current':'Upcoming';
const formatDate=(s:string)=>{const d=new Date(s.replace(' ','T'));return Number.isFinite(d.getTime())?d.toLocaleString(undefined,{month:'short',day:'numeric',hour:'numeric',minute:'2-digit'}):s;};
export default function Theatre(){
 const [selected,setSelected]=useState<Filter>('Current');
 const load=useCallback(()=>theatre(),[]);
 const {data,error,loading}=useScreenData(load);
 const productions=(data?.productions||[]).filter(p=>category(p)===selected);
 const upcoming=(data?.events||[]).filter(e=>e.status.toLowerCase()!=='cancelled'&&new Date(e.starts_at.replace(' ','T')).getTime()>=Date.now()).sort((a,b)=>a.starts_at.localeCompare(b.starts_at));
 return <Screen title="Theatre">
  <Text style={s.subtitle}>Your productions, people, and performances.</Text>
  <View style={s.filters}>{(['Current','Upcoming','Past'] as Filter[]).map(filter=><Pressable key={filter} accessibilityRole="button" accessibilityState={{selected:filter===selected}} style={[s.filter,filter===selected&&s.activeFilter]} onPress={()=>setSelected(filter)}><Text style={[s.filterLabel,filter===selected&&s.activeLabel]}>{filter}</Text></Pressable>)}</View>
  <LoadState loading={loading} error={error}/>
  {data&&<>
   {productions.length===0?<View style={s.card}><Text style={s.subtitle}>No {selected.toLowerCase()} productions available to your account.</Text></View>:productions.map(p=><Pressable key={p.id} accessibilityRole="button" onPress={()=>router.push({pathname:'/theatre/[id]',params:{id:String(p.id)}})} style={s.card}>
     <View style={s.heading}><Ionicons name="film-outline" size={23} color={colors.primary}/><View style={{flex:1}}><Text style={s.title}>{p.title}</Text><Text style={s.subtitle}>{[p.season,p.status].filter(Boolean).join(' · ')}</Text></View><Ionicons name="chevron-forward" size={18} color={colors.muted}/></View>
   </Pressable>)}
   <View style={s.sectionHeading}><Text style={s.sectionTitle}>My Theatre Schedule</Text><Text style={s.subtitle}>Upcoming</Text></View>
   {upcoming.length===0?<View style={s.card}><Text style={s.subtitle}>No upcoming events are visible.</Text></View>:upcoming.slice(0,5).map(e=><Pressable key={e.id} accessibilityRole="button" style={s.card} onPress={()=>router.push({pathname:'/theatre/[id]',params:{id:String(e.production_id)}})}><Text style={s.title}>{e.title}</Text><Text style={s.subtitle}>{e.production_title} · {formatDate(e.starts_at)}</Text>{!!e.location&&<Text style={s.subtitle}>{e.location}</Text>}</Pressable>)}
   <Text style={s.sectionTitle}>Explore Theatre</Text>
   <View style={s.grid}>
    <Pressable accessibilityRole="link" style={s.tile} onPress={()=>void Linking.openURL('https://ctsmd.ludus.com/index.php')}><Ionicons name="ticket-outline" color={colors.primary} size={23}/><Text style={s.title}>Tickets</Text><Text style={s.subtitle}>Shows and seats</Text></Pressable>
    <Pressable accessibilityRole="button" style={s.tile} onPress={()=>router.push('/(tabs)/community')}><Ionicons name="people-outline" color={colors.primary} size={23}/><Text style={s.title}>Channels</Text><Text style={s.subtitle}>Production discussions</Text></Pressable>
   </View>
  </>}
 </Screen>;
}
const s=StyleSheet.create({
 subtitle:{color:colors.muted,fontSize:13,lineHeight:18},filters:{flexDirection:'row',gap:7,marginVertical:8},filter:{flex:1,borderRadius:13,borderWidth:1,borderColor:colors.gold,paddingVertical:13,alignItems:'center',backgroundColor:colors.ivory},activeFilter:{backgroundColor:colors.burgundy,borderColor:colors.gold},filterLabel:{color:colors.text,fontWeight:'700',fontSize:13},activeLabel:{color:'#fff'},
 card:{padding:19,backgroundColor:colors.card,borderRadius:17,borderWidth:1,borderColor:colors.border,gap:9},heading:{flexDirection:'row',alignItems:'center',gap:12},title:{color:colors.text,fontFamily:'Georgia',fontSize:19,fontWeight:'700'},sectionHeading:{flexDirection:'row',justifyContent:'space-between',alignItems:'center',marginTop:10},sectionTitle:{fontFamily:'Georgia',fontSize:25,fontWeight:'700',color:colors.navy,marginTop:12},grid:{flexDirection:'row',gap:10},tile:{flex:1,padding:14,backgroundColor:colors.card,borderRadius:12,borderWidth:1,borderColor:colors.border,gap:8}
});
