import {TouchTarget as Pressable} from '../../src/components/TouchTarget';
import React,{useCallback,useState} from 'react';
import {Alert,StyleSheet,Text,TextInput,View} from 'react-native';
import {Ionicons} from '@expo/vector-icons';
import {router} from 'expo-router';
import {Screen} from '../../src/components/Screen';
import {StageBackdrop} from '../../src/components/StageBackdrop';
import {LoadState,useScreenData} from '../../src/components/LoadState';
import {conversations,archiveConversation} from '../../src/api/client';
import {colors} from '../../src/theme/colors';

export default function Messages(){
 const [search,setSearch]=useState(''),[appliedSearch,setAppliedSearch]=useState('');
 const [scope,setScope]=useState<'inbox'|'archived'|'all'>('inbox');
 const archived=scope==='archived';
 const [busy,setBusy]=useState<number|null>(null);
 const load=useCallback(()=>conversations({q:appliedSearch,scope}),[appliedSearch,scope]);
 const {data,error,loading,retry}=useScreenData(load);
 const changeArchive=(id:number,shouldArchive:boolean)=>{
  Alert.alert(shouldArchive?'Archive conversation?':'Restore conversation?',
   shouldArchive?'This removes the conversation from your inbox, not anyone else’s. New incoming messages will return it to your inbox.':'Move this conversation back to your inbox?',
   [{text:'Cancel',style:'cancel'},{text:shouldArchive?'Archive':'Restore',onPress:()=>void (async()=>{
    setBusy(id);try{await archiveConversation(id,shouldArchive);retry();}
    catch(e){Alert.alert('Unable to update conversation',e instanceof Error?e.message:'Please try again.');}
    finally{setBusy(null);}
   })()}]);
 };
 return <Screen title="Messages">
  <Text style={s.intro}>Search conversations and hold one to archive or restore it.</Text>
  <Pressable accessibilityRole="button" onPress={()=>router.push('/messages/new')} style={s.create}><StageBackdrop/><Ionicons name="add" color={colors.gold} size={27}/><Text style={s.createText}>New conversation</Text><Ionicons name="chatbubbles-outline" size={23} color={colors.gold}/></Pressable>
  <View style={s.searchBar}>
   <Ionicons name="search-outline" size={21} color={colors.muted}/>
   <TextInput accessibilityLabel="Search conversations and messages" placeholder="Search people or message text" placeholderTextColor={colors.muted} value={search} onChangeText={setSearch} style={s.searchInput} returnKeyType="search" onSubmitEditing={()=>setAppliedSearch(search.trim())}/>
   {!!search&&<Pressable accessibilityRole="button" accessibilityLabel="Clear search" onPress={()=>{setSearch('');setAppliedSearch('')}}><Ionicons name="close-circle" color={colors.muted} size={21}/></Pressable>}
   <Pressable accessibilityRole="button" accessibilityLabel="Search messages" onPress={()=>setAppliedSearch(search.trim())} style={s.searchButton}><Ionicons name="arrow-forward" color="#fff" size={20}/></Pressable>
  </View>
  <View style={s.tabs}>
   <Pressable accessibilityRole="tab" accessibilityState={{selected:scope==='inbox'}} style={[s.tab,scope==='inbox'&&s.tabActive]} onPress={()=>setScope('inbox')}><Text style={[s.tabText,scope==='inbox'&&s.tabTextActive]}>Inbox</Text></Pressable>
   <Pressable accessibilityRole="tab" accessibilityState={{selected:archived}} style={[s.tab,archived&&s.tabActive]} onPress={()=>setScope('archived')}><Text style={[s.tabText,archived&&s.tabTextActive]}>Archived</Text></Pressable>
   <Pressable accessibilityRole="tab" accessibilityState={{selected:scope==='all'}} style={[s.tab,scope==='all'&&s.tabActive]} onPress={()=>setScope('all')}><Text style={[s.tabText,scope==='all'&&s.tabTextActive]}>All</Text></Pressable>
  </View>
  <LoadState loading={loading} error={error} onRetry={retry}/>
  {data?.conversations.map(c=><View key={c.id} style={s.card}>
   <Pressable accessibilityRole="button" accessibilityLabel={'Open conversation with '+(c.display_name||'Conversation')} onPress={()=>router.push({pathname:'/messages/[id]',params:{id:String(c.id)}})} onLongPress={()=>changeArchive(c.id,!c.archived_at)} accessibilityHint="Press and hold for archive or restore options" style={s.open}>
    <View style={s.avatar}><Text style={s.initials}>{(c.display_name||'C').split(' ').slice(0,2).map(x=>x[0]||'').join('').toUpperCase()}</Text></View>
    <View style={s.content}><Text style={s.name} numberOfLines={1}>{c.display_name||'Conversation'}</Text><Text style={s.preview} numberOfLines={2}>{c.preview||'No visible messages'}</Text></View>
    {c.unread_count>0&&<View style={s.unread}><Text style={s.unreadText}>{c.unread_count}</Text></View>}
    <Ionicons name="chevron-forward" color={colors.gold} size={21}/>
   </Pressable>

  </View>)}
  {data?.conversations.length===0&&<Text style={s.preview}>{appliedSearch?'No messages or conversations match that search.':archived?'No archived conversations.':'No conversations yet. Start one to connect with your community.'}</Text>}
 </Screen>;
}
const s=StyleSheet.create({
 intro:{color:colors.muted,fontSize:14},create:{backgroundColor:colors.burgundy,borderColor:colors.gold,borderWidth:2,borderRadius:17,paddingVertical:10,paddingHorizontal:14,flexDirection:'row',alignItems:'center',justifyContent:'space-between',gap:12},createText:{color:colors.ivory,fontWeight:'700',fontSize:16,flex:1},
 searchBar:{flexDirection:'row',alignItems:'center',backgroundColor:colors.card,borderWidth:1,borderColor:colors.border,borderRadius:12,paddingLeft:12,gap:8,minHeight:49},searchInput:{flex:1,color:colors.text,fontSize:15,paddingVertical:8},searchButton:{backgroundColor:colors.primary,borderTopRightRadius:11,borderBottomRightRadius:11,minWidth:49,minHeight:48,alignItems:'center',justifyContent:'center'},tabs:{flexDirection:'row',gap:8},tab:{flex:1,alignItems:'center',paddingVertical:11,borderRadius:10,backgroundColor:colors.card,borderWidth:1,borderColor:colors.border},tabActive:{backgroundColor:colors.burgundy,borderColor:colors.burgundy},tabText:{color:colors.text,fontWeight:'700'},tabTextActive:{color:colors.ivory},
 card:{backgroundColor:colors.card,borderColor:colors.border,borderWidth:1,borderRadius:17,overflow:'hidden'},open:{flexDirection:'row',alignItems:'center',gap:10,padding:12,minHeight:68},avatar:{height:42,width:42,borderRadius:21,borderWidth:2,borderColor:colors.gold,backgroundColor:colors.burgundy,alignItems:'center',justifyContent:'center'},initials:{color:colors.ivory,fontWeight:'700',fontSize:16},content:{flex:1,gap:5},name:{color:colors.text,fontWeight:'700',fontSize:16},preview:{color:colors.muted,fontSize:14},unread:{minWidth:20,height:20,borderRadius:10,backgroundColor:colors.primary,paddingHorizontal:5,alignItems:'center',justifyContent:'center'},unreadText:{fontSize:12,fontWeight:'700',color:'#fff'},
});
