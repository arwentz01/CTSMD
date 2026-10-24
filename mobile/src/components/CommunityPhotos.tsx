import React,{useEffect,useState} from 'react';
import {Image,StyleSheet,Text,View} from 'react-native';
import {readToken} from '../auth/session';
import {communityPhotoUrl} from '../api/client';
import type {CommunityAttachment} from '../types/ctsmd';
import {colors} from '../theme/colors';
export function CommunityPhotos({attachments}:{attachments?:CommunityAttachment[]}){
 const [token,setToken]=useState<string|null>(null);
 useEffect(()=>{let alive=true;void readToken().then(value=>{if(alive)setToken(value)});return()=>{alive=false}},[]);
 const photos=(attachments||[]).filter(a=>['image/jpeg','image/png','image/webp'].includes(a.mime_type));
 if(!photos.length)return null;
 return <View style={s.grid}>{photos.map(photo=><View key={photo.id} style={s.photoWrap}>{token?<Image accessibilityLabel={photo.original_name||'Community photo'} source={{uri:communityPhotoUrl(photo.id),headers:{Authorization:'Bearer '+token}}} style={s.photo} resizeMode="cover"/>:<Text style={s.placeholder}>Loading photo…</Text>}</View>)}</View>;
}
const s=StyleSheet.create({grid:{flexDirection:'row',flexWrap:'wrap',gap:6},photoWrap:{width:'48%',aspectRatio:1,borderRadius:10,overflow:'hidden',backgroundColor:colors.background},photo:{height:'100%',width:'100%'},placeholder:{color:colors.muted,padding:10,fontSize:12}});
