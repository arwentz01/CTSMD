import React,{useCallback,useState} from 'react';
import {Pressable,StyleSheet,Text,View} from 'react-native';
import {router,useLocalSearchParams} from 'expo-router';
import {Ionicons} from '@expo/vector-icons';
import {Screen,Item} from '../../src/components/Screen';
import {CommunityPhotos} from '../../src/components/CommunityPhotos';
import {CommunityHeart} from '../../src/components/CommunityHeart';
import {LoadState,useScreenData} from '../../src/components/LoadState';
import {channels,communityFeed} from '../../src/api/client';
import {colors} from '../../src/theme/colors';

export default function Community(){
 const [view,setView]=useState<'Feed'|'Channels'>('Feed');
 const {production_id}=useLocalSearchParams<{production_id?:string}>();
 const id=Number(production_id);
 const filtered=!!production_id&&Number.isSafeInteger(id)&&id>0;
 const load=useCallback(async()=>{const [directory,feed]=await Promise.all([channels(),communityFeed()]);return {directory,feed}},[]);
 const {data,error,loading}=useScreenData(load);
 const visible=(data?.directory.channels||[]).filter(c=>!filtered||c.production_id===id);
 const posts=(data?.feed.posts||[]).filter(p=>!filtered||p.production_id===id);
 const general=visible.filter(c=>c.production_id===null);
 const groups=new Map<number,{title:string;items:typeof visible}>();
 visible.filter(c=>c.production_id!==null).forEach(c=>{const key=c.production_id as number;if(!groups.has(key))groups.set(key,{title:c.production_title||'Production',items:[]});groups.get(key)!.items.push(c)});
 const renderChannel=(c:typeof visible[number])=><Pressable key={c.id} onPress={()=>router.push({pathname:'/community/[id]',params:{id:String(c.id)}})} accessibilityRole="button"><Item title={c.name} subtitle={(c.description||'Community channel')+(c.unread_count>0?' · '+c.unread_count+' unread':'')}/></Pressable>;
 return <Screen title="Community">
  {filtered&&<View style={s.filterHeader}><Text style={s.muted}>Production community</Text><Pressable accessibilityRole="button" onPress={()=>router.setParams({production_id:undefined})}><Text style={s.link}>View all</Text></Pressable></View>}
  <View style={s.switch}>{(['Feed','Channels'] as const).map(tab=><Pressable accessibilityRole="button" accessibilityState={{selected:view===tab}} key={tab} onPress={()=>setView(tab)} style={[s.tab,view===tab&&s.selected]}><Text style={[s.tabText,view===tab&&s.selectedText]}>{tab}</Text></Pressable>)}</View>
  <LoadState loading={loading} error={error}/>
  {data&&view==='Feed'&&<>
    {posts.length===0&&<Text style={s.muted}>No community posts available yet.</Text>}
    {posts.map(p=><View key={p.id} style={s.post}><Pressable accessibilityRole="button" onPress={()=>router.push({pathname:'/community/post/[id]',params:{id:String(p.id),channel_id:String(p.channel_id)}})}>
      <View style={s.authorRow}><View style={s.avatar}><Text style={s.avatarText}>{p.initials||'?'}</Text></View><View style={{flex:1,gap:3}}><Text style={s.author}>{p.author}</Text><Text style={s.muted}>{p.channel_name}{p.production_title?' · '+p.production_title:''}</Text></View><Ionicons name="chevron-forward" color={colors.muted} size={18}/></View>
      <Text style={s.body}>{p.body}</Text><Text style={s.muted}>{p.created_at}</Text></Pressable><CommunityPhotos attachments={p.attachments}/><CommunityHeart postId={p.id} count={p.heart_count} hearted={p.hearted}/>
    </View>)}
  </>}
  {data&&view==='Channels'&&<>
   {visible.length===0&&<Text style={s.muted}>No channels available.</Text>}
   {general.length>0&&<><Text style={s.section}>General</Text>{general.map(renderChannel)}</>}
   {Array.from(groups.entries()).map(([key,group])=><View key={key} style={{gap:10}}><Text style={s.section}>{group.title}</Text>{group.items.map(renderChannel)}</View>)}
  </>}
 </Screen>;
}
const s=StyleSheet.create({filterHeader:{flexDirection:'row',justifyContent:'space-between',alignItems:'center'},muted:{color:colors.muted,fontSize:12},link:{color:colors.primary,fontWeight:'700'},switch:{flexDirection:'row',borderRadius:15,backgroundColor:colors.ivory,borderWidth:1,borderColor:colors.gold,padding:5},tab:{flex:1,paddingVertical:10,alignItems:'center',borderRadius:9},selected:{backgroundColor:colors.burgundy},tabText:{fontSize:14,fontWeight:'700',color:colors.muted},selectedText:{color:colors.ivory},section:{fontFamily:'Georgia',fontSize:25,fontWeight:'700',color:colors.navy},post:{backgroundColor:colors.card,borderColor:colors.border,borderWidth:1,borderLeftWidth:4,borderLeftColor:colors.primary,borderRadius:16,padding:17,gap:12},authorRow:{flexDirection:'row',gap:10,alignItems:'center'},avatar:{height:43,width:43,borderRadius:22,alignItems:'center',justifyContent:'center',backgroundColor:colors.burgundy,borderColor:colors.gold,borderWidth:2},avatarText:{fontFamily:'Georgia',fontSize:15,fontWeight:'700',color:colors.ivory},author:{color:colors.text,fontFamily:'Georgia',fontWeight:'700',fontSize:19},body:{color:colors.text,fontSize:15,lineHeight:22}});
