import React,{useEffect,useState} from 'react';
import {Keyboard,KeyboardAvoidingView,Platform,StyleSheet} from 'react-native';
import {SafeAreaView,useSafeAreaInsets} from 'react-native-safe-area-context';
import {colors} from '../theme/colors';

/** One viewport handles keyboard overlap on both native platforms. */
export function ConversationViewport({children}:{children:React.ReactNode}){
 const [keyboardVisible,setKeyboardVisible]=useState(false);
 const {top}=useSafeAreaInsets();
 useEffect(()=>{
  const show=Keyboard.addListener(Platform.OS==='ios'?'keyboardWillShow':'keyboardDidShow',()=>setKeyboardVisible(true));
  const hide=Keyboard.addListener(Platform.OS==='ios'?'keyboardWillHide':'keyboardDidHide',()=>setKeyboardVisible(false));
  return()=>{show.remove();hide.remove()};
 },[]);
 return <SafeAreaView edges={keyboardVisible?['top','left','right']:['top','bottom','left','right']} style={styles.screen}>
  <KeyboardAvoidingView style={styles.screen} behavior={Platform.OS==='ios'?'padding':'height'} keyboardVerticalOffset={Platform.OS==='ios'?top:0}>{children}</KeyboardAvoidingView>
 </SafeAreaView>;
}
const styles=StyleSheet.create({screen:{flex:1,backgroundColor:colors.background}});
