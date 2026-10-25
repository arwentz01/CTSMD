import React from 'react';
import {router,usePathname} from 'expo-router';
import {Ionicons} from '@expo/vector-icons';
import {SafeAreaView} from 'react-native-safe-area-context';
import {Pressable,ScrollView,StyleSheet,Text,View} from 'react-native';
import {colors} from '../theme/colors';
function AppHeader(){
 const pathname=usePathname();const onMessages=pathname==='/messages';const onMore=pathname==='/more';
 return <View style={styles.header}>
  <View style={styles.brandMark}><Ionicons name="happy-outline" size={22} color={colors.gold}/></View>
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
 header:{minHeight:76,paddingHorizontal:16,flexDirection:'row',alignItems:'center',gap:10,backgroundColor:colors.burgundy,borderBottomColor:colors.gold,borderBottomWidth:2},
 brandMark:{width:42,height:42,borderRadius:21,borderWidth:2,borderColor:colors.gold,alignItems:'center',justifyContent:'center',backgroundColor:colors.stage},
 brand:{flex:1,gap:4},brandName:{fontFamily:'Georgia',fontSize:23,fontWeight:'700',color:colors.ivory},brandGold:{color:colors.gold},brandSubtitle:{fontSize:7,letterSpacing:1.1,color:'#f4e1d3'},
 actions:{flexDirection:'row',gap:7},headerButton:{width:37,height:37,borderRadius:19,alignItems:'center',justifyContent:'center',backgroundColor:colors.stage,borderWidth:1,borderColor:colors.gold},
 content:{padding:18,paddingBottom:40,gap:13},pageHeading:{marginBottom:12},title:{fontFamily:'Georgia',fontSize:38,fontWeight:'700',color:colors.navy},goldLine:{width:98,height:3,backgroundColor:colors.gold,marginTop:7,borderRadius:3},
 item:{backgroundColor:colors.card,borderRadius:15,borderWidth:1,borderColor:colors.border,padding:17,gap:5},itemTitle:{fontFamily:'Georgia',fontSize:19,fontWeight:'700',color:colors.text},subtitle:{fontSize:13,color:colors.muted}
});
