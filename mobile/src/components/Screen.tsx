import React,{useEffect,useState} from 'react';
import {router,usePathname} from 'expo-router';
import {Ionicons} from '@expo/vector-icons';
import {SafeAreaView} from 'react-native-safe-area-context';
import {Alert,AppState,Image,KeyboardAvoidingView,Linking,Platform,Pressable,ScrollView,StyleSheet,Text,View} from 'react-native';
import {colors} from '../theme/colors';
import {ANDROID_PLAY_STORE_URL,checkForAndroidUpdate,type AvailableAndroidUpdate} from '../updates/closedTestUpdate';
function AppHeader(){
 const path=usePathname();
 const [availableUpdate,setAvailableUpdate]=useState<AvailableAndroidUpdate|null>(null);
 useEffect(()=>{
  let active=true;
  const refresh=()=>{
   void checkForAndroidUpdate().then(result=>{if(active)setAvailableUpdate(result)});
  };
  refresh();
  const listener=AppState.addEventListener('change',state=>{if(state==='active')refresh()});
  return ()=>{active=false;listener.remove()};
 },[]);
 const openUpdate=()=>{
  void Linking.openURL(ANDROID_PLAY_STORE_URL).catch(()=>{
   Alert.alert('Unable to open Google Play','Open the Play Store and search for CTSMD Connect to update your app.');
  });
 };
 const detail=!['/','/theatre','/community','/messages','/more','/forms','/volunteer'].includes(path);
 return <View style={styles.header}>
  {detail&&router.canGoBack()&&<Pressable accessibilityRole="button" accessibilityLabel="Go back" style={styles.headerButton} onPress={()=>router.back()}><Ionicons name="chevron-back" size={24} color={colors.primary}/></Pressable>}
  <Image accessible={false} source={require('../../assets/images/ctsmd-logo.png')} style={styles.brandMark} resizeMode="contain"/>
  <View style={styles.brand}><Text style={styles.brandName}>CTSMD</Text><Text style={styles.brandSubtitle}>CONNECT</Text></View>
  {availableUpdate&&<Pressable accessibilityRole="link" accessibilityLabel={'Update CTSMD Connect to version '+availableUpdate.version+' in Google Play'} accessibilityHint="Opens the CTSMD Connect closed-testing listing in Google Play" onPress={openUpdate} style={styles.headerButton}>
    <View style={styles.updateIcon}><Ionicons name="cloud-download-outline" size={22} color={colors.primary}/><View style={styles.updateDot}/></View>
   </Pressable>}
  {path!=='/messages'&&<Pressable accessibilityRole="button" accessibilityLabel="Open messages" style={styles.headerButton} onPress={()=>router.navigate('/(tabs)/messages')}><Ionicons name="chatbubble-outline" size={22} color={colors.primary}/></Pressable>}
  {path!=='/more'&&<Pressable accessibilityRole="button" accessibilityLabel="Open account and settings" style={styles.headerButton} onPress={()=>router.navigate('/(tabs)/more')}><Ionicons name="person-outline" size={22} color={colors.primary}/></Pressable>}
 </View>;
}
export function Screen({title,children,compact=false}:{title:string;children:React.ReactNode;compact?:boolean}){
 return <SafeAreaView edges={['top','left','right']} style={styles.safe}><AppHeader/><KeyboardAvoidingView style={styles.safe} behavior={Platform.OS==='ios'?'padding':undefined}><ScrollView keyboardShouldPersistTaps="handled" keyboardDismissMode={Platform.OS==='ios'?'interactive':'on-drag'} automaticallyAdjustKeyboardInsets contentContainerStyle={[styles.content,compact&&styles.compactContent]}><Text accessibilityRole="header" style={styles.title}>{title}</Text>{children}</ScrollView></KeyboardAvoidingView></SafeAreaView>;
}
export function Item({title,subtitle}:{title:string;subtitle?:string}){
 return <View style={styles.item}><Text style={styles.itemTitle}>{title}</Text>{!!subtitle&&<Text style={styles.subtitle}>{subtitle}</Text>}</View>;
}
const styles=StyleSheet.create({
 safe:{flex:1,backgroundColor:colors.background},
 header:{minHeight:60,paddingHorizontal:12,flexDirection:'row',alignItems:'center',gap:6,backgroundColor:colors.card,borderBottomColor:colors.border,borderBottomWidth:StyleSheet.hairlineWidth},
 brandMark:{width:36,height:36},brand:{flex:1,gap:1},brandName:{fontSize:17,fontWeight:'800',color:colors.navy,letterSpacing:-0.4},brandSubtitle:{fontSize:9,letterSpacing:2.3,fontWeight:'800',color:colors.primary},
 headerButton:{width:44,height:48,alignItems:'center',justifyContent:'center'},
 updateIcon:{width:35,height:35,borderRadius:18,backgroundColor:'#f9eac5',borderWidth:1,borderColor:colors.gold,alignItems:'center',justifyContent:'center'},updateDot:{position:'absolute',top:1,right:0,width:9,height:9,borderRadius:5,backgroundColor:colors.primary,borderWidth:1,borderColor:colors.card},
 content:{width:'100%',maxWidth:1000,alignSelf:'center',paddingHorizontal:16,paddingTop:17,paddingBottom:32,gap:14,flexGrow:1},
 compactContent:{paddingTop:12,gap:9},
 title:{fontSize:25,fontWeight:'800',color:colors.navy,letterSpacing:-0.45,marginBottom:2},
 item:{backgroundColor:colors.card,borderRadius:12,borderWidth:1,borderColor:colors.border,padding:13,gap:5},
 itemTitle:{fontSize:17,fontWeight:'600',color:colors.text},subtitle:{fontSize:15,lineHeight:22,color:colors.muted},
});
