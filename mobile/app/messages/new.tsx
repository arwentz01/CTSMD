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
 const selected=data?.recipients.find(person=>person.id===recipient);
 const people=useMemo(()=>data?.recipients.filter(person=>person.selectable&&(person.name.toLocaleLowerCase().includes(query.trim().toLocaleLowerCase())||person.role.toLocaleLowerCase().includes(query.trim().toLocaleLowerCase())))||[],[data,query]);
 async function send(){
  if(busy||!recipient||!message.trim())return;
  setBusy(true);
  try{
   const result=await createConversation(recipient,message.trim());
   if(!Number.isSafeInteger(Number(result.conversation_id))||Number(result.conversation_id)<1)throw Error('CTSMD did not return a valid conversation.');
   router.replace({pathname:'/messages/[id]',params:{id:String(result.conversation_id)}});
  }catch(e){Alert.alert('Conversation not created',e instanceof Error?e.message:'Please try again.')}
  finally{setBusy(false)}
 }
 return <ConversationViewport>
  <View style={styles.header}>
   <Pressable accessibilityLabel="Back to messages" onPress={()=>router.back()} style={styles.back}><Ionicons name="chevron-back" color={colors.navy} size={25}/></Pressable>
   <Text style={styles.heading}>New conversation</Text>
  </View>
  <ScrollView style={styles.list} contentContainerStyle={styles.content} keyboardShouldPersistTaps="always" keyboardDismissMode="on-drag">
   <Text style={styles.helper}>Find an approved recipient to begin a conversation.</Text>
   <View style={styles.search}>
    <Ionicons name="search-outline" color={colors.muted} size={20}/>
    <TextInput accessibilityLabel="Search recipients" placeholder="Search by name or role" placeholderTextColor={colors.muted} autoCorrect={false} value={query} onChangeText={setQuery} style={styles.searchInput}/>
    {!!query&&<Pressable accessibilityLabel="Clear recipient search" onPress={()=>setQuery('')}><Ionicons name="close-circle" color={colors.muted} size={19}/></Pressable>}
   </View>
   <LoadState loading={loading} error={error} onRetry={retry}/>
   {selected&&<Text style={styles.selected}>To: {selected.name}</Text>}
   {people.map(person=><Pressable key={person.id} accessibilityRole="radio" accessibilityState={{checked:recipient===person.id}} onPress={()=>{setRecipient(person.id);Keyboard.dismiss()}} style={[styles.person,recipient===person.id&&styles.activePerson]}>
    <Ionicons name={recipient===person.id?'checkmark-circle':'ellipse-outline'} color={colors.primary} size={21}/>
    <View style={{flex:1}}><Text style={styles.personName}>{person.name}</Text><Text style={styles.role}>{person.role}</Text></View>
   </Pressable>)}
   {!loading&&!error&&people.length===0&&<Text style={styles.helper}>{query?'No recipients match that search.':'No eligible recipients are available right now.'}</Text>}
  </ScrollView>
  <View style={styles.composer}>
   <TextInput accessibilityLabel="Message body" placeholder={recipient?'Write a message':'Choose a recipient first'} placeholderTextColor={colors.muted} multiline maxLength={4000} value={message} onChangeText={setMessage} style={styles.input}/>
   <Pressable accessibilityLabel="Start conversation" disabled={!recipient||!message.trim()||busy} onPress={()=>void send()} style={[styles.send,(!recipient||!message.trim()||busy)&&styles.disabled]}>
    <Ionicons name="arrow-up" color="#fff" size={23}/>
   </Pressable>
  </View>
 </ConversationViewport>;
}
const styles=StyleSheet.create({
 header:{minHeight:62,paddingHorizontal:12,flexDirection:'row',gap:10,alignItems:'center',backgroundColor:colors.card,borderBottomWidth:1,borderBottomColor:colors.border},
 back:{width:44,alignItems:'center'},heading:{color:colors.navy,fontSize:19,fontWeight:'800'},
 list:{flex:1},content:{padding:16,gap:10,paddingBottom:24},
 helper:{color:colors.muted,fontSize:14,lineHeight:20},
 search:{flexDirection:'row',alignItems:'center',gap:8,borderRadius:12,borderWidth:1,borderColor:colors.border,backgroundColor:colors.card,paddingHorizontal:12,minHeight:50},
 searchInput:{flex:1,fontSize:15,color:colors.text,minHeight:48},
 selected:{color:colors.primary,fontWeight:'700',marginVertical:2},
 person:{flexDirection:'row',alignItems:'center',gap:12,padding:12,borderRadius:12,borderWidth:1,borderColor:colors.border,backgroundColor:colors.card},
 activePerson:{borderColor:colors.primary,backgroundColor:colors.ivory},
 personName:{fontWeight:'700',color:colors.text,fontSize:15},role:{fontSize:13,color:colors.muted,marginTop:3},
 composer:{flexDirection:'row',alignItems:'flex-end',gap:9,backgroundColor:colors.card,paddingHorizontal:12,paddingTop:10,paddingBottom:10,borderTopWidth:1,borderTopColor:colors.border},
 input:{flex:1,minHeight:48,maxHeight:125,borderRadius:22,borderWidth:1,borderColor:colors.border,paddingHorizontal:14,paddingVertical:10,fontSize:15,color:colors.text},
 send:{width:48,height:48,borderRadius:24,backgroundColor:colors.primary,alignItems:'center',justifyContent:'center'},disabled:{opacity:0.45}
});
