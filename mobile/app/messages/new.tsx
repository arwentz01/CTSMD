import React,{useCallback,useMemo,useState} from 'react';
import {Alert,Keyboard,ScrollView,StyleSheet,Text,TextInput,View} from 'react-native';
import {router} from 'expo-router';
import {Ionicons} from '@expo/vector-icons';
import {ConversationViewport} from '../../src/components/ConversationViewport';
import {TouchTarget as Pressable} from '../../src/components/TouchTarget';
import {recipients,createConversation} from '../../src/api/client';
import {LoadState,useScreenData} from '../../src/components/LoadState';
import {colors} from '../../src/theme/colors';

export default function NewMessage(){
 const load=useCallback(()=>recipients(),[]);
 const {data,error,loading,retry}=useScreenData(load);
 const [recipient,setRecipient]=useState<number|null>(null);
 const [query,setQuery]=useState('');
 const [message,setMessage]=useState('');
 const [busy,setBusy]=useState(false);
 const eligible=useMemo(()=>data?.recipients.filter(r=>r.selectable)||[],[data]);
 const matches=useMemo(()=>eligible.filter(r=>(r.name+' '+r.role).toLocaleLowerCase().includes(query.trim().toLocaleLowerCase())),[eligible,query]);
 const chosen=eligible.find(r=>r.id===recipient);
 async function send(){
  if(busy||!recipient||!message.trim())return;
  setBusy(true);
  try{
   const result=await createConversation(recipient,message.trim());
   if(!Number.isSafeInteger(Number(result.conversation_id))||Number(result.conversation_id)<1)throw Error('CTSMD did not return a valid conversation.');
   Keyboard.dismiss();
   router.replace({pathname:'/messages/[id]',params:{id:String(result.conversation_id)}});
  }catch(e){Alert.alert('Conversation not created',e instanceof Error?e.message:'Please try again.')}
  finally{setBusy(false);}
 }
 return <ConversationViewport>
  <View style={s.header}>
   <Pressable accessibilityLabel="Back to messages" onPress={()=>router.back()} style={s.back}><Ionicons name="chevron-back" size={26} color={colors.navy}/></Pressable>
   <Text accessibilityRole="header" style={s.title}>New conversation</Text>
  </View>
  <View style={s.search}>
   <Ionicons name="search-outline" size={21} color={colors.muted}/>
   <TextInput accessibilityLabel="Search recipients" placeholder="Search by name" placeholderTextColor={colors.muted} autoCorrect={false} value={query} onChangeText={setQuery} style={s.searchInput}/>
   {!!query&&<Pressable accessibilityLabel="Clear recipient search" onPress={()=>setQuery('')}><Ionicons name="close-circle" size={20} color={colors.muted}/></Pressable>}
  </View>
  {chosen&&<View style={s.selected}><Ionicons name="checkmark-circle" color={colors.primary} size={20}/><Text style={s.selectedName}>To: {chosen.name}</Text><Pressable accessibilityLabel="Change recipient" onPress={()=>{setRecipient(null);setQuery('')}}><Ionicons name="close" color={colors.muted} size={20}/></Pressable></View>}
  <LoadState loading={loading} error={error} onRetry={retry}/>
  <ScrollView style={s.list} contentContainerStyle={s.listContent} keyboardShouldPersistTaps="handled" keyboardDismissMode="on-drag">
   {!chosen&&matches.map(r=><Pressable key={r.id} accessibilityRole="button" accessibilityLabel={'Message '+r.name} onPress={()=>{setRecipient(r.id);Keyboard.dismiss()}} style={s.person}>
    <View style={s.avatar}><Ionicons name="person-outline" size={18} color={colors.primary}/></View>
    <View style={s.personName}><Text style={s.name}>{r.name}</Text><Text style={s.role}>{r.role}</Text></View>
    <Ionicons name="chevron-forward" size={18} color={colors.muted}/>
   </Pressable>)}
   {!loading&&!error&&!chosen&&matches.length===0&&<Text style={s.empty}>{eligible.length?'No recipients match your search.':'No eligible recipients are available right now.'}</Text>}
   {chosen&&<Text style={s.hint}>Write your first message to start the conversation. CTSMD verifies messaging permissions when you send.</Text>}
  </ScrollView>
  <View style={s.composer}>
   <TextInput accessibilityLabel="Message body" placeholder={chosen?'Write a message':'Choose a recipient first'} placeholderTextColor={colors.muted} editable={!!chosen&&!busy} multiline maxLength={4000} value={message} onChangeText={setMessage} style={s.input}/>
   <Pressable accessibilityLabel="Start conversation" disabled={!recipient||!message.trim()||busy} onPress={()=>void send()} style={[s.send,(!recipient||!message.trim()||busy)&&s.disabled]}><Ionicons name="arrow-up" color="#fff" size={23}/></Pressable>
  </View>
 </ConversationViewport>;
}
const s=StyleSheet.create({
 header:{minHeight:62,flexDirection:'row',alignItems:'center',gap:8,paddingHorizontal:12,backgroundColor:colors.card,borderBottomWidth:1,borderBottomColor:colors.border},back:{width:48,alignItems:'center'},title:{fontSize:20,fontWeight:'800',color:colors.navy},
 search:{margin:12,marginBottom:4,flexDirection:'row',alignItems:'center',gap:8,paddingHorizontal:12,minHeight:50,backgroundColor:colors.card,borderWidth:1,borderColor:colors.border,borderRadius:12},searchInput:{flex:1,color:colors.text,fontSize:16,paddingVertical:8},
 selected:{marginHorizontal:12,marginTop:6,backgroundColor:colors.ivory,flexDirection:'row',alignItems:'center',gap:8,padding:11,borderRadius:12},selectedName:{flex:1,fontWeight:'700',color:colors.navy},
 list:{flex:1},listContent:{padding:12,gap:6,paddingBottom:24},person:{flexDirection:'row',alignItems:'center',gap:10,minHeight:61,paddingHorizontal:12,backgroundColor:colors.card,borderRadius:12,borderWidth:1,borderColor:colors.border},avatar:{width:36,height:36,borderRadius:18,backgroundColor:colors.ivory,alignItems:'center',justifyContent:'center'},personName:{flex:1},name:{fontSize:16,fontWeight:'700',color:colors.text},role:{fontSize:13,color:colors.muted},empty:{paddingVertical:20,textAlign:'center',color:colors.muted},hint:{fontSize:14,color:colors.muted,lineHeight:21},
 composer:{flexDirection:'row',alignItems:'flex-end',gap:8,paddingHorizontal:12,paddingVertical:10,backgroundColor:colors.card,borderTopWidth:1,borderTopColor:colors.border},input:{flex:1,maxHeight:130,minHeight:48,borderRadius:21,borderWidth:1,borderColor:colors.border,paddingHorizontal:15,paddingVertical:10,fontSize:16,color:colors.text},send:{height:48,width:48,borderRadius:24,backgroundColor:colors.primary,alignItems:'center',justifyContent:'center'},disabled:{opacity:0.45}
});
