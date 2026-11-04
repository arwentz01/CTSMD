import {TouchTarget as Pressable} from '../../src/components/TouchTarget';
import React,{useCallback,useEffect,useState} from 'react';
import {Alert,FlatList,ScrollView,StyleSheet,Text,TextInput,View} from 'react-native';
import {ConversationViewport} from '../../src/components/ConversationViewport';
import {Ionicons} from '@expo/vector-icons';
import {router,useLocalSearchParams} from 'expo-router';
import {thread,sendMessage} from '../../src/api/client';
import {LoadState,useScreenData} from '../../src/components/LoadState';
import {useAuth} from '../../src/auth/AuthProvider';
import {colors} from '../../src/theme/colors';
const formatTime=(input:string)=>{const value=new Date(input.replace(' ','T'));return Number.isFinite(value.getTime())?value.toLocaleTimeString(undefined,{hour:'numeric',minute:'2-digit'}):input;};
export default function ThreadScreen(){
 const {id}=useLocalSearchParams<{id:string}>();
 const {user}=useAuth();
 const threadId=Number(id);
 const [participantsOpen,setParticipantsOpen]=useState(false);
 useEffect(()=>setParticipantsOpen(false),[threadId]);
 const [version,setVersion]=useState(0),[draft,setDraft]=useState(''),[busy,setBusy]=useState(false);
 const load=useCallback(()=>Number.isSafeInteger(threadId)&&threadId>0?thread(threadId):Promise.reject(new Error('Invalid conversation.')),[threadId,version]);
 const {data,error,loading,retry}=useScreenData(load);
 const safeguarded=data?.conversation.id===threadId&&data.conversation.conversation_type==='safeguarded';
 const send=async()=>{const content=draft.trim();if(!content||busy)return;setBusy(true);try{await sendMessage(threadId,content);setDraft('');setVersion(v=>v+1)}catch(e){Alert.alert('Message not sent',e instanceof Error?e.message:'Please try again.')}finally{setBusy(false)}};
 return <ConversationViewport>
   <View style={styles.header}>
    <Pressable accessibilityRole="button" accessibilityLabel="Back to messages" onPress={()=>router.back()} hitSlop={12}><Ionicons name="chevron-back" size={26} color={colors.navy}/></Pressable>
    <View style={styles.flex}><Text style={styles.name} numberOfLines={1}>{data?.conversation.display_name||'Conversation'}</Text></View>
    {safeguarded&&<Pressable accessibilityRole="button" accessibilityLabel="Safeguarding and participants" accessibilityHint="Shows who can see this conversation" accessibilityState={{expanded:participantsOpen}} onPress={()=>setParticipantsOpen(value=>!value)} style={styles.shield}><Ionicons name={participantsOpen?'shield-checkmark':'shield-checkmark-outline'} size={23} color={colors.primary}/></Pressable>}
   </View>
   <LoadState loading={loading} error={error} onRetry={retry}/>
   {safeguarded&&participantsOpen&&<View style={styles.safetyPanel}>
    <Text accessibilityRole="header" style={styles.panelTitle}>Safeguarding and participants</Text>
    <Text style={styles.panelDescription}>Conversation participants are listed below. CTSMD monitors messages for safeguarding.</Text>
    <ScrollView nestedScrollEnabled keyboardShouldPersistTaps="handled" style={styles.participantList}>
     {data.participants.map(person=><View key={person.user_id} style={styles.participant}>
      <View style={styles.participantAvatar}><Text style={styles.participantInitials}>{person.initials||person.name.split(' ').filter(Boolean).slice(0,2).map(part=>part[0]).join('').toUpperCase()||'?'}</Text></View>
      <View style={styles.flex}><Text style={styles.participantName}>{person.name}</Text><Text style={styles.participantRole}>{person.participant_role.replace(/_/g,' ').replace(/^./,letter=>letter.toUpperCase())}</Text></View>
     </View>)}
    </ScrollView>
   </View>}
   <FlatList keyboardShouldPersistTaps="handled" keyboardDismissMode="none" style={styles.flex} contentContainerStyle={styles.messages} data={data?.messages||[]} keyExtractor={m=>String(m.id)} renderItem={({item:m})=>{
    const mine=m.sender_user_id===user?.id;
    const intended=(data?.participants||[]).filter(p=>p.user_id!==user?.id&&p.participant_role!=='guardian');
    const read=mine&&intended.length>0&&intended.every(p=>Number(p.last_read_message_id)>=m.id);
    return <View style={[styles.messageRow,mine&&styles.mineRow]}>
     {!mine&&<View style={styles.initials}><Text style={styles.initialText}>{m.initials||'?'}</Text></View>}
     <View style={styles.messageContent}>
      <View style={[styles.bubble,mine&&styles.mineBubble]}>
       <Text style={[styles.messageText,mine&&styles.mineText]}>{m.body}</Text>
      </View>
      <View style={[styles.messageMeta,mine&&styles.mineMeta]}>
       <Text style={styles.timestamp}>{formatTime(m.created_at)}</Text>
       {mine&&read&&<Ionicons accessibilityLabel="Read by intended recipient" name="checkmark-done" size={13} color={colors.muted}/>}
      </View>
     </View>
    </View>;
   }} ListEmptyComponent={!loading&&!error?<Text style={styles.empty}>No messages yet.</Text>:undefined}/>
   {data&&!data.conversation.send_allowed&&<View style={{padding:16}}><Text accessibilityRole="alert" style={{color:colors.text}}>Sending is paused. {data.conversation.safety_issue}</Text></View>}{data?.conversation.send_allowed&&<View style={styles.composer}>
    <TextInput accessibilityLabel="Message" placeholder="Message" multiline maxLength={4000} value={draft} onChangeText={setDraft} style={styles.input}/>
    <Pressable accessibilityRole="button" accessibilityLabel="Send message" disabled={busy||!draft.trim()} onPress={()=>void send()} style={[styles.send,(busy||!draft.trim())&&styles.disabled]}><Ionicons name="arrow-up" color="#fff" size={22}/></Pressable>
   </View>}
 </ConversationViewport>;
}
const styles=StyleSheet.create({
 screen:{flex:1,backgroundColor:colors.background},flex:{flex:1},header:{flexDirection:'row',alignItems:'center',gap:12,paddingHorizontal:14,paddingVertical:12,backgroundColor:colors.card,borderBottomColor:colors.border,borderBottomWidth:1},name:{fontSize:18,fontWeight:'700',color:colors.navy},shield:{alignItems:'center',justifyContent:'center'},safetyPanel:{marginHorizontal:12,marginTop:8,padding:12,borderRadius:12,borderWidth:1,borderColor:colors.border,backgroundColor:colors.card,gap:6},panelTitle:{fontSize:15,fontWeight:'700',color:colors.navy},panelDescription:{fontSize:12,lineHeight:17,color:colors.muted},participantList:{maxHeight:150},participant:{flexDirection:'row',alignItems:'center',gap:9,paddingVertical:5},participantAvatar:{width:32,height:32,borderRadius:16,backgroundColor:colors.ivory,alignItems:'center',justifyContent:'center'},participantInitials:{fontSize:11,fontWeight:'700',color:colors.primary},participantName:{fontSize:14,fontWeight:'600',color:colors.text},participantRole:{fontSize:12,color:colors.muted},
 messages:{padding:14,gap:10,flexGrow:1,justifyContent:'flex-end'},messageRow:{flexDirection:'row',alignItems:'flex-end',gap:7,maxWidth:'91%'},mineRow:{alignSelf:'flex-end',justifyContent:'flex-end'},initials:{height:28,width:28,borderRadius:14,backgroundColor:'#e5e9ef',alignItems:'center',justifyContent:'center'},initialText:{color:colors.navy,fontSize:12,fontWeight:'700'},bubble:{backgroundColor:colors.card,borderWidth:1,borderColor:colors.border,borderRadius:16,paddingVertical:9,paddingHorizontal:12,maxWidth:'100%'},mineBubble:{backgroundColor:colors.navy,borderColor:colors.navy},messageContent:{flexShrink:1,alignItems:'flex-start',gap:3},messageMeta:{flexDirection:'row',alignItems:'center',gap:4,alignSelf:'flex-start'},mineMeta:{alignSelf:'flex-end'},messageText:{fontSize:15,color:colors.text,flexShrink:1},mineText:{color:'#fff'},timestamp:{fontSize:12,color:colors.muted},empty:{color:colors.muted,textAlign:'center',padding:18},
 composer:{flexDirection:'row',alignItems:'flex-end',gap:8,paddingHorizontal:12,paddingTop:10,paddingBottom:8,backgroundColor:colors.card,borderTopWidth:1,borderTopColor:colors.border},input:{flex:1,maxHeight:120,minHeight:48,borderRadius:21,borderWidth:1,borderColor:colors.border,paddingHorizontal:15,paddingVertical:10,fontSize:15,color:colors.text},send:{height:48,width:48,borderRadius:21,backgroundColor:colors.primary,alignItems:'center',justifyContent:'center'},disabled:{opacity:0.45}
});
