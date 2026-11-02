import React from 'react';
import {StyleSheet,View} from 'react-native';
import {colors} from '../theme/colors';
/** Small, decorative stage motif. All elements ignore touches. */
export function StageBackdrop(){
 return <View pointerEvents="none" style={styles.layer}>
  <View style={styles.light}/>
  <View style={styles.curtain}>
   {[0,1,2,3,4].map(i=><View key={i} style={[styles.fold,{left:i*23,opacity:i%2===0?0.75:0.35}]}/>)}
   <View style={styles.edge}/>
  </View>
  <View style={styles.trim}/>
 </View>;
}
const styles=StyleSheet.create({
 layer:{position:'absolute',top:0,right:0,bottom:0,left:0,overflow:'hidden',borderRadius:12},
 curtain:{position:'absolute',right:0,top:0,bottom:0,width:'40%',backgroundColor:'#60051c',overflow:'hidden'},
 fold:{position:'absolute',top:0,bottom:0,width:13,backgroundColor:'#b22240'},
 edge:{position:'absolute',left:0,top:0,bottom:0,width:2,backgroundColor:'#d3a04b'},
 light:{position:'absolute',right:40,top:-45,width:64,height:230,backgroundColor:'#e6b759',opacity:0.24,transform:[{rotate:'32deg'}]},
 trim:{position:'absolute',left:0,right:0,bottom:0,height:2,backgroundColor:colors.gold}
});
