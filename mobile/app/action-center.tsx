import React,{useCallback,useEffect,useState} from 'react';
import {Alert,Pressable,StyleSheet,Text,TextInput,View} from 'react-native';
import {Ionicons} from '@expo/vector-icons';
import {Screen} from '../src/components/Screen';
import {tasks,completeProductionTodo,respondCastingOffer} from '../src/api/client';
import type {TasksResult} from '../src/types/ctsmd';
import {colors} from '../src/theme/colors';

export default function ActionCenter(){
 const [data,setData]=useState<TasksResult|null>(null);
 const [busy,setBusy]=useState<string|null>(null);
 const [error,setError]=useState('');
 const [notes,setNotes]=useState<Record<number,string>>({});
 const [declining,setDeclining]=useState<number|null>(null);
 const refresh=useCallback(async()=>{try{setError('');setData(await tasks());}catch(e){setError(e instanceof Error?e.message:'Unable to load your Action Center.');}},[]);
 useEffect(()=>{void refresh();},[refresh]);
 const complete=async(id:number)=>{setBusy('task-'+id);try{await completeProductionTodo(id);await refresh();}catch(e){Alert.alert('Unable to complete task',e instanceof Error?e.message:'Please try again.');}finally{setBusy(null);}};
 const respond=async(id:number,decision:'accepted'|'declined')=>{
  const note=(notes[id]||'').trim();
  if(decision==='declined'&&!note){Alert.alert('Reason required','Please enter a brief explanation before declining the role.');return;}
  setBusy('offer-'+id);
  try{await respondCastingOffer(id,decision,note);await refresh();Alert.alert('Response recorded',decision==='accepted'?'You accepted the role.':'Your decline was sent to production staff.');}
  catch(e){Alert.alert('Unable to respond',e instanceof Error?e.message:'Please try again.');}
  finally{setBusy(null);}
 };
 return <Screen title="Action Center">
  <Text style={s.intro}>Production tasks, casting offers, and approved family notices.</Text>
  {!!error&&<View style={s.card}><Text style={s.error}>{error}</Text><Pressable onPress={()=>void refresh()}><Text style={s.link}>Try again</Text></Pressable></View>}
  {!data&&!error&&<Text style={s.muted}>Loading your assignments…</Text>}
  {data&&<>
   <View style={s.heading}><Ionicons name="checkbox-outline" size={22} color={colors.primary}/><Text style={s.title}>Production To-Dos</Text></View>
   {!data.production_todos.length&&<Text style={s.muted}>No outstanding production tasks.</Text>}
   {data.production_todos.map(t=><View key={t.assignment_id} style={s.card}>
     <Text style={s.name}>{t.title}</Text>
     <Text style={s.muted}>{t.production_title}{t.required?' · Required':' · Optional'}</Text>
     <Text style={s.muted}>{t.is_own?'Assigned to you':'For '+t.student_first+' '+t.student_last}</Text>
     {!!t.due_at&&<Text style={s.muted}>Due {t.due_at.replace(' ',' · ')}</Text>}
     {!!t.notes&&<Text style={s.body}>{t.notes}</Text>}
     <Pressable disabled={busy!==null} accessibilityRole="button" style={[s.button,busy!==null&&s.disabled]} onPress={()=>void complete(t.assignment_id)}><Text style={s.buttonText}>Mark complete</Text></Pressable>
   </View>)}
   <View style={s.heading}><Ionicons name="star-outline" size={22} color={colors.primary}/><Text style={s.title}>Casting Offers</Text></View>
   {!data.casting_offers.length&&<Text style={s.muted}>No casting offers awaiting a response.</Text>}
   {data.casting_offers.map(o=><View key={o.id} style={s.offerCard}>
     <Text style={s.offerEyebrow}>✦ YOUR CASTING OFFER</Text>
     <Text style={s.offerProduction}>{o.production_title}</Text>
     <Text style={s.offerRoleLabel}>YOU'VE BEEN OFFERED THE ROLE OF</Text>
     <Text style={s.offerRole}>{o.role_snapshot}</Text>
     <Text style={s.offerStudent}>For {o.first_name} {o.last_name}</Text>
     <Text style={s.offerInstruction}>Please review your offer and let the production team know whether you accept or decline.</Text>
     <View style={s.actions}>
       <Pressable disabled={busy!==null} accessibilityRole="button" accessibilityLabel={'Accept '+o.role_snapshot+' in '+o.production_title} style={[s.button,{flex:1},busy!==null&&s.disabled]} onPress={()=>void respond(o.id,'accepted')}><Text style={s.buttonText}>Accept offer</Text></Pressable>
       <Pressable disabled={busy!==null} accessibilityRole="button" accessibilityState={{expanded:declining===o.id}} style={[s.secondaryButton,busy!==null&&s.disabled]} onPress={()=>setDeclining(o.id)}><Text style={s.secondaryText}>Decline offer</Text></Pressable>
     </View>
     {declining===o.id&&<View style={s.declinePanel}>
       <Text style={s.declineLabel}>Please share a reason for declining (required)</Text>
       <TextInput style={s.input} multiline maxLength={2000} value={notes[o.id]||''} onChangeText={value=>setNotes(current=>({...current,[o.id]:value}))} placeholder="Let the production team know why you're declining…" accessibilityLabel="Reason for declining casting offer"/>
       <View style={s.actions}>
         <Pressable disabled={busy!==null||!(notes[o.id]||'').trim()} accessibilityRole="button" style={[s.button,{flex:1},(busy!==null||!(notes[o.id]||'').trim())&&s.disabled]} onPress={()=>void respond(o.id,'declined')}><Text style={s.buttonText}>Confirm decline</Text></Pressable>
         <Pressable accessibilityRole="button" style={s.secondaryButton} onPress={()=>setDeclining(null)}><Text style={s.secondaryText}>Cancel</Text></Pressable>
       </View>
     </View>}
   </View>)}
   <View style={s.heading}><Ionicons name="time-outline" size={22} color={colors.primary}/><Text style={s.title}>Pickup Notices</Text></View>
   {!(data.late_pickup_notices??[]).length&&<Text style={s.muted}>No approved pickup notices.</Text>}
   {(data.late_pickup_notices??[]).map(n=><View key={n.id} style={s.card}>
     <Text style={s.name}>{n.subject}</Text>
     <Text style={s.muted}>{n.production_title} · {n.student_name}</Text>
     <Text style={s.body}>{n.body}</Text>
     <Text style={s.muted}>{n.status==='approved'?'Approved for family review':n.status==='queued'?'Email queued; delivery is not confirmed':'Email sent'}</Text>
   </View>)}
   <Pressable accessibilityRole="button" onPress={()=>void refresh()} style={s.refresh}><Ionicons name="refresh-outline" size={17} color={colors.primary}/><Text style={s.link}>Refresh</Text></Pressable>
  </>}
 </Screen>;
}
const s=StyleSheet.create({
 offerCard:{padding:20,borderWidth:1,borderColor:'#e6c8cf',backgroundColor:'#fffafa',borderRadius:18,marginBottom:14,gap:8},offerEyebrow:{fontSize:11,fontWeight:'800',letterSpacing:1.5,color:colors.primary},offerProduction:{fontFamily:'Georgia',fontSize:27,fontWeight:'700',lineHeight:33,color:colors.navy,marginTop:5,marginBottom:8},offerRoleLabel:{fontSize:10,fontWeight:'800',letterSpacing:1.2,color:colors.muted},offerRole:{fontFamily:'Georgia',fontSize:34,fontWeight:'700',color:colors.primary},offerStudent:{color:colors.navy,fontSize:13,fontWeight:'700'},offerInstruction:{fontSize:14,color:colors.muted,lineHeight:21,marginTop:8},declinePanel:{borderWidth:1,borderColor:'#e7d6da',borderRadius:12,padding:14,backgroundColor:'#fff',marginTop:9,gap:8},declineLabel:{color:colors.navy,fontSize:13,fontWeight:'700'},
 intro:{color:colors.muted,marginBottom:18,fontSize:14},heading:{flexDirection:'row',alignItems:'center',gap:9,marginTop:16,marginBottom:10},title:{fontWeight:'800',fontSize:20,color:colors.navy},
 card:{padding:16,borderWidth:1,borderColor:'#e4dadd',backgroundColor:'#fff',borderRadius:14,marginBottom:11,gap:7},
 name:{fontSize:17,fontWeight:'800',color:colors.navy},body:{fontSize:14,color:colors.navy},muted:{fontSize:13,color:colors.muted},error:{color:'#a22035'},
 actions:{flexDirection:'row',gap:10,marginTop:8},button:{backgroundColor:colors.primary,paddingVertical:11,paddingHorizontal:15,borderRadius:9,alignItems:'center',marginTop:9},buttonText:{color:'#fff',fontWeight:'800'},secondaryButton:{paddingVertical:11,paddingHorizontal:15,alignItems:'center',justifyContent:'center',borderRadius:9,borderColor:colors.primary,borderWidth:1,flex:1,marginTop:9},secondaryText:{color:colors.primary,fontWeight:'800'},
 input:{borderWidth:1,borderColor:'#cdc5ca',borderRadius:8,padding:12,minHeight:58,textAlignVertical:'top',color:colors.navy},disabled:{opacity:.5},refresh:{flexDirection:'row',gap:8,alignItems:'center',padding:12},link:{color:colors.primary,fontWeight:'700'}
});
