import React,{useEffect,useState} from 'react';
import {Image,Modal,Pressable,ScrollView,StyleSheet,Text,View} from 'react-native';
import {Ionicons} from '@expo/vector-icons';
import {SafeAreaView} from 'react-native-safe-area-context';
import {readToken} from '../auth/session';
import {communityPhotoUrl} from '../api/client';
import type {CommunityAttachment} from '../types/ctsmd';
import {colors} from '../theme/colors';
export function CommunityPhotos({attachments}:{attachments?:CommunityAttachment[]}){
 const [token,setToken]=useState<string|null>(null);
 const [active,setActive]=useState<number|null>(null);
 useEffect(()=>{let alive=true;void readToken().then(value=>{if(alive)setToken(value)});return()=>{alive=false}},[]);
 const photos=(attachments||[]).filter(a=>['image/jpeg','image/png','image/webp'].includes(a.mime_type));
 if(!photos.length)return null;
 const source=(id:number)=>({uri:communityPhotoUrl(id),headers:{Authorization:'Bearer '+token}});
 const current=photos.find(p=>p.id===active);
 return <>
  <View style={s.grid}>{photos.map(photo=><Pressable accessibilityRole="button" accessibilityLabel={'View '+(photo.original_name||'photo')} key={photo.id} style={s.photoWrap} onPress={()=>setActive(photo.id)}>{token?<Image source={source(photo.id)} style={s.photo} resizeMode="cover"/>:<Text style={s.placeholder}>Loading photo…</Text>}</Pressable>)}</View>
  <Modal visible={!!current} animationType="fade" onRequestClose={()=>setActive(null)} statusBarTranslucent>
   <SafeAreaView style={s.viewer}>
    <View style={s.toolbar}><Text style={s.count}>{Math.max(0,photos.findIndex(p=>p.id===active)+1)} / {photos.length}</Text><Pressable accessibilityRole="button" accessibilityLabel="Close photo" onPress={()=>setActive(null)}><Ionicons name="close" size={28} color="#fff"/></Pressable></View>
    {current&&token&&<Image source={source(current.id)} resizeMode="contain" style={s.large}/>}
    <View style={s.controls}><Pressable accessibilityRole="button" accessibilityLabel="Previous photo" disabled={photos.findIndex(p=>p.id===active)<=0} onPress={()=>setActive(photos[Math.max(0,photos.findIndex(p=>p.id===active)-1)].id)}><Ionicons name="chevron-back" size={30} color="#fff"/></Pressable><Text style={s.filename} numberOfLines={2}>{current?.original_name||'Community photo'}</Text><Pressable accessibilityRole="button" accessibilityLabel="Next photo" disabled={photos.findIndex(p=>p.id===active)>=photos.length-1} onPress={()=>setActive(photos[Math.min(photos.length-1,photos.findIndex(p=>p.id===active)+1)].id)}><Ionicons name="chevron-forward" size={30} color="#fff"/></Pressable></View>
   </SafeAreaView>
  </Modal>
 </>;
}
const s=StyleSheet.create({grid:{flexDirection:'row',flexWrap:'wrap',gap:6},photoWrap:{width:'48%',aspectRatio:1,borderRadius:10,overflow:'hidden',backgroundColor:colors.background},photo:{height:'100%',width:'100%'},placeholder:{color:colors.muted,padding:10,fontSize:12},viewer:{flex:1,backgroundColor:'#0e1626'},toolbar:{paddingHorizontal:20,paddingVertical:12,flexDirection:'row',justifyContent:'space-between',alignItems:'center'},count:{color:'#fff',fontSize:14},large:{flex:1,width:'100%'},controls:{flexDirection:'row',alignItems:'center',justifyContent:'space-between',paddingHorizontal:18,paddingVertical:18},filename:{color:'#fff',fontSize:13,flex:1,textAlign:'center'}});
