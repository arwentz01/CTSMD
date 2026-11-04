import React from 'react';
import {Linking,Text,StyleSheet} from 'react-native';
import {router} from 'expo-router';
import {colors} from '../theme/colors';

/** Shared safe link rendering for channel messages, replies and feed previews. */
export function CommunityBody({body,numberOfLines}:{body:string;numberOfLines?:number}){
 const pattern=/\[([^\]\r\n]{1,160})\]\(([^\s)]+)\)|(https?:\/\/[^\s<>]+)/gi;
 const parts:React.ReactNode[]=[];
 let last=0;
 for(const match of body.matchAll(pattern)){
  const start=match.index??0;
  if(start>last)parts.push(body.slice(last,start));
  let target=match[2]||match[3],suffix='';
  if(!match[2]){const trailing=target.match(/[.,!?;:]+$/);if(trailing){suffix=trailing[0];target=target.slice(0,-suffix.length)}}
  const label=match[1]||target;
  const video=target.match(/^(?:https:\/\/(?:www\.)?ctsmd\.me)?\/videos\/view\?id=([1-9]\d{0,15})$/i);
  let safe=false;
  try{const parsed=new URL(target);safe=(parsed.protocol==='https:'||parsed.protocol==='http:')&&!parsed.username&&!parsed.password;}catch{}
  if(video&&Number.isSafeInteger(Number(video[1]))){
   const id=video[1];parts.push(<Text key={start} accessibilityRole="link" style={styles.link} onPress={e=>{e.stopPropagation();router.push({pathname:'/videos/[id]',params:{id}})}}>{label}</Text>);
  }else if(safe){
   parts.push(<Text key={start} accessibilityRole="link" style={styles.link} onPress={e=>{e.stopPropagation();void Linking.openURL(target).catch(()=>{})}}>{label}</Text>);
  }else parts.push(label);
  if(suffix)parts.push(suffix);
  last=start+match[0].length;
 }
 if(last<body.length)parts.push(body.slice(last));
 return <Text style={styles.body} numberOfLines={numberOfLines}>{parts.length?parts:body}</Text>;
}
const styles=StyleSheet.create({body:{fontSize:14,lineHeight:20,color:colors.text},link:{color:colors.primary,fontWeight:'700',textDecorationLine:'underline'}});
