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
  <Text style={s.intro}>Production tasks and casting offers requiring your attention.</Text>
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
   {data.casting_offers.map(o=><View key={o.id} style={s.card}>
     <Text style={s.name}>{o.production_title}</Text>
     <Text style={s.body}>{o.first_name} {o.last_name} · {o.role_snapshot}</Text>
     <TextInput style={s.input} multiline maxLength={2000} value={notes[o.id]||''} onChangeText={value=>setNotes(current=>({...current,[o.id]:value}))} placeholder="Reason for declining (required to decline)" accessibilityLabel="Reason for declining casting offer"/>
     <View style={s.actions}><Pressable disabled={busy!==null} accessibilityRole="button" style={[s.button,{flex:1}]} onPress={()=>void respond(o.id,'accepted')}><Text style={s.buttonText}>Accept</Text></Pressable><Pressable disabled={busy!==null} accessibilityRole="button" style={s.secondaryButton} onPress={()=>void respond(o.id,'declined')}><Text style={s.secondaryText}>Decline</Text></Pressable></View>
   </View>)}
   <Pressable accessibilityRole="button" onPress={()=>void refresh()} style={s.refresh}><Ionicons name="refresh-outline" size={17} color={colors.primary}/><Text style={s.link}>Refresh</Text></Pressable>
  </>}
 </Screen>;
}
const s=StyleSheet.create({
 intro:{color:colors.muted,marginBottom:18,fontSize:14},heading:{flexDirection:'row',alignItems:'center',gap:9,marginTop:16,marginBottom:10},title:{fontWeight:'800',fontSize:20,color:colors.navy},
 card:{padding:16,borderWidth:1,borderColor:'#e4dadd',backgroundColor:'#fff',borderRadius:14,marginBottom:11,gap:7},
 name:{fontSize:17,fontWeight:'800',color:colors.navy},body:{fontSize:14,color:colors.navy},muted:{fontSize:13,color:colors.muted},error:{color:'#a22035'},
 actions:{flexDirection:'row',gap:10,marginTop:8},button:{backgroundColor:colors.primary,paddingVertical:11,paddingHorizontal:15,borderRadius:9,alignItems:'center',marginTop:9},buttonText:{color:'#fff',fontWeight:'800'},secondaryButton:{paddingVertical:11,paddingHorizontal:15,alignItems:'center',justifyContent:'center',borderRadius:9,borderColor:colors.primary,borderWidth:1,flex:1,marginTop:9},secondaryText:{color:colors.primary,fontWeight:'800'},
 input:{borderWidth:1,borderColor:'#cdc5ca',borderRadius:8,padding:12,minHeight:58,textAlignVertical:'top',color:colors.navy},disabled:{opacity:.5},refresh:{flexDirection:'row',gap:8,alignItems:'center',padding:12},link:{color:colors.primary,fontWeight:'700'}
});
