import React,{useCallback,useState} from 'react';
import {Alert,FlatList,KeyboardAvoidingView,Platform,Pressable,StyleSheet,Text,TextInput,View} from 'react-native';
import {SafeAreaView,useSafeAreaInsets} from 'react-native-safe-area-context';
import {Ionicons} from '@expo/vector-icons';
import {router,useLocalSearchParams} from 'expo-router';
import {thread,sendMessage} from '../../src/api/client';
import {LoadState,useScreenData} from '../../src/components/LoadState';
import {useAuth} from '../../src/auth/AuthProvider';
import {colors} from '../../src/theme/colors';
export default function ThreadScreen(){
 const {id}=useLocalSearchParams<{id:string}>();
 const {user}=useAuth();
 const {bottom}=useSafeAreaInsets();
 const threadId=Number(id);
 const [version,setVersion]=useState(0),[draft,setDraft]=useState(''),[busy,setBusy]=useState(false);
 const load=useCallback(()=>Number.isSafeInteger(threadId)&&threadId>0?thread(threadId):Promise.reject(new Error('Invalid conversation.')),[threadId,version]);
 const {data,error,loading}=useScreenData(load);
 const send=async()=>{const content=draft.trim();if(!content||busy)return;setBusy(true);try{await sendMessage(threadId,content);setDraft('');setVersion(v=>v+1)}catch(e){Alert.alert('Message not sent',e instanceof Error?e.message:'Please try again.')}finally{setBusy(false)}};
 return <SafeAreaView edges={['top','bottom']} style={styles.screen}>
  <KeyboardAvoidingView style={styles.flex} behavior={Platform.OS==='ios'?'padding':undefined}>
   <View style={styles.header}>
    <Pressable accessibilityRole="button" accessibilityLabel="Back to messages" onPress={()=>router.back()} hitSlop={12}><Ionicons name="chevron-back" size={26} color={colors.navy}/></Pressable>
    <View style={styles.flex}><Text style={styles.name} numberOfLines={1}>{data?.conversation.display_name||'Conversation'}</Text>{data?.conversation.conversation_type==='safeguarded'&&<Text style={styles.safeguarded}>Safeguarded</Text>}</View>
   </View>
   <LoadState loading={loading} error={error}/>
   <FlatList style={styles.flex} contentContainerStyle={styles.messages} data={data?.messages||[]} keyExtractor={m=>String(m.id)} renderItem={({item:m})=>{
    const mine=m.sender_user_id===user?.id;
    const intended=(data?.participants||[]).filter(p=>p.user_id!==user?.id&&p.participant_role!=='guardian');
    const read=mine&&intended.length>0&&intended.every(p=>Number(p.last_read_message_id)>=m.id);
    return <View style={[styles.messageRow,mine&&styles.mineRow]}>
     {!mine&&<View style={styles.initials}><Text style={styles.initialText}>{m.initials||'?'}</Text></View>}
     <View style={[styles.bubble,mine&&styles.mineBubble]}>
      <View style={styles.bubbleLine}><Text style={[styles.messageText,mine&&styles.mineText]}>{m.body}</Text>{mine&&read&&<Ionicons accessibilityLabel="Read by intended recipient" name="checkmark-done" size={13} color="#e9f3ff"/>}</View>
     </View>
    </View>;
   }} ListEmptyComponent={!loading&&!error?<Text style={styles.empty}>No messages yet.</Text>:undefined}/>
   {data&&<View style={[styles.composer,{paddingBottom:Math.max(8,bottom>0?8:16)}]}>
    <TextInput accessibilityLabel="Message" placeholder="Message" multiline maxLength={4000} value={draft} onChangeText={setDraft} style={styles.input}/>
    <Pressable accessibilityRole="button" accessibilityLabel="Send message" disabled={busy||!draft.trim()} onPress={()=>void send()} style={[styles.send,(busy||!draft.trim())&&styles.disabled]}><Ionicons name="arrow-up" color="#fff" size={22}/></Pressable>
   </View>}
  </KeyboardAvoidingView>
 </SafeAreaView>;
}
const styles=StyleSheet.create({
 screen:{flex:1,backgroundColor:colors.background},flex:{flex:1},header:{flexDirection:'row',alignItems:'center',gap:12,paddingHorizontal:14,paddingVertical:12,backgroundColor:colors.card,borderBottomColor:colors.border,borderBottomWidth:1},name:{fontSize:18,fontWeight:'700',color:colors.navy},safeguarded:{fontSize:12,color:colors.muted},
 messages:{padding:14,gap:10,flexGrow:1,justifyContent:'flex-end'},messageRow:{flexDirection:'row',alignItems:'flex-end',gap:7,maxWidth:'91%'},mineRow:{alignSelf:'flex-end',justifyContent:'flex-end'},initials:{height:28,width:28,borderRadius:14,backgroundColor:'#e5e9ef',alignItems:'center',justifyContent:'center'},initialText:{color:colors.navy,fontSize:10,fontWeight:'700'},bubble:{backgroundColor:colors.card,borderWidth:1,borderColor:colors.border,borderRadius:16,paddingVertical:9,paddingHorizontal:12,maxWidth:'100%'},mineBubble:{backgroundColor:colors.navy,borderColor:colors.navy},bubbleLine:{flexDirection:'row',alignItems:'flex-end',flexWrap:'wrap',columnGap:7,rowGap:3},messageText:{fontSize:15,color:colors.text,flexShrink:1},mineText:{color:'#fff'},timestamp:{fontSize:10,color:colors.muted,marginLeft:'auto'},mineTime:{color:'#d5e0f2'},empty:{color:colors.muted,textAlign:'center',padding:18},
 composer:{flexDirection:'row',alignItems:'flex-end',gap:8,paddingHorizontal:12,paddingTop:10,backgroundColor:colors.card,borderTopWidth:1,borderTopColor:colors.border},input:{flex:1,maxHeight:120,minHeight:42,borderRadius:21,borderWidth:1,borderColor:colors.border,paddingHorizontal:15,paddingVertical:10,fontSize:15,color:colors.text},send:{height:42,width:42,borderRadius:21,backgroundColor:colors.primary,alignItems:'center',justifyContent:'center'},disabled:{opacity:0.45}
});
