import React from 'react';
import {router,usePathname} from 'expo-router';
import {Ionicons} from '@expo/vector-icons';
import {SafeAreaView} from 'react-native-safe-area-context';
import {Pressable,ScrollView,StyleSheet,Text,View} from 'react-native';
import {colors} from '../theme/colors';
import {StageBackdrop} from './StageBackdrop';
function AppHeader(){
 const pathname=usePathname();const onMessages=pathname==='/messages';const onMore=pathname==='/more';
 return <View style={styles.header}><StageBackdrop/>
  <View style={styles.brandMark}><View style={{flexDirection:"row",alignItems:"center"}}><Ionicons name="happy-outline" size={15} color={colors.gold}/><Ionicons name="sad-outline" size={18} color={colors.gold}/></View></View>
  <View style={styles.brand}><Text style={styles.brandName}>CTSMD <Text style={styles.brandGold}>Connect</Text></Text><Text style={styles.brandSubtitle}>CHILDREN'S THEATRE OF SOUTHERN MARYLAND</Text></View>
  <View style={styles.actions}>
   {!onMessages&&<Pressable accessibilityRole="button" accessibilityLabel="Open messages" hitSlop={8} style={styles.headerButton} onPress={()=>router.navigate('/(tabs)/messages')}><Ionicons name="chatbubble-outline" size={21} color={colors.ivory}/></Pressable>}
   {!onMore&&<Pressable accessibilityRole="button" accessibilityLabel="Open account and settings" hitSlop={8} style={styles.headerButton} onPress={()=>router.navigate('/(tabs)/more')}><Ionicons name="person-outline" size={22} color={colors.ivory}/></Pressable>}
  </View>
 </View>;
}
export function Screen({title,children}:{title:string;children:React.ReactNode}){
 return <SafeAreaView edges={['top']} style={styles.safe}><AppHeader/><ScrollView keyboardShouldPersistTaps="handled" contentContainerStyle={styles.content}><View style={styles.pageHeading}><Text accessibilityRole="header" style={styles.title}>{title}</Text><View style={styles.goldLine}/></View>{children}</ScrollView></SafeAreaView>;
}
export function Item({title,subtitle}:{title:string;subtitle?:string}){return <View style={styles.item}><Text style={styles.itemTitle}>{title}</Text>{!!subtitle&&<Text style={styles.subtitle}>{subtitle}</Text>}</View>}
const styles=StyleSheet.create({
 safe:{flex:1,backgroundColor:colors.background},
 header:{minHeight:60,paddingHorizontal:15,flexDirection:'row',alignItems:'center',gap:8,backgroundColor:colors.burgundy,borderBottomColor:colors.gold,borderBottomWidth:2},
 brandMark:{width:35,height:35,borderRadius:18,borderWidth:2,borderColor:colors.gold,alignItems:'center',justifyContent:'center',backgroundColor:colors.stage},
 brand:{flex:1,gap:4},brandName:{fontFamily:'serif',fontSize:19,fontWeight:'700',color:colors.ivory},brandGold:{color:colors.gold},brandSubtitle:{fontSize:6.5,letterSpacing:0.7,color:'#f4e1d3'},
 actions:{flexDirection:'row',gap:7},headerButton:{width:33,height:33,borderRadius:17,alignItems:'center',justifyContent:'center',backgroundColor:colors.stage,borderWidth:1,borderColor:colors.gold},
 content:{paddingHorizontal:16,paddingTop:16,paddingBottom:28,gap:9},pageHeading:{marginBottom:3},title:{fontFamily:'serif',fontSize:31,fontWeight:'700',color:colors.navy},goldLine:{width:70,height:2,backgroundColor:colors.gold,marginTop:4,borderRadius:3},
 item:{backgroundColor:colors.card,borderRadius:15,borderWidth:1,borderColor:colors.border,padding:17,gap:5},itemTitle:{fontFamily:'serif',fontSize:17,fontWeight:'700',color:colors.text},subtitle:{fontSize:13,color:colors.muted}
});
