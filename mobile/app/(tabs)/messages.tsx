import {TouchTarget as Pressable} from '../../src/components/TouchTarget';
import React,{useCallback,useEffect,useState} from 'react';
import {Alert,StyleSheet,Text,TextInput,View} from 'react-native';
import {Ionicons} from '@expo/vector-icons';
import {router} from 'expo-router';
import {Screen} from '../../src/components/Screen';
import {LoadState,useScreenData} from '../../src/components/LoadState';
import {conversations,archiveConversation} from '../../src/api/client';
import {colors} from '../../src/theme/colors';

export default function Messages(){
 const [search,setSearch]=useState('');
 const [appliedSearch,setAppliedSearch]=useState('');
 const [archived,setArchived]=useState(false);
 const [busy,setBusy]=useState<number|null>(null);
 useEffect(()=>{
  const timer=setTimeout(()=>setAppliedSearch(search.trim()),280);
  return ()=>clearTimeout(timer);
 },[search]);
 const load=useCallback(()=>conversations({q:appliedSearch,scope:archived?'archived':'inbox'}),[appliedSearch,archived]);
 const {data,error,loading,retry}=useScreenData(load);
 const changeArchive=(id:number,shouldArchive:boolean)=>{
  if(busy!==null)return;
  Alert.alert(shouldArchive?'Archive conversation?':'Restore conversation?',
   shouldArchive?'This hides the conversation from your inbox. New messages will return it to your inbox.':'Return this conversation to your inbox?',
   [{text:'Cancel',style:'cancel'},{text:shouldArchive?'Archive':'Restore',onPress:()=>void (async()=>{
    setBusy(id);
    try{await archiveConversation(id,shouldArchive);retry();}
    catch(e){Alert.alert('Unable to update conversation',e instanceof Error?e.message:'Please try again.');}
    finally{setBusy(null);}
   })()}]);
 };
 return <Screen title="Messages" compact headerAction={
  <Pressable accessibilityRole="button" accessibilityLabel="New conversation" onPress={()=>router.push('/messages/new')} style={s.compose}>
   <Ionicons name="create-outline" color={colors.primary} size={24}/>
  </Pressable>
 }>
  <View style={s.toolbar}>
   <View style={s.searchBar}>
    <Ionicons name="search-outline" size={20} color={colors.muted}/>
    <TextInput accessibilityLabel="Search conversations and messages" placeholder="Search messages" placeholderTextColor={colors.muted} value={search} onChangeText={setSearch} style={s.searchInput} returnKeyType="search" onSubmitEditing={()=>setAppliedSearch(search.trim())}/>
    {!!search&&<Pressable accessibilityRole="button" accessibilityLabel="Clear search" onPress={()=>{setSearch('');setAppliedSearch('')}} style={s.clear}><Ionicons name="close-circle" color={colors.muted} size={20}/></Pressable>}
   </View>
   <Pressable accessibilityRole="button" accessibilityLabel={archived?'Return to inbox':'View archived conversations'} accessibilityState={{selected:archived}} onPress={()=>setArchived(value=>!value)} style={[s.archiveToggle,archived&&s.archiveSelected]}>
    <Ionicons name={archived?'mail-outline':'archive-outline'} size={21} color={archived?colors.ivory:colors.primary}/>
   </Pressable>
  </View>
  {archived&&<Text style={s.archiveHint}>Archived conversations</Text>}
  <LoadState loading={loading} error={error} onRetry={retry}/>
  {!!data?.conversations.length&&<View style={s.list}>
   {data.conversations.map(c=><Pressable key={c.id} accessibilityRole="button" accessibilityLabel={'Open conversation with '+(c.display_name||'Conversation')} accessibilityHint="Press and hold to archive or restore this conversation" disabled={busy!==null} onPress={()=>router.push({pathname:'/messages/[id]',params:{id:String(c.id)}})} onLongPress={()=>changeArchive(c.id,!c.archived_at)} style={s.row}>
    <View style={s.avatar}><Text style={s.initials}>{(c.display_name||'C').split(' ').slice(0,2).map(x=>x[0]||'').join('').toUpperCase()}</Text></View>
    <View style={s.content}><Text style={s.name} numberOfLines={1}>{c.display_name||'Conversation'}</Text><Text style={s.preview} numberOfLines={1}>{c.preview||'No visible messages'}</Text></View>
    {c.unread_count>0&&<View style={s.unread}><Text style={s.unreadText}>{c.unread_count}</Text></View>}
   </Pressable>)}
  </View>}
  {data?.conversations.length===0&&!loading&&!error&&<Text style={s.empty}>{search.trim()?'No conversations match your search.':archived?'No archived conversations.':'No conversations yet. Use the compose icon to start one.'}</Text>}
 </Screen>;
}
const s=StyleSheet.create({
 compose:{width:44,height:44,alignItems:'center',justifyContent:'center',borderRadius:12,backgroundColor:colors.card,borderWidth:1,borderColor:colors.border},
 toolbar:{flexDirection:'row',alignItems:'center',gap:9},
 searchBar:{flex:1,flexDirection:'row',alignItems:'center',backgroundColor:colors.card,borderWidth:1,borderColor:colors.border,borderRadius:12,paddingHorizontal:12,gap:9,minHeight:46},
 searchInput:{flex:1,color:colors.text,fontSize:15,paddingVertical:8,minHeight:44},
 clear:{width:34,height:44,alignItems:'center',justifyContent:'center'},
 archiveToggle:{width:46,height:46,borderRadius:12,alignItems:'center',justifyContent:'center',backgroundColor:colors.card,borderWidth:1,borderColor:colors.border},
 archiveSelected:{backgroundColor:colors.primary,borderColor:colors.primary},
 archiveHint:{color:colors.primary,fontWeight:'700',fontSize:13},
 list:{backgroundColor:colors.card,borderRadius:13,borderWidth:1,borderColor:colors.border,overflow:'hidden'},
 row:{flexDirection:'row',alignItems:'center',gap:11,paddingHorizontal:12,paddingVertical:10,minHeight:67,borderBottomWidth:StyleSheet.hairlineWidth,borderBottomColor:colors.border},
 avatar:{height:40,width:40,borderRadius:20,borderWidth:1.5,borderColor:colors.gold,backgroundColor:colors.burgundy,alignItems:'center',justifyContent:'center'},
 initials:{color:colors.ivory,fontWeight:'700',fontSize:14},
 content:{flex:1,gap:4},name:{color:colors.text,fontWeight:'700',fontSize:15},preview:{color:colors.muted,fontSize:14},
 unread:{minWidth:21,height:21,borderRadius:11,backgroundColor:colors.primary,paddingHorizontal:5,alignItems:'center',justifyContent:'center'},
 unreadText:{fontSize:12,fontWeight:'700',color:'#fff'},
 empty:{color:colors.muted,fontSize:14,paddingHorizontal:4,paddingVertical:10}
});
