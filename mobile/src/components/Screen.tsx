import React from 'react';
import {router, usePathname} from 'expo-router';
import {Ionicons} from '@expo/vector-icons';
import {SafeAreaView} from 'react-native-safe-area-context';
import {Pressable, ScrollView, StyleSheet, Text, View} from 'react-native';
import {colors} from '../theme/colors';

function AppHeader(){
  const pathname=usePathname();
  const onMessages=pathname==='/messages';
  const onMore=pathname==='/more';
  return <View style={styles.header}>
    <View style={styles.brand}><Ionicons name="color-palette-outline" color={colors.primary} size={22}/><Text style={styles.brandName}>CTSMD Connect</Text></View>
    <View style={styles.headerActions}>
      {!onMessages&&<Pressable accessibilityRole="button" accessibilityLabel="Open messages" hitSlop={10} style={styles.headerButton} onPress={()=>router.navigate('/(tabs)/messages')}><Ionicons name="chatbubble-outline" size={23} color={colors.navy}/></Pressable>}
      {!onMore&&<Pressable accessibilityRole="button" accessibilityLabel="Open account and settings" hitSlop={10} style={styles.headerButton} onPress={()=>router.navigate('/(tabs)/more')}><Ionicons name="person-circle-outline" size={25} color={colors.navy}/></Pressable>}
    </View>
  </View>;
}
export function Screen({title,children}:{title:string;children:React.ReactNode}){
  return <SafeAreaView edges={['top']} style={styles.safe}>
    <AppHeader/>
    <ScrollView keyboardShouldPersistTaps="handled" contentContainerStyle={styles.content}>
      <Text accessibilityRole="header" style={styles.title}>{title}</Text>
      {children}
    </ScrollView>
  </SafeAreaView>;
}
export function Item({title,subtitle}:{title:string;subtitle?:string}){
  return <View style={styles.item}><Text style={styles.itemTitle}>{title}</Text>{!!subtitle&&<Text style={styles.subtitle}>{subtitle}</Text>}</View>;
}
const styles=StyleSheet.create({
  safe:{flex:1,backgroundColor:colors.background},
  header:{height:56,paddingHorizontal:20,flexDirection:'row',alignItems:'center',justifyContent:'space-between',backgroundColor:colors.card,borderBottomColor:colors.border,borderBottomWidth:1},
  brand:{flexDirection:'row',alignItems:'center',gap:9},
  brandName:{fontSize:17,fontWeight:'800',color:colors.navy},
  headerActions:{flexDirection:'row',alignItems:'center',gap:8},
  headerButton:{width:38,height:40,alignItems:'center',justifyContent:'center'},
  content:{padding:20,paddingBottom:38,gap:12},
  title:{fontSize:28,fontWeight:'800',color:colors.navy,marginBottom:8},
  item:{backgroundColor:colors.card,borderRadius:12,borderWidth:1,borderColor:colors.border,padding:16,gap:5},
  itemTitle:{fontSize:16,fontWeight:'700',color:colors.text},
  subtitle:{fontSize:13,color:colors.muted}
});
