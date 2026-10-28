import {TouchTarget as Pressable} from '../../src/components/TouchTarget';
import React,{useCallback} from 'react';
import {useLocalSearchParams,router} from 'expo-router';
import {Ionicons} from '@expo/vector-icons';
import {Linking,StyleSheet,Text,View} from 'react-native';
import {Screen} from '../../src/components/Screen';
import {ProductionHero} from '../../src/components/ProductionHero';
import {LoadState,useScreenData} from '../../src/components/LoadState';
import {theatre,channels,productionCasting} from '../../src/api/client';
import {colors} from '../../src/theme/colors';

const dateLabel=(value:string)=>{const date=new Date(value.replace(' ','T'));return Number.isFinite(date.getTime())?date.toLocaleString(undefined,{weekday:'short',month:'short',day:'numeric',hour:'numeric',minute:'2-digit'}):value;};
export default function ProductionPage(){
 const {id}=useLocalSearchParams<{id:string}>();
 const productionId=Number(id);
 const valid=Number.isSafeInteger(productionId)&&productionId>0;
 const load=useCallback(async()=>{const t=await theatre();const selected=t.productions.find(p=>p.id===productionId);if(!valid||!selected||selected.is_public_preview)return {t,c:{channels:[]},casting:{cast:null,callbacks:null}};const [c,casting]=await Promise.all([channels(),productionCasting(productionId)]);return {t,c,casting}},[productionId,valid]);
 const {data,error,loading,retry}=useScreenData(load);
 const production=data?.t.productions.find(p=>p.id===productionId);
 const casting=data?.casting;
 const events=(data?.t.events||[]).filter(e=>e.production_id===productionId&&e.status.toLowerCase()!=='cancelled'&&new Date(e.starts_at.replace(' ','T')).getTime()>=Date.now()).sort((a,b)=>a.starts_at.localeCompare(b.starts_at));
 const relatedChannels=(data?.c.channels||[]).filter(c=>c.production_id===productionId);
 return <Screen title={production?.title||'Production'}>
   <Pressable onPress={()=>router.back()} accessibilityRole="button" style={s.back}><Ionicons name="arrow-back" color={colors.navy} size={23}/><Text style={s.backText}>Back to Theatre</Text></Pressable>
    <LoadState loading={loading} error={error} onRetry={retry}/>
    {!valid&&<Text style={s.secondary}>Invalid production.</Text>}
    {data&&valid&&!production&&<Text style={s.secondary}>This production isn't available to your account.</Text>}
    {production&&<>
     <ProductionHero production={production}/>
     {(production.lifecycle_stage||production.status)==='planning'&&<View style={s.notice}><Ionicons name="information-circle-outline" size={16} color={colors.burgundy}/><Text style={s.noticeText}>Planning: private preparation is not published to the wider community.</Text></View>}
     {!production.is_public_preview&&casting&&(['callbacks','cast'] as const).map(type=>{const published=casting[type];return published?<View key={type} style={s.card}><Text style={s.heading}>{type==='cast'?'Published Cast':'Published Callbacks'}</Text>{!!published.headline&&<Text style={s.tileTitle}>{published.headline}</Text>}{!!published.member_note&&<Text style={s.secondary}>{published.member_note}</Text>}{type==='cast'?(()=>{const grouped=new Map<string,string[]>();published.cast.forEach(person=>{(person.role||'Cast').split('/').map(role=>role.trim()).filter(Boolean).forEach(role=>{const names=grouped.get(role)||[];if(!names.includes(person.name))names.push(person.name);grouped.set(role,names);});});return [...grouped.entries()].sort(([a],[b])=>a.localeCompare(b)).map(([role,names])=><View key={role} style={{flexDirection:'row',justifyContent:'space-between',gap:12,paddingVertical:7,borderBottomWidth:1,borderBottomColor:'#eee5e8'}}><Text style={[s.castEntry,{fontWeight:'700',flex:1}]}>{role}</Text><Text style={[s.castEntry,{flex:1,textAlign:'right'}]}>{names.join(', ')}</Text></View>);})():published.cast.map((person,i)=><Text key={i} style={s.castEntry}>{person.name}{person.role?' · '+person.role:''}</Text>)}</View>:null})}
     {(production.audition_starts_at||production.audition_ends_at||production.performance_starts_at||production.performance_ends_at)&&<View style={s.card}><Text style={s.heading}>Show dates</Text>{([['Auditions begin',production.audition_starts_at],['Auditions through',production.audition_ends_at],['Performances begin',production.performance_starts_at],['Performances through',production.performance_ends_at]] as [string,string|null|undefined][]).filter((entry)=>!!entry[1]).map(([label,value])=><Text key={label} style={s.secondary}>{label}: {value?.slice(0,10)}</Text>)}<Pressable onPress={()=>void Linking.openURL('https://ctsmd.me/productions/view?id='+production.id)} accessibilityRole="link"><Text style={s.tileTitle}>View public production information</Text></Pressable></View>}
     {production.is_public_preview?<View style={s.card}><Text style={s.heading}>Production announcement</Text><Text style={s.secondary}>This show has been announced. Rehearsal schedules, cast details, and channels are only available to authorized production members.</Text></View>:<Text style={s.heading}>Production Hub</Text>}
     {!production.is_public_preview&&<View style={s.tiles}>
      <Pressable accessibilityRole="link" style={s.tile} onPress={()=>void Linking.openURL('https://ctsmd.ludus.com/index.php')}><Ionicons name="ticket-outline" color={colors.primary} size={23}/><Text style={s.tileTitle}>Tickets</Text><Text style={s.secondary}>Show tickets</Text></Pressable>
      <Pressable accessibilityRole="button" style={s.tile} onPress={()=>router.push({pathname:'/(tabs)/community',params:{production_id:String(productionId)}})}><Ionicons name="chatbubbles-outline" color={colors.primary} size={23}/><Text style={s.tileTitle}>Discussions</Text><Text style={s.secondary}>{relatedChannels.length} channels</Text></Pressable>
     </View>}
     {!production.is_public_preview&&<Text style={s.heading}>Production Schedule</Text>}
     {!production.is_public_preview&&(events.length===0?<View style={s.card}><Text style={s.secondary}>No upcoming events for this production.</Text></View>:events.slice(0,5).map(event=><View style={s.card} key={event.id}><Text style={s.tileTitle}>{event.title}</Text><Text style={s.secondary}>{dateLabel(event.starts_at)}</Text>{!!event.location&&<Text style={s.secondary}>{event.location}</Text>}</View>))}
     {!production.is_public_preview&&<Text style={s.heading}>Production Channels</Text>}
     {!production.is_public_preview&&(relatedChannels.length===0?<View style={s.card}><Text style={s.secondary}>No channels currently available for this production.</Text></View>:relatedChannels.map(channel=><Pressable key={channel.id} accessibilityRole="button" style={s.card} onPress={()=>router.push({pathname:'/community/[id]',params:{id:String(channel.id)}})}><Text style={s.tileTitle}>{channel.name}</Text>{!!channel.description&&<Text style={s.secondary}>{channel.description}</Text>}</Pressable>))}
    </>}
 </Screen>;
}
const s=StyleSheet.create({notice:{flexDirection:'row',gap:7,alignItems:'center',paddingHorizontal:10,paddingVertical:7,borderRadius:9,backgroundColor:'#fff1d9'},noticeText:{color:colors.burgundy,fontSize:12,flex:1},castEntry:{fontSize:14,color:colors.text,paddingVertical:3},back:{paddingHorizontal:20,paddingVertical:12,flexDirection:'row',alignItems:'center',gap:10,backgroundColor:colors.card},backText:{color:colors.navy,fontWeight:'700',fontSize:16},secondary:{fontSize:13,color:colors.muted},heading:{fontSize:18,fontWeight:'800',color:colors.navy,marginTop:12},tiles:{flexDirection:'row',gap:10},tile:{flex:1,padding:14,gap:7,backgroundColor:colors.card,borderRadius:12,borderWidth:1,borderColor:colors.border},tileTitle:{fontWeight:'700',fontSize:15,color:colors.text},card:{backgroundColor:colors.card,padding:15,borderRadius:12,borderWidth:1,borderColor:colors.border,gap:5}});
