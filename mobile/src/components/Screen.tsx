import React from 'react';
import {router,usePathname} from 'expo-router';
import {Ionicons} from '@expo/vector-icons';
import {SafeAreaView} from 'react-native-safe-area-context';
import {Image,KeyboardAvoidingView,Platform,Pressable,ScrollView,StyleSheet,Text,View} from 'react-native';
import {colors} from '../theme/colors';
function AppHeader(){
 const path=usePathname();
 const detail=!['/','/theatre','/community','/messages','/more','/forms','/volunteer'].includes(path);
 return <View style={styles.header}>
  {detail&&router.canGoBack()&&<Pressable accessibilityRole="button" accessibilityLabel="Go back" style={styles.headerButton} onPress={()=>router.back()}><Ionicons name="chevron-back" size={24} color={colors.primary}/></Pressable>}
  <Image accessible={false} source={require('../../assets/images/ctsmd-logo.png')} style={styles.brandMark} resizeMode="contain"/>
  <View style={styles.brand}><Text style={styles.brandName}>CTSMD Connect</Text><Text style={styles.brandSubtitle}>Children's Theatre of Southern Maryland</Text></View>
  {path!=='/messages'&&<Pressable accessibilityRole="button" accessibilityLabel="Open messages" style={styles.headerButton} onPress={()=>router.navigate('/(tabs)/messages')}><Ionicons name="chatbubble-outline" size={22} color={colors.primary}/></Pressable>}
  {path!=='/more'&&<Pressable accessibilityRole="button" accessibilityLabel="Open account and settings" style={styles.headerButton} onPress={()=>router.navigate('/(tabs)/more')}><Ionicons name="person-outline" size={22} color={colors.primary}/></Pressable>}
 </View>;
}
export function Screen({title,children}:{title:string;children:React.ReactNode}){
 return <SafeAreaView edges={['top','left','right']} style={styles.safe}><AppHeader/><KeyboardAvoidingView style={styles.safe} behavior={Platform.OS==='ios'?'padding':undefined}><ScrollView keyboardShouldPersistTaps="handled" keyboardDismissMode={Platform.OS==='ios'?'interactive':'on-drag'} automaticallyAdjustKeyboardInsets contentContainerStyle={styles.content}><Text accessibilityRole="header" style={styles.title}>{title}</Text>{children}</ScrollView></KeyboardAvoidingView></SafeAreaView>;
}
export function Item({title,subtitle}:{title:string;subtitle?:string}){
 return <View style={styles.item}><Text style={styles.itemTitle}>{title}</Text>{!!subtitle&&<Text style={styles.subtitle}>{subtitle}</Text>}</View>;
}
const styles=StyleSheet.create({
 safe:{flex:1,backgroundColor:colors.background},
 header:{minHeight:64,paddingHorizontal:8,flexDirection:'row',alignItems:'center',gap:4,backgroundColor:colors.card,borderBottomColor:colors.border,borderBottomWidth:1},
 brandMark:{width:32,height:32},brand:{flex:1,gap:3},brandName:{fontSize:17,fontWeight:'700',color:colors.text},brandSubtitle:{fontSize:11,color:colors.muted},
 headerButton:{width:48,height:48,alignItems:'center',justifyContent:'center'},
 content:{width:'100%',maxWidth:1000,alignSelf:'center',paddingHorizontal:20,paddingTop:20,paddingBottom:40,gap:16,flexGrow:1},
 title:{fontSize:28,fontWeight:'700',color:colors.text,marginBottom:4},
 item:{backgroundColor:colors.card,borderRadius:12,borderWidth:1,borderColor:colors.border,padding:16,gap:6},
 itemTitle:{fontSize:17,fontWeight:'600',color:colors.text},subtitle:{fontSize:15,lineHeight:22,color:colors.muted},
});
