import React,{useCallback} from 'react';
import {Text,StyleSheet} from 'react-native';
import {useLocalSearchParams} from 'expo-router';
import {Screen,Item} from '../../src/components/Screen';
import {LoadState,useScreenData} from '../../src/components/LoadState';
import {eventDetail} from '../../src/api/client';
import {colors} from '../../src/theme/colors';
export default function EventDetails(){
 const {id}=useLocalSearchParams<{id:string}>(),eventId=Number(id);
 const load=useCallback(()=>Number.isSafeInteger(eventId)&&eventId>0?eventDetail(eventId):Promise.reject(new Error('Invalid event.')),[eventId]);
 const {data,loading,error,retry}=useScreenData(load),event=data?.event;
 const date=(value:string)=>{const d=new Date(value.replace(' ','T'));return Number.isFinite(d.getTime())?d.toLocaleString():value};
 return <Screen title="Schedule details"><LoadState loading={loading} error={error} onRetry={retry}/>{event&&<>
  <Item title={event.title} subtitle={event.production_title}/>
  <Item title="When" subtitle={date(event.starts_at)+(event.ends_at?' – '+date(event.ends_at):'')}/>
  <Item title="Where" subtitle={event.location||'Location not provided'}/>
  <Text style={s.copy}>{event.item_type} · {event.status}</Text>
  {!!event.family_call_at&&<Item title="Family call time" subtitle={date(event.family_call_at)}/>}
  {!!(event.notes||event.description)&&<Text style={s.copy}>{event.notes||event.description}</Text>}
  <Text style={s.copy}>Visibility follows your current CTSMD permissions. A visible event does not by itself confirm a personal assignment.</Text>
 </>}</Screen>;
}
const s=StyleSheet.create({copy:{color:colors.text,fontSize:16,lineHeight:24}});
