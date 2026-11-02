import {TouchTarget as Pressable} from '../src/components/TouchTarget';
import React,{useCallback,useState} from 'react';
import {Alert,StyleSheet,Text,View} from 'react-native';
import {router,useFocusEffect,type Href} from 'expo-router';
import {Ionicons} from '@expo/vector-icons';
import {Screen} from '../src/components/Screen';
import {notifications,markNotificationRead} from '../src/api/client';
import type {MobileNotification} from '../src/api/client';
import {colors} from '../src/theme/colors';
import {notificationDestination} from '../src/notifications/destination';

export default function NotificationsScreen(){
 const [items,setItems]=useState<MobileNotification[]>([]);
 const [historyOpen,setHistoryOpen]=useState(false);
 const [loading,setLoading]=useState(true);
 const [error,setError]=useState('');
 const load=useCallback(async()=>{try{setError('');setItems([]);setLoading(true);const result=await notifications();setItems(result.notifications);}catch(e){setError(e instanceof Error?e.message:'Unable to load notifications.');}finally{setLoading(false);}},[]);
 useFocusEffect(useCallback(()=>{void load();return()=>setItems([])},[load]));
 const unread=items.filter(n=>!n.read_at);
 const past=items.filter(n=>!!n.read_at);
 const mark=async(id:number)=>{try{await markNotificationRead(id);setItems(old=>old.map(n=>n.id===id?{...n,read_at:new Date().toISOString()}:n));}catch(e){Alert.alert('Unable to mark as read',e instanceof Error?e.message:'Try again.');}};
 const open=async(n:MobileNotification)=>{
  if(!n.read_at)await mark(n.id);
  if(n.source_type==='casting_offer'){router.push('/action-center');return;}
  const destination=notificationDestination({action_path:n.action_path});
  if(destination!=='/notifications'){router.push(destination as Href);return;}
  // The backend stores web routes, not native deep links. Never open an
  // authenticated PHP web URL with a mobile bearer token.
  if(n.source_type==='form_assignment'){router.push('/(tabs)/forms');return;}
  if(n.source_type==='schedule_change'){router.push('/(tabs)/theatre');return;}
  Alert.alert('Update',n.body);
 };
 const show=(n:MobileNotification)=><View key={n.id} style={s.row}>
  <View style={[s.dot,!!n.read_at&&s.dotRead]}/>
  <View style={{flex:1,gap:5}}>
   <Text style={s.itemTitle}>{n.title}</Text>
   <Text style={s.body}>{n.body}</Text>
   <View style={s.rowActions}><Pressable accessibilityRole="button" onPress={()=>void open(n)} style={s.linkButton}><Text style={s.link}>{n.source_type==='casting_offer'?'Review casting offer':'Open update'} →</Text></Pressable>{!n.read_at&&<Pressable accessibilityRole="button" onPress={()=>void mark(n.id)}><Text style={s.link}>Mark read</Text></Pressable>}</View>
  </View>
 </View>;
 return <Screen title="Notifications">
  <Text style={s.intro}>What needs attention and what has changed.</Text>
  {loading&&<Text style={s.muted}>Loading updates…</Text>}
  {!!error&&<View style={s.card}><Text style={s.muted}>{error}</Text><Pressable onPress={()=>void load()}><Text style={s.link}>Try again</Text></Pressable></View>}
  {!loading&&!error&&<View style={s.card}>
   <View style={s.header}><Text style={s.heading}>Recent updates</Text><Text style={s.count}>{unread.length} unread</Text></View>
   {unread.length?unread.map(show):<Text style={s.muted}>All caught up. No unread notifications.</Text>}
   {!!past.length&&<View style={s.history}>
    <Pressable accessibilityRole="button" accessibilityState={{expanded:historyOpen}} onPress={()=>setHistoryOpen(v=>!v)} style={s.historyToggle}><Ionicons name={historyOpen?'chevron-down':'chevron-forward'} size={18} color={colors.primary}/><Text style={s.historyLabel}>Past notifications</Text><Text style={s.historyCount}>{past.length}</Text></Pressable>
    {historyOpen&&past.map(show)}
   </View>}
  </View>}
  <Pressable accessibilityRole="button" onPress={()=>void load()} style={s.refresh}><Ionicons name="refresh-outline" size={18} color={colors.primary}/><Text style={s.link}>Refresh notifications</Text></Pressable>
 </Screen>;
}
const s=StyleSheet.create({
 intro:{fontSize:14,color:colors.muted,marginBottom:15},card:{backgroundColor:'#fff',borderWidth:1,borderColor:colors.border,borderRadius:16,padding:17,gap:11},
 header:{flexDirection:'row',alignItems:'center',justifyContent:'space-between',gap:8},heading:{fontSize:22,fontWeight:'700',color:colors.navy},count:{fontSize:12,fontWeight:'800',color:colors.primary},muted:{fontSize:13,color:colors.muted,lineHeight:19},
 row:{flexDirection:'row',gap:11,paddingVertical:12,borderTopWidth:1,borderTopColor:colors.border},dot:{width:5,borderRadius:4,backgroundColor:colors.primary},dotRead:{backgroundColor:colors.border},itemTitle:{fontSize:15,fontWeight:'800',color:colors.navy},body:{fontSize:13,color:colors.muted,lineHeight:19},rowActions:{flexDirection:'row',justifyContent:'space-between',alignItems:'center',gap:10,flexWrap:'wrap'},linkButton:{paddingVertical:6},link:{fontSize:13,color:colors.primary,fontWeight:'800'},history:{marginTop:13,borderTopWidth:1,borderTopColor:colors.border},historyToggle:{flexDirection:'row',alignItems:'center',gap:8,paddingVertical:13},historyLabel:{color:colors.navy,fontWeight:'800',flex:1},historyCount:{backgroundColor:'#f4edef',paddingHorizontal:9,paddingVertical:4,borderRadius:20,color:colors.primary,fontWeight:'800'},refresh:{flexDirection:'row',gap:7,padding:14,alignItems:'center'}
});
