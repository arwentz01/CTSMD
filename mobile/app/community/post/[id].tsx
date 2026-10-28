import {TouchTarget as Pressable} from '../../../src/components/TouchTarget';
import {CommunityBody} from '../../../src/components/CommunityBody';
import React,{useCallback,useState} from 'react';
import {Alert,StyleSheet,Text,TextInput,View} from 'react-native';
import {router,useLocalSearchParams} from 'expo-router';
import {Screen} from '../../../src/components/Screen';
import {CommunityPhotos} from '../../../src/components/CommunityPhotos';
import {CommunityHeart} from '../../../src/components/CommunityHeart';
import {LoadState,useScreenData} from '../../../src/components/LoadState';
import {channel,channelReplies,createChannelReply} from '../../../src/api/client';
import {colors} from '../../../src/theme/colors';
export default function CommunityPost(){
 const {id,channel_id}=useLocalSearchParams<{id:string;channel_id:string}>();
 const postId=Number(id),channelId=Number(channel_id);
 const [draft,setDraft]=useState(''),[busy,setBusy]=useState(false),[version,setVersion]=useState(0);
 const load=useCallback(async()=>{
  if(!Number.isSafeInteger(postId)||postId<1||!Number.isSafeInteger(channelId)||channelId<1)throw Error('Invalid post.');
  const [c,r]=await Promise.all([channel(channelId),channelReplies(postId)]);
  const post=c.posts.find(p=>p.id===postId);
  if(!post)throw Error('Post not available.');
  return {channel:c.channel,post,replies:r.replies};
 },[postId,channelId,version]);
 const {data,error,loading,retry}=useScreenData(load);
 const submit=async()=>{const body=draft.trim();if(!body||busy)return;setBusy(true);try{const result=await createChannelReply(postId,body);if(result.status==='rejected'){Alert.alert('Reply not published','The reply was not accepted by moderation.');return;}setDraft('');setVersion(x=>x+1);if(result.status==='pending')Alert.alert('Submitted','Your reply is awaiting review.');}catch(e){Alert.alert('Unable to reply',e instanceof Error?e.message:'Try again.')}finally{setBusy(false)}};
 return <Screen title="Post">
  <Pressable accessibilityRole="button" onPress={()=>router.back()}><Text style={s.link}>‹ Back to Community</Text></Pressable>
  <LoadState loading={loading} error={error} onRetry={retry}/>
  {data&&<>
   <View style={s.card}><Text style={s.muted}>{data.channel.name}</Text><Text style={s.name}>{data.post.author}</Text><CommunityBody body={data.post.body}/><CommunityPhotos attachments={data.post.attachments}/><CommunityHeart postId={data.post.id} count={data.post.heart_count} hearted={data.post.hearted}/><Text style={s.muted}>{data.post.created_at}</Text></View>
   <Text style={s.heading}>Replies</Text>
   {data.replies.length===0&&<Text style={s.muted}>No replies yet.</Text>}
   {data.replies.map(r=><View key={r.id} style={s.card}><Text style={s.name}>{r.initials} · {r.author}</Text><CommunityBody body={r.body}/><Text style={s.muted}>{r.created_at}</Text></View>)}
   {data.channel.can_post&&<View style={s.card}><TextInput accessibilityLabel="Write a reply" placeholder="Write a reply…" multiline maxLength={4000} value={draft} onChangeText={setDraft} style={s.input}/><Pressable accessibilityRole="button" disabled={!draft.trim()||busy} onPress={()=>void submit()} style={[s.button,(!draft.trim()||busy)&&{opacity:.5}]}><Text style={s.buttonText}>{busy?'Sending…':'Reply'}</Text></Pressable></View>}
  </>}
 </Screen>;
}
const s=StyleSheet.create({card:{backgroundColor:colors.card,borderRadius:12,borderColor:colors.border,borderWidth:1,padding:14,gap:9},name:{fontWeight:'700',fontSize:14,color:colors.navy},body:{fontSize:15,lineHeight:22,color:colors.text},muted:{fontSize:12,color:colors.muted},heading:{fontSize:18,fontWeight:'700',color:colors.navy},link:{fontWeight:'700',color:colors.primary},input:{borderWidth:1,borderColor:colors.border,borderRadius:10,minHeight:72,padding:11,color:colors.text,textAlignVertical:'top'},button:{backgroundColor:colors.primary,borderRadius:10,padding:12,alignItems:'center'},buttonText:{color:'#fff',fontWeight:'700'}});
