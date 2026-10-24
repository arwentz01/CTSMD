import React,{useCallback} from 'react';
import {Pressable,StyleSheet,Text,View} from 'react-native';
import {Ionicons} from '@expo/vector-icons';
import {router} from 'expo-router';
import {Screen} from '../src/components/Screen';
import {LoadState,useScreenData} from '../src/components/LoadState';
import {myFamily} from '../src/api/client';
import {colors} from '../src/theme/colors';

export default function MyFamily(){
 const load=useCallback(()=>myFamily(),[]);
 const {data,error,loading}=useScreenData(load);
 return <Screen title="My Family">
  <Pressable accessibilityRole="button" onPress={()=>router.back()} style={s.back}><Ionicons name="chevron-back" color={colors.primary} size={20}/><Text style={s.link}>More</Text></Pressable>
  <Text style={s.intro}>Family connections associated with your CTSMD account. Only active relationships are displayed.</Text>
  <LoadState loading={loading} error={error}/>
  {data&&<>
   <Text style={s.heading}>My students</Text>
   {data.students.map(student=><View key={student.id} style={s.card}><Ionicons name="school-outline" size={22} color={colors.primary}/><View style={{flex:1,gap:4}}><Text style={s.name}>{student.name}</Text><Text style={s.label}>{student.is_primary?'Primary guardian relationship':'Active family relationship'}</Text></View></View>)}
   {data.students.length===0&&<Text style={s.empty}>No linked students are currently available to your account.</Text>}
   <Text style={s.heading}>My guardians</Text>
   {data.guardians.map(guardian=><View key={guardian.id} style={s.card}><Ionicons name="people-outline" size={22} color={colors.primary}/><View style={{flex:1,gap:4}}><Text style={s.name}>{guardian.name}</Text><Text style={s.label}>{guardian.is_primary?'Primary guardian':'Active guardian'}</Text></View></View>)}
   {data.guardians.length===0&&<Text style={s.empty}>No active guardian relationships are shown for this account.</Text>}
  </>}
  <Text style={s.note}>Family relationships are managed through CTSMD. Changes to guardianship and student access are controlled by the organization's safeguarding procedures, not by this mobile screen.</Text>
 </Screen>;
}
const s=StyleSheet.create({back:{flexDirection:'row',alignItems:'center',gap:4},link:{color:colors.primary,fontWeight:'700'},intro:{color:colors.muted,fontSize:14,lineHeight:21},heading:{color:colors.navy,fontWeight:'800',fontSize:18,marginTop:10},card:{borderWidth:1,borderColor:colors.border,backgroundColor:colors.card,padding:16,borderRadius:12,flexDirection:'row',gap:12,alignItems:'center'},name:{color:colors.text,fontSize:16,fontWeight:'700'},label:{color:colors.muted,fontSize:12},empty:{color:colors.muted,fontSize:14,paddingVertical:8},note:{color:colors.muted,fontSize:12,lineHeight:19,marginTop:16}});
