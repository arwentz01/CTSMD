import React,{useEffect,useRef,useState} from 'react';
import {useEvent,useEventListener} from 'expo';
import {ActivityIndicator,Pressable,StyleSheet,Text,View} from 'react-native';
import {useLocalSearchParams,router} from 'expo-router';
import {VideoView,useVideoPlayer,type VideoSource} from 'expo-video';
import {Ionicons} from '@expo/vector-icons';
import {Screen} from '../../src/components/Screen';
import {colors} from '../../src/theme/colors';
import {authorizedVideoSource,videoLibrary,type LibraryVideo} from '../../src/api/client';

function Player({source}:{source:VideoSource}){
 const player=useVideoPlayer(source);
 const [rate,setRate]=useState(1);
 const [segment,setSegment]=useState<{start:number|null;end:number|null}>({start:null,end:null});
 const segmentRef=useRef<{start:number|null;end:number|null}>({start:null,end:null});
 const {status}=useEvent(player,'statusChange',{status:player.status});
 useEffect(()=>{player.timeUpdateEventInterval=0.15;return()=>{player.timeUpdateEventInterval=0}},[player]);
 useEventListener(player,'timeUpdate',({currentTime})=>{
  const {start,end}=segmentRef.current;
  if(start!==null&&end!==null&&currentTime>=end){player.currentTime=start;}
 });
 const setSpeed=(speed:number)=>{player.playbackRate=speed;setRate(speed)};
 const mark=(kind:'start'|'end')=>{
  const now=player.currentTime;
  const next=kind==='start'?{start:now,end:null}:
   segmentRef.current.start!==null&&now>segmentRef.current.start+0.2?{start:segmentRef.current.start,end:now}:segmentRef.current;
  segmentRef.current=next;setSegment(next);
 };
 const clear=()=>{const next={start:null,end:null};segmentRef.current=next;setSegment(next)};
 return <View style={styles.viewer}>
  {status==='error'?<Text style={styles.error}>This video could not be played. Try reopening it or contact CTSMD staff if the problem continues.</Text>:null}
  {status==='loading'?<ActivityIndicator color={colors.primary}/>:null}
  <VideoView player={player} style={styles.video} nativeControls contentFit="contain" fullscreenOptions={{enable:true}}/>
  <View style={styles.tools}>
   <Pressable accessibilityRole="button" onPress={()=>{player.currentTime=Math.max(0,player.currentTime-10)}} style={styles.tool}>
    <Ionicons name="play-back" size={17} color={colors.primary}/><Text style={styles.toolText}>10 sec</Text>
   </Pressable>
   {[0.5,0.75,1,1.25].map(speed=><Pressable key={speed} accessibilityRole="button" accessibilityState={{selected:rate===speed}} onPress={()=>setSpeed(speed)} style={[styles.tool,rate===speed&&styles.selected]}><Text style={styles.toolText}>{speed}×</Text></Pressable>)}
  </View>
  <View style={styles.tools}>
   <Pressable accessibilityRole="button" onPress={()=>mark('start')} style={styles.tool}><Text style={styles.toolText}>Set loop A{segment.start!==null?' · '+segment.start.toFixed(1)+'s':''}</Text></Pressable>
   <Pressable accessibilityRole="button" onPress={()=>mark('end')} disabled={segment.start===null} style={[styles.tool,segment.start===null&&styles.disabled]}><Text style={styles.toolText}>Set loop B{segment.end!==null?' · '+segment.end.toFixed(1)+'s':''}</Text></Pressable>
   {segment.start!==null?<Pressable accessibilityRole="button" onPress={clear} style={styles.tool}><Text style={styles.toolText}>Clear loop</Text></Pressable>:null}
  </View>
 </View>;
}
export default function VideoDetail(){
 const params=useLocalSearchParams<{id:string}>();
 const id=Number(params.id);
 const [source,setSource]=useState<VideoSource|null>(null);
 const [item,setItem]=useState<LibraryVideo|null>(null);
 const [error,setError]=useState('');
 useEffect(()=>{let active=true;(async()=>{
  try{
   if(!Number.isSafeInteger(id)||id<=0)throw Error('Invalid video.');
   const data=await videoLibrary();
   const selected=data.videos.find(v=>v.id===id);
   if(!selected)throw Error('This video is no longer available to your account.');
   const playback=await authorizedVideoSource(id);
   if(active){setItem(selected);setSource(playback)}
  }catch(e){if(active)setError(e instanceof Error?e.message:'Could not open video.')}
 })();return()=>{active=false}},[id]);
 return <Screen title="Video Library">
  <Pressable onPress={()=>router.back()} style={styles.back} accessibilityRole="button"><Ionicons name="arrow-back" color={colors.primary} size={18}/><Text style={styles.link}>All videos</Text></Pressable>
  {error?<Text style={styles.error}>{error}</Text>:null}
  {!source&&!error?<ActivityIndicator color={colors.primary}/>:null}
  {source?<Player source={source}/>:null}
  {item?<View style={styles.info}><Text style={styles.eyebrow}>{item.category.toUpperCase()} · {item.audience_scope==='platform'?'CTSMD-wide':item.audience_scope==='group'?(item.group_name||'Production Group'):'Entire production'}</Text><Text style={styles.title}>{item.title}</Text>{item.description?<Text style={styles.desc}>{item.description}</Text>:null}<Text style={styles.note}>These videos are for authorized CTSMD viewing only. Video playback may use mobile data.</Text></View>:null}
 </Screen>;
}
const styles=StyleSheet.create({
 back:{flexDirection:'row',alignItems:'center',gap:8,marginBottom:8},link:{color:colors.primary,fontWeight:'700'},
 error:{color:colors.primary,fontSize:14},viewer:{gap:12},video:{width:'100%',aspectRatio:16/9,backgroundColor:'#151116',borderRadius:12},
 tools:{flexDirection:'row',flexWrap:'wrap',gap:8,alignItems:'center'},tool:{backgroundColor:colors.card,borderWidth:1,borderColor:colors.border,borderRadius:9,paddingHorizontal:11,paddingVertical:9,flexDirection:'row',gap:5,alignItems:'center'},
 selected:{borderColor:colors.primary,backgroundColor:'#fff0f3'},disabled:{opacity:0.4},toolText:{fontSize:12,color:colors.text,fontWeight:'700'},
 info:{gap:9,marginTop:18},eyebrow:{fontSize:11,fontWeight:'800',color:colors.primary},title:{fontSize:23,fontWeight:'700',fontFamily:'Georgia',color:colors.navy},
 desc:{fontSize:14,color:colors.text,lineHeight:21},note:{fontSize:11,color:colors.muted,marginTop:14}
});
