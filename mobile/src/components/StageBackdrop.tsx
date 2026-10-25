import React from 'react';
import {StyleSheet,View} from 'react-native';
import {colors} from '../theme/colors';
export function StageBackdrop(){
 return <View pointerEvents="none" style={styles.layer}>
  <View style={styles.light}/>
  <View style={styles.curtain}>{[0,1,2,3].map(i=><View key={i} style={[styles.fold,{left:i*25}]}/>)}</View>
  <View style={styles.trim}/>
 </View>;
}
const styles=StyleSheet.create({layer:{...StyleSheet.absoluteFillObject,overflow:'hidden'},curtain:{position:'absolute',right:0,top:0,bottom:0,width:'36%',backgroundColor:'#430616'},fold:{position:'absolute',width:12,top:0,bottom:0,backgroundColor:colors.primary,opacity:0.35},light:{position:'absolute',right:45,bottom:0,width:90,height:130,backgroundColor:'#a37b40',opacity:0.18,transform:[{rotate:'25deg'}]},trim:{position:'absolute',left:0,right:0,bottom:0,height:3,backgroundColor:colors.gold}});
