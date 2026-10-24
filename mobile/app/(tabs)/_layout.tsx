import React from 'react';import {Tabs} from 'expo-router';import {Ionicons} from '@expo/vector-icons';import {useSafeAreaInsets} from 'react-native-safe-area-context';import {colors} from '../../src/theme/colors';
export default function Layout(){const {bottom}=useSafeAreaInsets();return <Tabs screenOptions={{headerShown:false,tabBarActiveTintColor:colors.primary,tabBarStyle:{height:56+bottom,paddingBottom:Math.max(bottom,8),paddingTop:6}}}>
<Tabs.Screen name="index" options={{title:'Home',tabBarIcon:({color,size})=><Ionicons name="home-outline" color={typeof color==="string"?color:colors.primary} size={size}/>}}/>
<Tabs.Screen name="theatre" options={{title:'Theatre',tabBarIcon:({color,size})=><Ionicons name="ticket-outline" color={typeof color==="string"?color:colors.primary} size={size}/>}}/>
<Tabs.Screen name="community" options={{title:'Community',tabBarIcon:({color,size})=><Ionicons name="people-outline" color={typeof color==="string"?color:colors.primary} size={size}/>}}/>
<Tabs.Screen name="messages" options={{title:'Messages',tabBarIcon:({color,size})=><Ionicons name="chatbubble-outline" color={typeof color==="string"?color:colors.primary} size={size}/>}}/>
<Tabs.Screen name="more" options={{title:'More',tabBarIcon:({color,size})=><Ionicons name="ellipsis-horizontal" color={typeof color==="string"?color:colors.primary} size={size}/>}}/>
<Tabs.Screen name="forms" options={{href:null}}/><Tabs.Screen name="volunteer" options={{href:null}}/>
</Tabs>}
