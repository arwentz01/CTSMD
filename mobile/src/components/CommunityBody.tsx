import React from 'react';
import {Text,StyleSheet} from 'react-native';
import {router} from 'expo-router';
import {colors} from '../theme/colors';

/** Render only CTSMD-owned video permalinks as named native navigation links. */
export function CommunityBody({body}:{body:string}){
 const pattern=/\[([^\]\r\n]{1,160})\]\((?:https:\/\/(?:www\.)?ctsmd\.me)?\/videos\/view\?id=([0-9]{1,18})\)/gi;
 const parts:React.ReactNode[]=[];
 let last=0;
 for(const match of body.matchAll(pattern)){
  const start=match.index??0;
  if(start>last)parts.push(body.slice(last,start));
  const id=Number(match[2]);
  if(Number.isSafeInteger(id)&&id>0){
   parts.push(<Text key={start} accessibilityRole="link" style={styles.link} onPress={(e)=>{e.stopPropagation();router.push({pathname:'/videos/[id]',params:{id:String(id)}})}}>{match[1]}</Text>);
  }else parts.push(match[0]);
  last=start+match[0].length;
 }
 if(last<body.length)parts.push(body.slice(last));
 return <Text style={styles.body}>{parts.length?parts:body}</Text>;
}
const styles=StyleSheet.create({body:{fontSize:15,lineHeight:22,color:colors.text},link:{color:colors.primary,fontWeight:'700',textDecorationLine:'underline'}});
