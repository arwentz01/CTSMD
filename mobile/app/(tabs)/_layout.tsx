import React from 'react';import {Tabs} from 'expo-router';import {Ionicons} from '@expo/vector-icons';import {useSafeAreaInsets} from 'react-native-safe-area-context';import {colors} from '../../src/theme/colors';
import {useAuth} from '../../src/auth/AuthProvider';
export default function Layout(){const {bottom}=useSafeAreaInsets();const {user}=useAuth();const caregiver=!!user?.caregiver_only;return <Tabs screenOptions={{headerShown:false,tabBarActiveTintColor:colors.primary,tabBarInactiveTintColor:colors.muted,tabBarLabelStyle:{fontSize:12,fontWeight:'600'},tabBarStyle:{backgroundColor:colors.card,borderTopWidth:1,borderTopColor:colors.border,height:61+bottom,paddingBottom:Math.max(bottom,8),paddingTop:7}}}>
<Tabs.Screen name="index" options={{title:'Home',tabBarIcon:({color,size})=><Ionicons name="home-outline" color={typeof color==="string"?color:colors.primary} size={size}/>}}/>
<Tabs.Screen name="theatre" options={{title:'Theatre',href:caregiver?null:undefined,tabBarIcon:({color,size})=><Ionicons name="ticket-outline" color={typeof color==="string"?color:colors.primary} size={size}/>}}/>
<Tabs.Screen name="community" options={{title:'Community',href:caregiver?null:undefined,tabBarIcon:({color,size})=><Ionicons name="people-outline" color={typeof color==="string"?color:colors.primary} size={size}/>}}/>
<Tabs.Screen name="messages" options={{title:'Messages',tabBarIcon:({color,size})=><Ionicons name="chatbubble-outline" color={typeof color==="string"?color:colors.primary} size={size}/>}}/>
<Tabs.Screen name="more" options={{title:'More',tabBarIcon:({color,size})=><Ionicons name="ellipsis-horizontal" color={typeof color==="string"?color:colors.primary} size={size}/>}}/>
<Tabs.Screen name="whats-new" options={{href:null}}/><Tabs.Screen name="forms" options={{href:null}}/><Tabs.Screen name="volunteer" options={{href:null}}/>
</Tabs>}
