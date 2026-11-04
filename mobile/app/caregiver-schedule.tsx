import React,{useCallback} from 'react';
import {StyleSheet,Text,View} from 'react-native';
import {Ionicons} from '@expo/vector-icons';
import {Screen} from '../src/components/Screen';
import {LoadState,useScreenData} from '../src/components/LoadState';
import {caregiverSchedule} from '../src/api/client';
import {colors} from '../src/theme/colors';

function dateLabel(value:string):string {
 const [date]=value.split(' '),[year,month,day]=date.split('-').map(Number);
 if(!year||!month||!day)return date;
 return new Date(year,month-1,day,12).toLocaleDateString(undefined,{weekday:'short',month:'short',day:'numeric'});
}
function timeLabel(value:string):string {
 const parts=value.match(/\b(\d{2}):(\d{2})(?::\d{2})?$/);
 if(!parts)return value;
 const hour=Number(parts[1]);
 return (hour%12||12)+':'+parts[2]+' '+(hour<12?'AM':'PM');
}
export default function CaregiverSchedule(){
 const load=useCallback(()=>caregiverSchedule(),[]);
 const {data,error,loading,retry}=useScreenData(load);
 const calls=data?.calls||[];
 return <Screen title="Caregiver schedule">
  <View style={s.notice}>
   <Ionicons name="shield-checkmark-outline" size={22} color={colors.primary}/>
   <Text style={s.noticeText}>Only upcoming logistics for students you're currently authorized to support are shown here. Casting, accounts and other private production details aren't included.</Text>
  </View>
  <LoadState loading={loading} error={error} onRetry={retry}/>
  {data&&calls.length===0&&<View style={s.empty}><Ionicons name="calendar-outline" size={24} color={colors.muted}/><Text style={s.title}>No upcoming calls</Text><Text style={s.detail}>There's nothing on your caregiver schedule in the next 45 days.</Text></View>}
  {calls.map((call,index)=><View key={[call.student_first_name,call.starts_at,call.title,index].join(':')} style={s.item}>
   <View style={s.date}><Text style={s.dateText}>{dateLabel(call.starts_at)}</Text><Text style={s.time}>{timeLabel(call.starts_at)}</Text></View>
   <View style={s.copy}><Text style={s.title}>{call.title}</Text><Text style={s.detail}>{call.student_first_name} · {call.production_title}</Text>
    {!!call.location&&<View style={s.location}><Ionicons name="location-outline" color={colors.muted} size={14}/><Text style={s.detail}>{call.location}</Text></View>}
   </View>
  </View>)}
  <Text style={s.footnote}>Schedule visibility follows current CTSMD caregiver authorization. Contact a parent or production staff for details not shown here.</Text>
 </Screen>;
}
const s=StyleSheet.create({
 notice:{flexDirection:'row',gap:11,padding:13,backgroundColor:colors.ivory,borderRadius:11,alignItems:'flex-start'},
 noticeText:{flex:1,fontSize:13,lineHeight:19,color:colors.text},
 item:{flexDirection:'row',gap:12,alignItems:'center',backgroundColor:colors.card,borderRadius:12,borderWidth:1,borderColor:colors.border,padding:12},
 date:{width:90,gap:3},dateText:{fontSize:12,fontWeight:'800',color:colors.primary},time:{fontSize:13,fontWeight:'700',color:colors.navy},
 copy:{flex:1,gap:4},title:{fontSize:15,fontWeight:'700',color:colors.text},detail:{fontSize:12,lineHeight:18,color:colors.muted},location:{flexDirection:'row',alignItems:'center',gap:4},
 empty:{alignItems:'center',gap:7,padding:25,backgroundColor:colors.card,borderWidth:1,borderColor:colors.border,borderRadius:12},
 footnote:{color:colors.muted,fontSize:12,lineHeight:18}
});
