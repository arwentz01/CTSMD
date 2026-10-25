import React,{useCallback} from 'react';
import {Pressable,StyleSheet,Text,View} from 'react-native';
import {Ionicons} from '@expo/vector-icons';
import {router} from 'expo-router';
import {Screen} from '../../src/components/Screen';
import {LoadState,useScreenData} from '../../src/components/LoadState';
import {conversations} from '../../src/api/client';
import {colors} from '../../src/theme/colors';
export default function Messages(){
 const load=useCallback(()=>conversations(),[]);const {data,error,loading}=useScreenData(load);
 return <Screen title="Messages">
  <Text style={s.intro}>Stay connected with your theatre community.</Text>
  <Pressable accessibilityRole="button" onPress={()=>router.push('/messages/new')} style={s.create}><Ionicons name="add" color={colors.gold} size={27}/><Text style={s.createText}>New conversation</Text><Ionicons name="chatbubbles-outline" size={23} color={colors.gold}/></Pressable>
  <LoadState loading={loading} error={error}/>
  {data?.conversations.map(c=><Pressable key={c.id} accessibilityRole="button" onPress={()=>router.push({pathname:'/messages/[id]',params:{id:String(c.id)}})} style={s.card}>
   <View style={s.avatar}><Text style={s.initials}>{(c.display_name||'C').split(' ').slice(0,2).map(x=>x[0]||'').join('').toUpperCase()}</Text></View>
   <View style={s.content}><Text style={s.name} numberOfLines={1}>{c.display_name||'Conversation'}</Text><Text style={s.preview} numberOfLines={2}>{c.preview||'No messages yet'}</Text></View>
   {c.unread_count>0&&<View style={s.unread}><Text style={s.unreadText}>{c.unread_count}</Text></View>}
   <Ionicons name="chevron-forward" color={colors.gold} size={21}/>
  </Pressable>)}
  {data?.conversations.length===0&&<Text style={s.preview}>No conversations yet. Start one to connect with your theatre community.</Text>}
 </Screen>
}
const s=StyleSheet.create({intro:{color:colors.muted,fontSize:14,marginBottom:10},create:{backgroundColor:colors.burgundy,borderColor:colors.gold,borderWidth:2,borderRadius:17,paddingVertical:19,paddingHorizontal:20,flexDirection:'row',alignItems:'center',justifyContent:'space-between',gap:12,marginBottom:12},createText:{color:colors.ivory,fontFamily:'Georgia',fontWeight:'700',fontSize:21,flex:1},card:{backgroundColor:colors.card,borderColor:colors.border,borderWidth:1,borderRadius:17,flexDirection:'row',alignItems:'center',gap:13,padding:17,minHeight:94},avatar:{height:55,width:55,borderRadius:28,borderWidth:2,borderColor:colors.gold,backgroundColor:colors.burgundy,alignItems:'center',justifyContent:'center'},initials:{color:colors.ivory,fontFamily:'Georgia',fontWeight:'700',fontSize:20},content:{flex:1,gap:5},name:{color:colors.text,fontFamily:'Georgia',fontWeight:'700',fontSize:20},preview:{color:colors.muted,fontSize:14},unread:{minWidth:20,height:20,borderRadius:10,backgroundColor:colors.primary,paddingHorizontal:5,alignItems:'center',justifyContent:'center'},unreadText:{fontSize:11,fontWeight:'700',color:'#fff'}});
