import React,{useCallback} from 'react';
import {useLocalSearchParams,router} from 'expo-router';
import {Ionicons} from '@expo/vector-icons';
import {Linking,Pressable,StyleSheet,Text,View} from 'react-native';
import {Screen} from '../../src/components/Screen';
import {LoadState,useScreenData} from '../../src/components/LoadState';
import {theatre,channels,tasks} from '../../src/api/client';
import {colors} from '../../src/theme/colors';

const dateLabel=(value:string)=>{const date=new Date(value.replace(' ','T'));return Number.isFinite(date.getTime())?date.toLocaleString(undefined,{weekday:'short',month:'short',day:'numeric',hour:'numeric',minute:'2-digit'}):value;};
export default function ProductionPage(){
 const {id}=useLocalSearchParams<{id:string}>();
 const productionId=Number(id);
 const valid=Number.isSafeInteger(productionId)&&productionId>0;
 const load=useCallback(async()=>{const [t,c,a]=await Promise.all([theatre(),channels(),tasks()]);return {t,c,a}},[]);
 const {data,error,loading}=useScreenData(load);
 const production=data?.t.productions.find(p=>p.id===productionId);
 const events=(data?.t.events||[]).filter(e=>e.production_id===productionId&&e.status.toLowerCase()!=='cancelled'&&new Date(e.starts_at.replace(' ','T')).getTime()>=Date.now()).sort((a,b)=>a.starts_at.localeCompare(b.starts_at));
 const relatedChannels=(data?.c.channels||[]).filter(c=>c.production_id===productionId);
 return <Screen title={production?.title||'Production'}>
   <Pressable onPress={()=>router.back()} accessibilityRole="button" style={s.back}><Ionicons name="arrow-back" color={colors.navy} size={23}/><Text style={s.backText}>Back to Theatre</Text></Pressable>
    <LoadState loading={loading} error={error}/>
    {!valid&&<Text style={s.secondary}>Invalid production.</Text>}
    {data&&valid&&!production&&<Text style={s.secondary}>This production isn't available to your account.</Text>}
    {production&&<>
     <Text style={s.secondary}>{[production.season,production.status].filter(Boolean).join(' · ')}</Text>
     <Text style={s.heading}>Production Hub</Text>
     <View style={s.tiles}>
      <Pressable accessibilityRole="link" style={s.tile} onPress={()=>void Linking.openURL('https://ctsmd.ludus.com/index.php')}><Ionicons name="ticket-outline" color={colors.primary} size={23}/><Text style={s.tileTitle}>Tickets</Text><Text style={s.secondary}>Show tickets</Text></Pressable>
      <Pressable accessibilityRole="button" style={s.tile} onPress={()=>router.push({pathname:'/(tabs)/community',params:{production_id:String(productionId)}})}><Ionicons name="chatbubbles-outline" color={colors.primary} size={23}/><Text style={s.tileTitle}>Discussions</Text><Text style={s.secondary}>{relatedChannels.length} channels</Text></Pressable>
     </View>
     <Text style={s.heading}>Production Schedule</Text>
     {events.length===0?<View style={s.card}><Text style={s.secondary}>No upcoming events for this production.</Text></View>:events.slice(0,5).map(event=><View style={s.card} key={event.id}><Text style={s.tileTitle}>{event.title}</Text><Text style={s.secondary}>{dateLabel(event.starts_at)}</Text>{!!event.location&&<Text style={s.secondary}>{event.location}</Text>}</View>)}
     <Text style={s.heading}>Production Channels</Text>
     {relatedChannels.length===0?<View style={s.card}><Text style={s.secondary}>No channels currently available for this production.</Text></View>:relatedChannels.map(channel=><Pressable key={channel.id} accessibilityRole="button" style={s.card} onPress={()=>router.push({pathname:'/community/[id]',params:{id:String(channel.id)}})}><Text style={s.tileTitle}>{channel.name}</Text>{!!channel.description&&<Text style={s.secondary}>{channel.description}</Text>}</Pressable>)}
    </>}
 </Screen>;
}
const s=StyleSheet.create({back:{paddingHorizontal:20,paddingVertical:12,flexDirection:'row',alignItems:'center',gap:10,backgroundColor:colors.card},backText:{color:colors.navy,fontWeight:'700',fontSize:16},secondary:{fontSize:13,color:colors.muted},heading:{fontSize:18,fontWeight:'800',color:colors.navy,marginTop:12},tiles:{flexDirection:'row',gap:10},tile:{flex:1,padding:14,gap:7,backgroundColor:colors.card,borderRadius:12,borderWidth:1,borderColor:colors.border},tileTitle:{fontWeight:'700',fontSize:15,color:colors.text},card:{backgroundColor:colors.card,padding:15,borderRadius:12,borderWidth:1,borderColor:colors.border,gap:5}});
