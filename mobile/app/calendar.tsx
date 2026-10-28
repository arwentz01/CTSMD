import React,{useCallback} from 'react';
import {Text} from 'react-native';
import {router} from 'expo-router';
import {Screen,Item} from '../src/components/Screen';
import {TouchTarget} from '../src/components/TouchTarget';
import {LoadState,useScreenData} from '../src/components/LoadState';
import {theatre} from '../src/api/client';
export default function Calendar(){
 const load=useCallback(()=>theatre(),[]),{data,error,loading,retry}=useScreenData(load);
 const events=[...(data?.events||[])].sort((a,b)=>a.starts_at.localeCompare(b.starts_at));
 return <Screen title="Your schedule"><Text>All events returned in your current authorized schedule window.</Text><LoadState loading={loading} error={error} onRetry={retry}/>
  {data&&!events.length&&<Item title="No visible schedule events" subtitle="Check again after CTSMD posts the next call."/>}
  {events.map(event=><TouchTarget key={event.id} accessibilityLabel={'Open '+event.title} onPress={()=>router.push({pathname:'/events/[id]',params:{id:String(event.id)}})}><Item title={event.title} subtitle={[event.production_title,event.starts_at,event.location].filter(Boolean).join(' · ')}/></TouchTarget>)}
 </Screen>;
}
