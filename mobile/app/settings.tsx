import {TouchTarget as Pressable} from '../src/components/TouchTarget';
import React,{useState} from 'react';
import {Alert,StyleSheet,Text,View} from 'react-native';
import {Ionicons} from '@expo/vector-icons';
import {router} from 'expo-router';
import {Screen} from '../src/components/Screen';
import {useAuth} from '../src/auth/AuthProvider';
import {colors} from '../src/theme/colors';
import {enableDeviceNotifications,disableDeviceNotifications} from '../src/notifications/device';
export default function Settings(){
 const {user,signOut}=useAuth();
 const [pushStatus,setPushStatus]=useState('Native delivery needs provider configuration and device acceptance.'),[pushBusy,setPushBusy]=useState(false);
 const configure=async(enable:boolean)=>{setPushBusy(true);try{setPushStatus(await(enable?enableDeviceNotifications():disableDeviceNotifications()))}catch(error){setPushStatus(error instanceof Error?error.message:'Device registration failed. Try again.')}finally{setPushBusy(false)}};
 return <Screen title="Settings">
  <Pressable accessibilityRole="button" onPress={()=>router.back()}><Text style={s.link}>‹ More</Text></Pressable>
  <Text style={s.heading}>Account & privacy</Text>
  <View style={s.card}><Text style={s.label}>Signed in as</Text><Text style={s.value}>{user?.email||'CTSMD member'}</Text></View>
  <View style={s.card}><Text style={s.label}>Family access</Text><Text style={s.body}>Student and guardian access is determined by CTSMD's active family relationships. Changes cannot be made in the app.</Text></View>
  <View style={s.card}><Text style={s.label}>Device notifications</Text><Text accessibilityLiveRegion="polite" style={s.body}>{pushStatus}</Text><Pressable disabled={pushBusy} onPress={()=>void configure(true)}><Text style={s.link}>{pushBusy?'Updating…':'Register this device'}</Text></Pressable><Pressable disabled={pushBusy} onPress={()=>void configure(false)}><Text style={s.link}>Remove device registration</Text></Pressable><Text style={s.body}>Registration is not proof of delivery. Your OS permission and CTSMD delivery preferences remain separate.</Text></View>
  <Text style={s.heading}>Session</Text>
  <Pressable accessibilityRole="button" style={s.card} onPress={()=>Alert.alert('Sign out?','This will remove your sign-in session from this device.',[{text:'Cancel',style:'cancel'},{text:'Sign out',style:'destructive',onPress:()=>void signOut()}])}><Text style={[s.value,{color:colors.primary}]}>Sign out of this device</Text></Pressable>
  <Text style={s.footer}>CTSMD Connect · Version 0.1.0</Text>
 </Screen>;
}
const s=StyleSheet.create({link:{color:colors.primary,fontWeight:'700'},heading:{color:colors.navy,fontSize:18,fontWeight:'800',marginTop:8},card:{backgroundColor:colors.card,borderColor:colors.border,borderWidth:1,borderRadius:12,padding:16,gap:6},label:{color:colors.muted,fontSize:12,fontWeight:'700'},value:{color:colors.text,fontSize:15,fontWeight:'700'},body:{color:colors.text,fontSize:14,lineHeight:21},footer:{fontSize:12,color:colors.muted,marginTop:12}});
