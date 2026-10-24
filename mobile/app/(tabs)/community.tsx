import React,{useCallback} from 'react';
import {Pressable,Text,View} from 'react-native';
import {router,useLocalSearchParams} from 'expo-router';
import {Screen,Item} from '../../src/components/Screen';
import {LoadState,useScreenData} from '../../src/components/LoadState';
import {channels} from '../../src/api/client';
import {colors} from '../../src/theme/colors';
export default function Community(){
 const {production_id}=useLocalSearchParams<{production_id?:string}>();
 const id=Number(production_id);
 const filtered=!!production_id&&Number.isSafeInteger(id)&&id>0;
 const load=useCallback(()=>channels(),[]);
 const {data,error,loading}=useScreenData(load);
 const visible=(data?.channels||[]).filter(c=>!filtered||c.production_id===id);
 const general=visible.filter(c=>c.production_id===null);
 const groups=new Map<number,{title:string;items:typeof visible}>();
 visible.filter(c=>c.production_id!==null).forEach(c=>{const key=c.production_id as number;if(!groups.has(key))groups.set(key,{title:c.production_title||'Production',items:[]});groups.get(key)!.items.push(c)});
 const renderChannel=(c:typeof visible[number])=><Pressable key={c.id} onPress={()=>router.push({pathname:'/community/[id]',params:{id:String(c.id)}})} accessibilityRole="button"><Item title={c.name} subtitle={(c.description||'Community channel')+(c.unread_count>0?' · '+c.unread_count+' unread':'')}/></Pressable>;
 return <Screen title="Community">
  {filtered&&<View style={{flexDirection:'row',justifyContent:'space-between',alignItems:'center'}}><Text style={{color:colors.muted}}>Production channels</Text><Pressable accessibilityRole="button" onPress={()=>router.setParams({production_id:undefined})}><Text style={{color:colors.primary,fontWeight:'700'}}>View all</Text></Pressable></View>}
  <LoadState loading={loading} error={error}/>
  {data&&visible.length===0&&<Text style={{color:colors.muted}}>No channels available.</Text>}
  {general.length>0&&<><Text style={{fontSize:18,fontWeight:'800',color:colors.navy}}>General</Text>{general.map(renderChannel)}</>}
  {Array.from(groups.entries()).map(([id,group])=><View key={id} style={{gap:10}}><Text style={{fontSize:18,fontWeight:'800',color:colors.navy}}>{group.title}</Text>{group.items.map(renderChannel)}</View>)}
 </Screen>;
}
