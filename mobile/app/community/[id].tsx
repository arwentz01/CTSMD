import React,{useCallback,useState} from 'react';
import * as ImagePicker from 'expo-image-picker';
import {router,useLocalSearchParams} from 'expo-router';
import {Ionicons} from '@expo/vector-icons';
import {Alert,Image,Pressable,StyleSheet,Text,TextInput,View} from 'react-native';
import {Screen} from '../../src/components/Screen';
import {LoadState,useScreenData} from '../../src/components/LoadState';
import {channel,createChannelPost} from '../../src/api/client';
import {colors} from '../../src/theme/colors';

export default function Channel(){
 const {id,post_id}=useLocalSearchParams<{id:string;post_id?:string}>();
 const [draft,setDraft]=useState('');const [photo,setPhoto]=useState<ImagePicker.ImagePickerAsset|null>(null);const [busy,setBusy]=useState(false);const [revision,setRevision]=useState(0);
 const load=useCallback(()=>{const value=Number(id);if(!Number.isSafeInteger(value)||value<1)throw Error('Invalid channel');return channel(value)},[id,revision]);
 const {data,error,loading}=useScreenData(load);
 const choosePhoto=async()=>{try{const selection=await ImagePicker.launchImageLibraryAsync({mediaTypes:['images'],allowsMultipleSelection:false,quality:0.8,exif:false});if(!selection.canceled&&selection.assets[0])setPhoto(selection.assets[0]);}catch(e){Alert.alert('Photo unavailable',e instanceof Error?e.message:'Unable to select photo.')}};
 const submit=async()=>{const body=draft.trim();if(!body||busy)return;setBusy(true);try{const result=await createChannelPost(Number(id),body,photo?{uri:photo.uri,fileName:photo.fileName,mimeType:photo.mimeType}:undefined);if(result.status==='rejected'){Alert.alert('Post not published','This post was not accepted by moderation.');return;}setDraft('');setPhoto(null);setRevision(v=>v+1);if(result.status==='pending')Alert.alert('Submitted','Your post is awaiting moderator review.');}catch(e){Alert.alert('Unable to post',e instanceof Error?e.message:'Please try again.')}finally{setBusy(false)}};
 return <Screen title={data?.channel.name||'Channel'}>
  <Pressable accessibilityRole="button" style={s.back} onPress={()=>router.back()}><Ionicons name="chevron-back" size={18} color={colors.primary}/><Text style={s.backText}>Community</Text></Pressable>
  {!!data?.channel.description&&<Text style={s.muted}>{data.channel.description}</Text>}
  <LoadState loading={loading} error={error}/>
  {data?.channel.can_post&&<View style={s.post}><Text style={s.author}>Share with this channel</Text><TextInput accessibilityLabel="New channel post" placeholder="Write a post..." multiline maxLength={5000} value={draft} onChangeText={setDraft} style={s.input}/>{photo&&<View style={{gap:6}}><Image source={{uri:photo.uri}} style={{height:120,width:120,borderRadius:10}}/><Pressable accessibilityRole="button" onPress={()=>setPhoto(null)}><Text style={s.backText}>Remove photo</Text></Pressable></View>}<Pressable accessibilityRole="button" onPress={()=>void choosePhoto()}><Text style={s.backText}>+ Add photo</Text></Pressable><Pressable accessibilityRole="button" disabled={!draft.trim()||busy} onPress={()=>void submit()} style={[s.submit,(!draft.trim()||busy)&&{opacity:0.5}]}><Text style={s.submitText}>{busy?'Posting…':'Post'}</Text></Pressable></View>}
  {data&&data.posts.length===0&&<Text style={s.muted}>No posts in this channel yet.</Text>}
  {post_id&&data?.posts.some(p=>String(p.id)===String(post_id))&&<Text style={s.muted}>Selected post is highlighted below.</Text>}
  {data?.posts.slice().sort((a,b)=>Number(b.pinned)-Number(a.pinned)||b.created_at.localeCompare(a.created_at)||b.id-a.id).map(p=><Pressable accessibilityRole="button" onPress={()=>router.push({pathname:'/community/post/[id]',params:{id:String(p.id),channel_id:String(id)}})} style={[s.post,String(p.id)===String(post_id)&&s.highlight]} key={p.id}>
    <View style={s.authorRow}><View style={s.avatar}><Text style={s.avatarText}>{p.initials||'?'}</Text></View><View style={s.authorMeta}><Text style={s.author}>{p.author}</Text><Text style={s.muted}>{p.created_at}</Text></View>{!!p.pinned&&<Ionicons name="pin" color={colors.primary} size={18}/>}</View>
    <Text style={s.body}>{p.body}</Text>
   </Pressable>)}
 </Screen>;
}
const s=StyleSheet.create({highlight:{borderColor:colors.primary,borderWidth:2},input:{borderWidth:1,borderColor:colors.border,borderRadius:10,padding:12,minHeight:85,textAlignVertical:'top',color:colors.text},submit:{alignSelf:'flex-end',backgroundColor:colors.primary,paddingHorizontal:22,paddingVertical:11,borderRadius:10},submitText:{color:'#fff',fontWeight:'700'},back:{flexDirection:'row',alignItems:'center',gap:4},backText:{color:colors.primary,fontWeight:'700'},muted:{color:colors.muted,fontSize:12},post:{backgroundColor:colors.card,borderWidth:1,borderColor:colors.border,borderRadius:14,padding:14,gap:12},authorRow:{flexDirection:'row',alignItems:'center',gap:10},avatar:{height:36,width:36,borderRadius:18,backgroundColor:'#e8edf3',alignItems:'center',justifyContent:'center'},avatarText:{color:colors.navy,fontWeight:'700',fontSize:12},authorMeta:{flex:1,gap:3},author:{color:colors.text,fontWeight:'700'},body:{color:colors.text,fontSize:15,lineHeight:22}});
