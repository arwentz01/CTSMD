import React,{useEffect,useState} from 'react';
import {Keyboard,KeyboardAvoidingView,Platform,StyleSheet} from 'react-native';
import {SafeAreaView} from 'react-native-safe-area-context';
import {colors} from '../theme/colors';

/** Android resizes the window; iOS uses one keyboard avoidance container. */
export function ConversationViewport({children}:{children:React.ReactNode}){
 const [keyboardVisible,setKeyboardVisible]=useState(false);
 useEffect(()=>{
  if(Platform.OS!=='ios')return;
  const show=Keyboard.addListener('keyboardWillShow',()=>setKeyboardVisible(true));
  const hide=Keyboard.addListener('keyboardWillHide',()=>setKeyboardVisible(false));
  return()=>{show.remove();hide.remove()};
 },[]);
 return <SafeAreaView edges={keyboardVisible?['top','left','right']:['top','bottom','left','right']} style={styles.screen}>
  <KeyboardAvoidingView style={styles.screen} behavior={Platform.OS==='ios'?'padding':undefined}>{children}</KeyboardAvoidingView>
 </SafeAreaView>;
}
const styles=StyleSheet.create({screen:{flex:1,backgroundColor:colors.background}});
