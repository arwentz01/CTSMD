import {TouchTarget as Pressable} from '../src/components/TouchTarget';
import React from 'react';
import {StyleSheet,Text,View} from 'react-native';
import {Ionicons} from '@expo/vector-icons';
import {router} from 'expo-router';
import {Screen} from '../src/components/Screen';
import {useAuth} from '../src/auth/AuthProvider';
import {colors} from '../src/theme/colors';
export default function MyAccount(){
 const {user}=useAuth();
 return <Screen title="My Account">
  <Pressable accessibilityRole="button" onPress={()=>router.back()} style={s.back}><Ionicons name="chevron-back" size={20} color={colors.primary}/><Text style={s.backText}>More</Text></Pressable>
  <View style={s.profile}><View style={s.avatar}><Text style={s.initials}>{user?.initials||'?'}</Text></View><Text style={s.name}>{user?.name||'Member'}</Text><Text style={s.muted}>CTSMD Connect account</Text></View>
  <Text style={s.heading}>Account information</Text>
  <View style={s.card}><Text style={s.label}>Name</Text><Text style={s.value}>{user?.name||'Not available'}</Text></View>
  <View style={s.card}><Text style={s.label}>Email</Text><Text style={s.value}>{user?.email||'Not available'}</Text></View>
  <View style={s.card}><Text style={s.label}>Role</Text><Text style={s.value}>{user?.role||'Member'}</Text></View>
  <Text style={s.hint}>These details are provided by your CTSMD account. Profile editing and contact preferences remain managed through the website until the mobile profile API supports secure updates.</Text>
 </Screen>;
}
const s=StyleSheet.create({back:{flexDirection:'row',alignItems:'center',gap:4},backText:{color:colors.primary,fontWeight:'700'},profile:{backgroundColor:colors.card,borderRadius:14,borderWidth:1,borderColor:colors.border,padding:20,alignItems:'center',gap:8},avatar:{backgroundColor:colors.navy,width:68,height:68,borderRadius:34,alignItems:'center',justifyContent:'center'},initials:{color:'#fff',fontSize:23,fontWeight:'800'},name:{color:colors.navy,fontSize:21,fontWeight:'800'},muted:{fontSize:13,color:colors.muted},heading:{fontSize:18,fontWeight:'800',color:colors.navy,marginTop:8},card:{backgroundColor:colors.card,padding:14,borderWidth:1,borderColor:colors.border,borderRadius:12,gap:5},label:{fontSize:12,color:colors.muted},value:{fontSize:16,color:colors.text,fontWeight:'600'},hint:{color:colors.muted,fontSize:13,lineHeight:20}});
