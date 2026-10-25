import React from 'react';
import {ProductionArtwork} from './ProductionArtwork';
import {Image,Pressable,StyleSheet,Text,View} from 'react-native';
import {Ionicons} from '@expo/vector-icons';
import {colors} from '../theme/colors';
import type {Production} from '../types/ctsmd';

/** Shared stage fallback until production-specific approved artwork is exposed by the API. */
export function ProductionHero({production,onPress}:{production:Production;onPress?:()=>void}){
 const content=<>
  <ProductionArtwork productionId={production.id} enabled={!!production.has_artwork}/>
  <View pointerEvents="none" style={s.scrim}/>
  <View style={s.content}><Text style={s.title} numberOfLines={2}>{production.title}</Text><Text style={s.meta} numberOfLines={1}>{[production.season,production.status].filter(Boolean).join(' · ')}</Text></View>
  {onPress&&<View style={s.arrow}><Ionicons name="chevron-forward" color={colors.stage} size={21}/></View>}
 </>;
 return onPress?<Pressable accessibilityRole="button" accessibilityLabel={'Open '+production.title} onPress={onPress} style={s.card}>{content}</Pressable>:<View style={s.card}>{content}</View>;
}
const s=StyleSheet.create({card:{position:'relative',overflow:'hidden',borderRadius:16,borderWidth:1,borderBottomWidth:3,borderColor:colors.gold,backgroundColor:colors.stage,height:116,justifyContent:'center',paddingHorizontal:18,paddingRight:65},image:{position:'absolute',top:0,bottom:0,left:0,right:0,width:undefined,height:undefined},scrim:{...StyleSheet.absoluteFillObject,backgroundColor:'rgba(13,6,10,0.50)'},content:{gap:7},title:{fontFamily:'serif',fontSize:25,fontWeight:'700',color:'#fff'},meta:{fontSize:13,color:'#f6e6d0'},arrow:{position:'absolute',right:15,top:39,width:36,height:36,borderRadius:18,backgroundColor:colors.gold,alignItems:'center',justifyContent:'center'}});
