import React,{useCallback} from 'react';
import {router,useLocalSearchParams} from 'expo-router';
import {Ionicons} from '@expo/vector-icons';
import {Pressable,StyleSheet,Text,View} from 'react-native';
import {Screen} from '../../src/components/Screen';
import {LoadState,useScreenData} from '../../src/components/LoadState';
import {channel} from '../../src/api/client';
import {colors} from '../../src/theme/colors';

export default function Channel(){
 const {id}=useLocalSearchParams<{id:string}>();
 const load=useCallback(()=>{const value=Number(id);if(!Number.isSafeInteger(value)||value<1)throw Error('Invalid channel');return channel(value)},[id]);
 const {data,error,loading}=useScreenData(load);
 return <Screen title={data?.channel.name||'Channel'}>
  <Pressable accessibilityRole="button" style={s.back} onPress={()=>router.back()}><Ionicons name="chevron-back" size={18} color={colors.primary}/><Text style={s.backText}>Community</Text></Pressable>
  {!!data?.channel.description&&<Text style={s.muted}>{data.channel.description}</Text>}
  <LoadState loading={loading} error={error}/>
  {data&&data.posts.length===0&&<Text style={s.muted}>No posts in this channel yet.</Text>}
  {data?.posts.slice().sort((a,b)=>Number(b.pinned)-Number(a.pinned)||b.created_at.localeCompare(a.created_at)||b.id-a.id).map(p=><View style={s.post} key={p.id}>
    <View style={s.authorRow}><View style={s.avatar}><Text style={s.avatarText}>{p.initials||'?'}</Text></View><View style={s.authorMeta}><Text style={s.author}>{p.author}</Text><Text style={s.muted}>{p.created_at}</Text></View>{!!p.pinned&&<Ionicons name="pin" color={colors.primary} size={18}/>}</View>
    <Text style={s.body}>{p.body}</Text>
   </View>)}
 </Screen>;
}
const s=StyleSheet.create({back:{flexDirection:'row',alignItems:'center',gap:4},backText:{color:colors.primary,fontWeight:'700'},muted:{color:colors.muted,fontSize:12},post:{backgroundColor:colors.card,borderWidth:1,borderColor:colors.border,borderRadius:14,padding:14,gap:12},authorRow:{flexDirection:'row',alignItems:'center',gap:10},avatar:{height:36,width:36,borderRadius:18,backgroundColor:'#e8edf3',alignItems:'center',justifyContent:'center'},avatarText:{color:colors.navy,fontWeight:'700',fontSize:12},authorMeta:{flex:1,gap:3},author:{color:colors.text,fontWeight:'700'},body:{color:colors.text,fontSize:15,lineHeight:22}});
