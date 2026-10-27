import React,{useEffect,useState} from 'react';
import {ActivityIndicator,Pressable,StyleSheet,Text,View} from 'react-native';
import {router} from 'expo-router';
import {Ionicons} from '@expo/vector-icons';
import {Screen} from '../src/components/Screen';
import {colors} from '../src/theme/colors';
import {videoLibrary,type LibraryVideo} from '../src/api/client';

export default function Videos(){
 const [items,setItems]=useState<LibraryVideo[]>([]);
 const [busy,setBusy]=useState(true);
 const [error,setError]=useState('');
 const load=async()=>{setBusy(true);setError('');try{const data=await videoLibrary();setItems(data.videos)}catch(e){setError(e instanceof Error?e.message:'Could not load videos.')}finally{setBusy(false)}};
 useEffect(()=>{void load()},[]);
 return <Screen title="Video Library">
  <Text style={styles.lead}>CTSMD videos and rehearsal material available to your account.</Text>
  {busy?<ActivityIndicator color={colors.primary}/>:null}
  {error?<View style={styles.panel}><Text style={styles.error}>{error}</Text><Pressable onPress={()=>void load()} accessibilityRole="button"><Text style={styles.action}>Retry</Text></Pressable></View>:null}
  {!busy&&!error&&!items.length?<Text style={styles.empty}>No videos are available for your account yet.</Text>:null}
  {items.map(video=><Pressable key={video.id} accessibilityRole="button" accessibilityLabel={'Play '+video.title} onPress={()=>router.push({pathname:'/videos/[id]',params:{id:String(video.id)}})} style={styles.card}>
   <View style={styles.icon}><Ionicons name="play-circle-outline" size={29} color={colors.primary}/></View>
   <View style={{flex:1,gap:4}}><Text style={styles.label}>{video.category.toUpperCase()} · {video.audience_scope==='platform'?'CTSMD-wide':video.audience_scope==='group'?(video.group_name||'Production Group'):'Entire production'}</Text>
    <Text style={styles.title}>{video.title}</Text>{video.description?<Text style={styles.desc} numberOfLines={2}>{video.description}</Text>:null}
    <Text style={styles.label}>{Math.round(video.byte_size/1048576)} MB</Text>
   </View>
   <Ionicons name="chevron-forward" size={20} color={colors.muted}/>
  </Pressable>)}
 </Screen>;
}
const styles=StyleSheet.create({
 lead:{color:colors.muted,fontSize:13,marginBottom:10},panel:{padding:15,backgroundColor:colors.card,borderRadius:12,gap:10},
 error:{color:colors.primary},action:{color:colors.primary,fontWeight:'700'},empty:{color:colors.muted,fontSize:14,padding:16},
 card:{backgroundColor:colors.card,borderColor:colors.border,borderWidth:1,borderRadius:14,padding:14,flexDirection:'row',alignItems:'center',gap:12},
 icon:{width:48,height:48,borderRadius:12,backgroundColor:'#fbf0f2',alignItems:'center',justifyContent:'center'},
 label:{color:colors.muted,fontSize:10,fontWeight:'700'},title:{fontSize:17,color:colors.navy,fontWeight:'700'},desc:{color:colors.text,fontSize:12}
});
