import React,{useState} from 'react';
import {Alert,Linking,Pressable,StyleSheet,Text,View} from 'react-native';
import {Ionicons} from '@expo/vector-icons';
import {router} from 'expo-router';
import {Screen} from '../../src/components/Screen';
import {useAuth} from '../../src/auth/AuthProvider';
import {colors} from '../../src/theme/colors';

const resources:[string,string][]=[
 ['Tickets','https://ctsmd.ludus.com/index.php'],
 ['Anti-Discrimination Policy','https://www.ctsmd.org/anti-discrimination-policy.html'],
 ['Bylaws','https://www.ctsmd.org/bylaws.html'],
 ["Captain's Program Application",'https://forms.gle/ouqiAP2CUA3GCKUs6'],
 ['Casting Philosophy and Criteria','https://www.ctsmd.org/casting-philosophy.html'],
 ['Code of Conduct','https://www.ctsmd.org/code-of-conduct.html'],
 ['Conflict Resolution Policy','https://www.ctsmd.org/conflict-resolution-policy.html'],
 ['Family Membership Application','https://childrenstheatreofsouthernmaryland.ludus.com/passes.php'],
 ['Production Attendance and Commitment Policy','https://www.ctsmd.org/production-attendance-and-commitment-policy.html'],
 ['Volunteer Handbook','https://www.ctsmd.org/volunteer-handbook.html'],
 ['Volunteer Application','https://childrenstheatreofsouthernmaryland.ludus.com/volunteering.php'],
 ['Youth Safety Policy','https://www.ctsmd.org/youth-safety-policy.html'],
 ['Production Resources','https://ctsmd.me/resources']
];
function External({title,url}:{title:string;url:string}){
 const open=async()=>{try{const supported=await Linking.canOpenURL(url);if(!supported)throw Error('This link cannot be opened on your device.');await Linking.openURL(url)}catch(e){Alert.alert('Unable to open link',e instanceof Error?e.message:'Please try again.')}};
 return <Pressable accessibilityRole="link" onPress={()=>void open()} style={s.item}><Text style={s.itemText}>{title}</Text><Ionicons name="open-outline" size={17} color={colors.muted}/></Pressable>;
}
function Local({title,detail,icon,path}:{title:string;detail:string;icon:'document-text-outline'|'hand-left-outline'|'people-outline';path:'/(tabs)/forms'|'/(tabs)/volunteer'|'/family'}){
 return <Pressable accessibilityRole="button" onPress={()=>router.push(path)} style={s.item}><Ionicons name={icon} size={23} color={colors.primary}/><View style={{flex:1,gap:4}}><Text style={s.itemText}>{title}</Text><Text style={s.muted}>{detail}</Text></View><Ionicons name="chevron-forward" size={18} color={colors.muted}/></Pressable>;
}
export default function More(){
 const {user,signOut}=useAuth();const [expanded,setExpanded]=useState(false);
 return <Screen title="More">
  <Pressable accessibilityRole="button" accessibilityLabel="View My Account" onPress={()=>router.push("/account")} style={s.profile}><View style={s.avatar}><Text style={s.initials}>{user?.initials||'?'}</Text></View><View style={{flex:1,gap:5}}><Text style={s.name}>{user?.name||'My CTSMD'}</Text><Text style={s.muted}>{user?.email||''}</Text></View><Ionicons name="chevron-forward" size={18} color={colors.muted}/></Pressable>
  <Text style={s.heading}>My CTSMD</Text>
  <Local title="My Family" detail="Active student and guardian relationships" icon="people-outline" path="/family"/>
  <Local title="Assigned Forms" detail="Complete and review your paperwork" icon="document-text-outline" path="/(tabs)/forms"/>
  <Local title="Volunteer" detail="Shifts, signups and approvals" icon="hand-left-outline" path="/(tabs)/volunteer"/>
  <Text style={s.heading}>Tickets & resources</Text>
  <External title="Tickets" url={resources[0][1]}/>
  <Pressable accessibilityRole="button" accessibilityState={{expanded}} onPress={()=>setExpanded(v=>!v)} style={s.item}><Ionicons name="library-outline" color={colors.primary} size={22}/><Text style={[s.itemText,{flex:1}]}>CTSMD forms, policies & resources</Text><Ionicons name={expanded?'chevron-up':'chevron-down'} color={colors.muted} size={19}/></Pressable>
  {expanded&&<View style={{gap:7,paddingLeft:12}}>{resources.slice(1).map(([title,url])=><External key={url} title={title} url={url}/>)}</View>}
  <Text style={s.heading}>Account</Text>
  <Pressable accessibilityRole="button" onPress={()=>Alert.alert('Sign out','Sign out of CTSMD Connect on this device?',[{text:'Cancel',style:'cancel'},{text:'Sign out',style:'destructive',onPress:()=>void signOut()}])} style={[s.item,{borderColor:colors.primary}]}><Ionicons name="log-out-outline" color={colors.primary} size={21}/><Text style={[s.itemText,{color:colors.primary}]}>Sign out</Text></Pressable>
 </Screen>;
}
const s=StyleSheet.create({profile:{flexDirection:'row',alignItems:'center',gap:14,padding:16,backgroundColor:colors.card,borderRadius:14,borderWidth:1,borderColor:colors.border},avatar:{width:52,height:52,borderRadius:26,alignItems:'center',justifyContent:'center',backgroundColor:colors.navy},initials:{color:'#fff',fontWeight:'800',fontSize:18},name:{color:colors.navy,fontSize:18,fontWeight:'800'},muted:{color:colors.muted,fontSize:12,lineHeight:17},heading:{fontSize:17,fontWeight:'800',color:colors.navy,marginTop:10},item:{backgroundColor:colors.card,borderColor:colors.border,borderWidth:1,borderRadius:12,padding:15,flexDirection:'row',alignItems:'center',gap:12},itemText:{fontSize:14,fontWeight:'700',color:colors.text,flexShrink:1}});
