import {TouchTarget as Pressable} from '../../src/components/TouchTarget';
import {CommunityBody} from '../../src/components/CommunityBody';
import React,{useCallback,useState} from 'react';
import {StyleSheet,Text,View} from 'react-native';
import {router,useLocalSearchParams} from 'expo-router';
import {Ionicons} from '@expo/vector-icons';
import {Screen,Item} from '../../src/components/Screen';
import {CommunityPhotos} from '../../src/components/CommunityPhotos';
import {CommunityHeart} from '../../src/components/CommunityHeart';
import {LoadState,useScreenData} from '../../src/components/LoadState';
import {channels,communityFeed,type CommunityFeedPost} from '../../src/api/client';
import {colors} from '../../src/theme/colors';

function shortDate(value:string):string {
 const match=value.match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
 if(!match)return value;
 const [,year,month,day,hour,minute]=match;
 const months=['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
 const label=months[Number(month)-1];
 if(!label)return value;
 const h=Number(hour);
 return `${label} ${Number(day)}${Number(year)!==new Date().getFullYear()?', '+year:''} · ${h%12||12}:${minute} ${h<12?'AM':'PM'}`;
}
export default function Community(){
 const [view,setView]=useState<'Feed'|'Channels'>('Feed');
 const [more,setMore]=useState<CommunityFeedPost[]>([]),[cursor,setCursor]=useState<string|null>(null),[started,setStarted]=useState(false),[hasMore,setHasMore]=useState(true),[paging,setPaging]=useState(false);
 const {production_id}=useLocalSearchParams<{production_id?:string}>();
 const id=Number(production_id);
 const filtered=!!production_id&&Number.isSafeInteger(id)&&id>0;
 const load=useCallback(async()=>{const [directory,feed]=await Promise.all([channels(),communityFeed()]);return {directory,feed}},[]);
 const {data,error,loading,retry}=useScreenData(load,true);
 const visible=(data?.directory.channels||[]).filter(c=>!filtered||c.production_id===id);
 const posts=[...(data?.feed.posts||[]),...more.filter(p=>(data?.directory.channels||[]).some(c=>Number(c.id)===Number(p.channel_id))&&!(data?.feed.posts||[]).some(first=>first.id===p.id))];
 const nextPage=async()=>{if(paging)return;setPaging(true);try{const next=await communityFeed(cursor||data?.feed.next_cursor||undefined);setMore(old=>[...old,...next.posts.filter(p=>!old.some(item=>item.id===p.id))]);setCursor(next.next_cursor);setStarted(true);setHasMore(next.has_more)}catch{ /* Keep existing posts available; retry using the same control. */ }finally{setPaging(false)}};
 const general=visible.filter(c=>c.production_id===null);
 const groups=new Map<number,{title:string;items:typeof visible}>();
 visible.filter(c=>c.production_id!==null).forEach(c=>{const key=c.production_id as number;if(!groups.has(key))groups.set(key,{title:c.production_title||'Production',items:[]});groups.get(key)!.items.push(c)});
 const renderChannel=(c:typeof visible[number])=><Pressable key={c.id} onPress={()=>router.push({pathname:'/community/[id]',params:{id:String(c.id)}})} accessibilityRole="button"><Item title={c.name} subtitle={(c.description||'Community channel')+(c.unread_count>0?' · '+c.unread_count+' unread':'')}/></Pressable>;
 return <Screen title="Community" compact>
  {filtered&&view==='Channels'&&<View style={s.filterHeader}><Text style={s.muted}>Production community</Text><Pressable accessibilityRole="button" onPress={()=>router.setParams({production_id:undefined})}><Text style={s.link}>View all</Text></Pressable></View>}
  <View style={s.switch}>{(['Feed','Channels'] as const).map(tab=><Pressable accessibilityRole="button" accessibilityState={{selected:view===tab}} key={tab} onPress={()=>setView(tab)} style={[s.tab,view===tab&&s.selected]}><Text style={[s.tabText,view===tab&&s.selectedText]}>{tab}</Text></Pressable>)}</View>
  <LoadState loading={loading} error={error} onRetry={retry}/>
  {data&&view==='Feed'&&<>
    {posts.length===0&&<Text style={s.muted}>No community posts available yet.</Text>}
    {posts.map(p=><View key={p.id} style={s.post}>
      <Pressable accessibilityRole="button" accessibilityLabel={'Open post by '+p.author+' in '+p.channel_name} onPress={()=>router.push({pathname:'/community/[id]',params:{id:String(p.channel_id),post_id:String(p.id)}})} style={s.postOpen}>
        <View style={s.authorRow}>
          <View style={s.avatar}><Text style={s.avatarText}>{p.initials||'?'}</Text></View>
          <View style={s.authorDetails}>
            <Text numberOfLines={1} style={s.author}>{p.author}</Text>
            <Text numberOfLines={1} style={s.channel}>{p.channel_name}{p.production_title?' · '+p.production_title:''}</Text>
          </View>
          <Ionicons name="chevron-forward" color={colors.muted} size={17}/>
        </View>
        <CommunityBody body={p.body} numberOfLines={4}/>
      </Pressable>
      <CommunityPhotos attachments={p.attachments}/>
      <Text style={s.postDate}>{shortDate(p.created_at)}</Text>
    </View>)}
    {(started?hasMore:data.feed.has_more)&&<Pressable accessibilityRole="button" onPress={()=>void nextPage()}><Text style={s.link}>{paging?'Loading…':'Load more posts'}</Text></Pressable>}
  </>}
  {data&&view==='Channels'&&<>
   {visible.length===0&&<Text style={s.muted}>No channels available.</Text>}
   {general.length>0&&<><Text style={s.section}>General</Text>{general.map(renderChannel)}</>}
   {Array.from(groups.entries()).map(([key,group])=><View key={key} style={{gap:10}}><Text style={s.section}>{group.title}</Text>{group.items.map(renderChannel)}</View>)}
  </>}
 </Screen>;
}
const s=StyleSheet.create({
 filterHeader:{flexDirection:'row',justifyContent:'space-between',alignItems:'center'},
 muted:{color:colors.muted,fontSize:12},preview:{fontSize:14,lineHeight:20,color:colors.text},
 link:{color:colors.primary,fontWeight:'700'},
 switch:{flexDirection:'row',borderRadius:11,backgroundColor:colors.ivory,borderWidth:1,borderColor:colors.border,padding:3},
 tab:{flex:1,minHeight:42,alignItems:'center',justifyContent:'center',borderRadius:8},
 selected:{backgroundColor:colors.burgundy},
 tabText:{fontSize:14,fontWeight:'700',color:colors.muted},
 selectedText:{color:colors.ivory},
 section:{fontSize:18,fontWeight:'700',color:colors.navy},
 post:{backgroundColor:colors.card,borderColor:colors.border,borderWidth:1,borderLeftWidth:3,borderLeftColor:colors.primary,borderRadius:12,paddingHorizontal:10,paddingVertical:8,gap:2},
 postOpen:{gap:5,minHeight:48,justifyContent:'center'},
 authorRow:{flexDirection:'row',gap:9,alignItems:'center'},
 avatar:{height:30,width:30,borderRadius:15,alignItems:'center',justifyContent:'center',backgroundColor:colors.burgundy,borderColor:colors.gold,borderWidth:1},
 avatarText:{fontSize:12,fontWeight:'700',color:colors.ivory},
 authorDetails:{flex:1,gap:1},
 author:{color:colors.text,fontWeight:'700',fontSize:15},
 channel:{color:colors.muted,fontSize:12},
 postFooter:{flexDirection:'row',alignItems:'center',justifyContent:'space-between',gap:12,minHeight:36},
 postDate:{fontSize:11,color:colors.muted,textAlign:'right',flexShrink:1},
});
