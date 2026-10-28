import React,{useCallback,useState} from 'react';
import {ActivityIndicator,Pressable,StyleSheet,Text,View} from 'react-native';
import {useFocusEffect} from 'expo-router';
import {colors} from '../theme/colors';
export function useScreenData<T>(load:()=>Promise<T>){
 const [data,setData]=useState<T|null>(null),[error,setError]=useState(''),[loading,setLoading]=useState(true),[revision,setRevision]=useState(0);
 const retry=useCallback(()=>setRevision(value=>value+1),[]);
 useFocusEffect(useCallback(()=>{
  let active=true;setData(null);setLoading(true);setError('');
  Promise.resolve().then(load).then(value=>{if(active)setData(value)}).catch(e=>{if(active){setData(null);setError(e instanceof Error?e.message:'Unable to load.')}}).finally(()=>{if(active)setLoading(false)});
  return()=>{active=false;setData(null)};
 },[load,revision]));
 return {data,error,loading,retry};
}
export function LoadState({loading,error,onRetry}:{loading:boolean;error:string;onRetry?:()=>void}){
 if(!loading&&!error)return null;
 return <View style={s.state} accessibilityLiveRegion="polite">
  {loading&&<View style={s.loading}><ActivityIndicator accessibilityLabel="Loading" color={colors.primary}/><Text style={s.copy}>Loading…</Text></View>}
  {!!error&&<><Text accessibilityRole="alert" style={s.error}>{error}</Text><Text style={s.copy}>Check your connection and try again.</Text>{onRetry&&<Pressable accessibilityRole="button" accessibilityLabel="Try loading again" onPress={onRetry} style={s.retry}><Text style={s.link}>Try again</Text></Pressable>}</>}
 </View>;
}
const s=StyleSheet.create({state:{padding:16,borderRadius:12,backgroundColor:colors.card,gap:10},loading:{flexDirection:'row',gap:12,alignItems:'center'},copy:{color:colors.muted,fontSize:15,lineHeight:22},error:{color:colors.danger,fontSize:15,lineHeight:22},retry:{minHeight:48,alignSelf:'flex-start',justifyContent:'center',paddingHorizontal:16,borderWidth:1,borderColor:colors.control,borderRadius:6},link:{color:colors.primary,fontWeight:'600',fontSize:15}});
