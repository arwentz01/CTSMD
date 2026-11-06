import React,{useState} from 'react';
import {Alert,Linking,StyleSheet,Text,View} from 'react-native';
import {Ionicons} from '@expo/vector-icons';
import {router} from 'expo-router';
import {Screen} from '../../src/components/Screen';
import {TouchTarget} from '../../src/components/TouchTarget';
import {useAuth} from '../../src/auth/AuthProvider';
import {colors} from '../../src/theme/colors';
import {openWebsite,type WebRoute} from '../../src/navigation/website';

type Icon=React.ComponentProps<typeof Ionicons>['name'];
const policies:[string,string][]=[
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
 ['Youth Safety Policy','https://www.ctsmd.org/youth-safety-policy.html']
];
const LUDUS_TICKETS='https://ctsmd.ludus.com/index.php';
type LinkItem={title:string;description?:string;icon:Icon;native?:string;website?:WebRoute;external?:string};
function Destination({item}:{item:LinkItem}){
 const external=item.website!==undefined||item.external!==undefined;
 const go=async()=>{
  if(item.native){router.push(item.native as never);return;}
  try{
   if(item.website)await openWebsite(item.website);
   else if(item.external){
    if(!await Linking.canOpenURL(item.external))throw new Error('This link is unavailable on your device.');
    await Linking.openURL(item.external);
   }
  }catch(error){Alert.alert('Unable to open',error instanceof Error?error.message:'Please try again.');}
 };
 return <TouchTarget accessibilityRole={external?'link':'button'} accessibilityLabel={item.title+(external?', opens website':'')} onPress={()=>void go()} style={s.destination}>
  <View style={s.icon}><Ionicons name={item.icon} color={colors.primary} size={21}/></View>
  <View style={s.destinationCopy}><Text style={s.destinationTitle}>{item.title}</Text>{item.description&&<Text style={s.description}>{item.description}</Text>}</View>
  <Ionicons name={external?'open-outline':'chevron-forward'} color={colors.muted} size={18}/>
 </TouchTarget>;
}
function Section({title,description,items}:{title:string;description?:string;items:LinkItem[]}){
 return <View style={s.section}><View style={s.sectionHead}><Text accessibilityRole="header" style={s.sectionTitle}>{title}</Text>{description&&<Text style={s.description}>{description}</Text>}</View><View style={s.sectionBody}>{items.map(item=><Destination key={item.title} item={item}/>)}</View></View>;
}
export default function More(){
 const {user,signOut}=useAuth();const [expanded,setExpanded]=useState(false);
 const caregiver=!!user?.caregiver_only;
 const staff=!!user?.roles?.some(role=>role==='production_staff'||role==='administrator');
 const admin=!!user?.roles?.includes('administrator');
 const member:LinkItem[]=[
  {title:'My family',description:'Children, parents and guardians',icon:'people-outline',native:'/family'},
  {title:'Forms',description:'Assigned forms and acknowledgements',icon:'document-text-outline',native:'/(tabs)/forms'},
  {title:'Volunteer',description:'Signups and commitments',icon:'hand-left-outline',native:'/(tabs)/volunteer'},
  {title:'Video Library',description:'Rehearsal recordings and shared videos',icon:'play-circle-outline',native:'/videos'},
  {title:'Performer profile',description:'Manage your performer details and consent · opens in browser',icon:'person-circle-outline',website:'performerProfile'},
  {title:'Theatre history',description:'Past roles and shows · opens in browser',icon:'time-outline',website:'theatreHistory'},
  {title:'Auditions & registration',description:'Applications and audition forms · website',icon:'clipboard-outline',website:'registration'},
  {title:'Family & pickup management',description:'Authorized guardians and pickup people · website',icon:'shield-checkmark-outline',website:'familyManagement'}
 ];
 const caregiverItems:LinkItem[]=[
  {title:'My family',description:'Active caregiver relationships',icon:'people-outline',native:'/family'},
  {title:'Caregiver schedule',description:'Upcoming calls authorized for your care',icon:'calendar-outline',native:'/caregiver-schedule'}
 ];
 const staffItems:LinkItem[]=[
  {title:'Production workspace',description:'Manage production details · opens in browser',icon:'film-outline',website:'productionWorkspace'},
  {title:'Production day & attendance',description:'Today's schedule and attendance · opens in browser',icon:'calendar-outline',website:'productionDay'},
  {title:'Production people',description:'Cast, crew and assignments · website',icon:'people-circle-outline',website:'productionPeople'},
  {title:'Production tasks',description:'Create, delegate and track work · website',icon:'checkmark-done-outline',website:'productionTasks'},
  {title:'Readiness & notices',description:'Pre-show status and communications · website',icon:'list-outline',website:'productionReadiness'},
  {title:'Playbills',description:'Production program tools · website',icon:'book-outline',website:'playbills'}
 ];
 const adminItems:LinkItem[]=[
  {title:'Operations',description:'Administrative workspace · website',icon:'grid-outline',website:'adminOperations'},
  {title:'Accounts & authorizations',description:'Role and relationship administration · website',icon:'key-outline',website:'adminAccounts'},
  {title:'Commerce & Box Office',description:'Ticketing administration and promotions · website',icon:'card-outline',website:'commerce'}
 ];
 return <Screen title="More">
  <TouchTarget accessibilityRole="button" accessibilityLabel="View My Account" onPress={()=>router.push('/account')} style={s.profile}>
   <View style={s.avatar}><Text style={s.initials}>{user?.initials||'?'}</Text></View>
   <View style={s.profileDetails}><Text style={s.profileEyebrow}>MY CTSMD ACCOUNT</Text><Text style={s.profileName}>{user?.name||'My account'}</Text><Text numberOfLines={1} style={s.profileEmail}>{user?.email||''}</Text></View>
   <Ionicons name="chevron-forward" color={colors.ivory} size={20}/>
  </TouchTarget>
  <Section title={caregiver?"Family logistics":"My Theatre"} items={caregiver?caregiverItems:member}/>
  {!caregiver&&staff&&<Section title="Production tools" description="Advanced staff workflows currently open the CTSMD website." items={staffItems}/>}
  {!caregiver&&admin&&<Section title="Administration" description="Permission-controlled web workspaces." items={adminItems}/>}
  <Section title="Tickets & resources" description="Ticket purchases currently use Ludus. CTSMD's own storefront remains a pilot." items={[
   {title:'Buy tickets',description:'Open the current Ludus ticket store',icon:'ticket-outline',external:LUDUS_TICKETS},
   {title:'Production resources',description:'Handbooks and production documents · website',icon:'folder-outline',website:'productionResources'}
  ]}/>
  <View style={s.section}>
   <TouchTarget accessibilityRole="button" accessibilityState={{expanded}} onPress={()=>setExpanded(v=>!v)} style={s.disclosure}><Ionicons name="library-outline" color={colors.primary} size={21}/><Text style={s.destinationTitle}>Policies & reference documents</Text><Ionicons name={expanded?'chevron-up':'chevron-down'} color={colors.muted} size={19}/></TouchTarget>
   {expanded&&<View style={s.sectionBody}>{policies.map(([title,url])=><Destination key={url} item={{title,icon:'document-text-outline',external:url}}/>)}</View>}
  </View>
  <Section title="Settings & support" items={[
   {title:'Settings',description:'Notifications and device preferences',icon:'settings-outline',native:'/settings'},
   {title:'Help & support',description:'Find guidance and contact CTSMD',icon:'help-circle-outline',native:'/help'}
  ]}/>
  <TouchTarget accessibilityRole="button" onPress={()=>Alert.alert('Sign out','Sign out of CTSMD Connect on this device?',[{text:'Cancel',style:'cancel'},{text:'Sign out',style:'destructive',onPress:()=>void signOut()}])} style={s.signOut}><Ionicons name="log-out-outline" color={colors.primary} size={20}/><Text style={s.destinationTitle}>Sign out</Text></TouchTarget>
 </Screen>;
}
const s=StyleSheet.create({
 profile:{flexDirection:'row',alignItems:'center',padding:16,gap:12,backgroundColor:colors.burgundy,borderRadius:15,borderWidth:1,borderColor:colors.gold},
 avatar:{width:48,height:48,borderRadius:24,alignItems:'center',justifyContent:'center',backgroundColor:colors.stage,borderWidth:1,borderColor:colors.gold},
 initials:{fontSize:16,fontWeight:'800',color:'#fff'},profileDetails:{flex:1,gap:3},profileEyebrow:{fontSize:10,letterSpacing:1.2,fontWeight:'800',color:colors.gold},profileName:{fontSize:19,fontWeight:'700',color:colors.ivory},profileEmail:{fontSize:12,color:'#f5dcd2'},
 section:{gap:9},sectionHead:{gap:3},sectionTitle:{fontSize:19,fontWeight:'700',color:colors.navy},description:{fontSize:12,lineHeight:18,color:colors.muted},
 sectionBody:{backgroundColor:colors.card,borderWidth:1,borderColor:colors.border,borderRadius:13,overflow:'hidden'},
 destination:{flexDirection:'row',alignItems:'center',gap:12,paddingHorizontal:13,paddingVertical:10,minHeight:62,borderBottomWidth:StyleSheet.hairlineWidth,borderBottomColor:colors.border},
 icon:{height:36,width:36,borderRadius:10,backgroundColor:colors.background,alignItems:'center',justifyContent:'center'},
 destinationCopy:{flex:1,gap:3},destinationTitle:{flex:1,color:colors.text,fontSize:15,fontWeight:'600'},disclosure:{flexDirection:'row',alignItems:'center',gap:12,backgroundColor:colors.card,borderWidth:1,borderColor:colors.border,borderRadius:13,padding:14},
 signOut:{flexDirection:'row',alignItems:'center',gap:10,minHeight:52,paddingHorizontal:14,borderRadius:12,borderWidth:1,borderColor:colors.border,backgroundColor:colors.card}
});
