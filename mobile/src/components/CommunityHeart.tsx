import React,{useEffect,useState} from 'react';
import {Alert,Pressable,Text,View} from 'react-native';
import {Ionicons} from '@expo/vector-icons';
import {toggleCommunityHeart} from '../api/client';
import {colors} from '../theme/colors';

export function CommunityHeart({postId,count,hearted}:{postId:number;count:number;hearted:boolean}){
 const [active,setActive]=useState(hearted),[total,setTotal]=useState(count),[busy,setBusy]=useState(false);
 useEffect(()=>{setActive(hearted);setTotal(count)},[postId,hearted,count]);
 const toggle=async()=>{if(busy)return;setBusy(true);try{const next=await toggleCommunityHeart(postId);setTotal(x=>Math.max(0,x+(next.hearted===active?0:next.hearted?1:-1)));setActive(next.hearted);}catch(e){Alert.alert('Reaction unavailable',e instanceof Error?e.message:'Please try again.')}finally{setBusy(false)}};
 return <Pressable accessibilityRole="button" accessibilityLabel={active?'Remove heart reaction':'Heart this post'} accessibilityState={{selected:active,disabled:busy}} disabled={busy} onPress={()=>void toggle()} style={{alignSelf:'flex-start',flexDirection:'row',alignItems:'center',gap:6,paddingVertical:5,paddingHorizontal:4}}>
  <Ionicons name={active?'heart':'heart-outline'} color={active?colors.primary:colors.muted} size={20}/><Text style={{fontSize:13,color:active?colors.primary:colors.muted,fontWeight:'600'}}>{total}</Text>
 </Pressable>;
}
