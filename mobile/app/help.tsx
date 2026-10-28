import {TouchTarget as Pressable} from '../src/components/TouchTarget';
import React from 'react';
import {Alert,Linking,StyleSheet,Text,View} from 'react-native';
import {Ionicons} from '@expo/vector-icons';
import {router} from 'expo-router';
import {Screen} from '../src/components/Screen';
import {colors} from '../src/theme/colors';
const link=async(url:string)=>{try{await Linking.openURL(url)}catch(e){Alert.alert('Unable to open link',e instanceof Error?e.message:'Please try again.')}};
export default function Help(){return <Screen title="Help & Support">
 <Pressable accessibilityRole="button" onPress={()=>router.back()}><Text style={s.back}>‹ More</Text></Pressable>
 <Text style={s.heading}>Using CTSMD Connect</Text>
 <View style={s.card}><Text style={s.title}>Messages & Community</Text><Text style={s.text}>Use Messages for private conversations and Community for posts in channels you are authorized to access. Student conversations follow CTSMD's guardian safeguards.</Text></View>
 <View style={s.card}><Text style={s.title}>Forms & Volunteer</Text><Text style={s.text}>Assigned Forms shows paperwork requiring your attention. Volunteer shows eligible shifts and approval requests; a request is not a confirmed shift.</Text></View>
 <View style={s.card}><Text style={s.title}>Family information</Text><Text style={s.text}>My Family shows current linked relationships. Contact CTSMD leadership through established organization channels if a guardian or student relationship appears incorrect.</Text></View>
 <Text style={s.heading}>Official resources</Text>
 <Pressable accessibilityRole="link" style={s.row} onPress={()=>void link('https://www.ctsmd.org/')}><Text style={s.title}>CTSMD website</Text><Ionicons name="open-outline" size={19} color={colors.muted}/></Pressable>
 <Pressable accessibilityRole="link" style={s.row} onPress={()=>void link('https://www.ctsmd.org/youth-safety-policy.html')}><Text style={s.title}>Youth Safety Policy</Text><Ionicons name="open-outline" size={19} color={colors.muted}/></Pressable>
 <Text style={s.note}>If there is an immediate safety concern, follow CTSMD's established safeguarding procedures. This screen does not submit a report or contact staff.</Text>
 </Screen>}
const s=StyleSheet.create({back:{color:colors.primary,fontWeight:'700'},heading:{color:colors.navy,fontSize:18,fontWeight:'800',marginTop:9},card:{backgroundColor:colors.card,borderColor:colors.border,borderWidth:1,borderRadius:12,padding:15,gap:7},title:{color:colors.text,fontWeight:'700',fontSize:15},text:{color:colors.text,fontSize:14,lineHeight:21},row:{backgroundColor:colors.card,borderColor:colors.border,borderWidth:1,borderRadius:12,padding:16,flexDirection:'row',alignItems:'center',justifyContent:'space-between'},note:{color:colors.muted,fontSize:12,lineHeight:19,marginTop:9}});
