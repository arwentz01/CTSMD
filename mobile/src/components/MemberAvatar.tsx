import React,{useEffect,useState} from 'react';
import {Image,StyleSheet,Text,View} from 'react-native';
import {avatarUrl} from '../api/client';
import {readToken} from '../auth/session';
import {colors} from '../theme/colors';

/** Avatar photos stay on the authenticated API, never public static assets. */
export function MemberAvatar({userId,initials,size=36,hasAvatar=true}:{userId:number;initials:string;size?:number;hasAvatar?:boolean}){
 const [token,setToken]=useState<string|null>(null);
 const [failed,setFailed]=useState(false);
 useEffect(()=>{let alive=true;setToken(null);setFailed(false);void readToken().then(value=>{if(alive)setToken(value)});return()=>{alive=false}},[userId]);
 const showImage=hasAvatar&&!!token&&!failed&&Number.isSafeInteger(userId)&&userId>0;
 return <View style={[styles.base,{width:size,height:size,borderRadius:size/2}]}>
  {showImage?<Image source={{uri:avatarUrl(userId,size>=85?'large':size>=48?'medium':'small'),headers:{Authorization:'Bearer '+token}}} style={{width:size,height:size,borderRadius:size/2}} onError={()=>setFailed(true)} accessibilityLabel="Member profile photo"/>:
  <Text style={[styles.initials,{fontSize:Math.max(11,Math.round(size*0.34))}]}>{initials||'?'}</Text>}
 </View>;
}
const styles=StyleSheet.create({
 base:{backgroundColor:colors.ivory,borderColor:colors.border,borderWidth:1,justifyContent:'center',alignItems:'center',overflow:'hidden'},
 initials:{color:colors.primary,fontWeight:'800'}
});
