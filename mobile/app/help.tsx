import {TouchTarget as Pressable} from '../src/components/TouchTarget';
import React,{useEffect,useMemo,useState} from 'react';
import {Alert,Linking,StyleSheet,Text,TextInput,View} from 'react-native';
import {Ionicons} from '@expo/vector-icons';
import {router} from 'expo-router';
import {Screen} from '../src/components/Screen';
import {colors} from '../src/theme/colors';
import {openWebsite} from '../src/navigation/website';
import {helpGuides} from '../src/api/client';
import type {HelpGuide} from '../src/types/ctsmd';
const link=async(url:string)=>{try{await Linking.openURL(url)}catch(e){Alert.alert('Unable to open link',e instanceof Error?e.message:'Please try again.')}};
export default function Help(){
 const [guides,setGuides]=useState<HelpGuide[]>([]);
 const [loading,setLoading]=useState(true);
 const [error,setError]=useState('');
 const [query,setQuery]=useState('');
 const [open,setOpen]=useState<string[]>([]);
 const [category,setCategory]=useState('All');
 const categories=useMemo(()=>['All',...Array.from(new Set(guides.map(g=>g.category)))],[guides]);
 const filtered=useMemo(()=>guides.filter(g=>(category==='All'||g.category===category)&&[g.title,g.intro,g.category,...g.steps].join(' ').toLowerCase().includes(query.trim().toLowerCase())),[guides,category,query]);
 useEffect(()=>{
  let active=true;
  void helpGuides().then(result=>{if(active){setGuides(result.guides);setError('');}})
   .catch(e=>{if(active)setError(e instanceof Error?e.message:'Unable to load help guides.');})
   .finally(()=>{if(active)setLoading(false)});
  return ()=>{active=false};
 },[]);
 const toggle=(id:string)=>setOpen(current=>current.includes(id)?current.filter(value=>value!==id):[...current,id]);
 return <Screen title="Help & Support">
 <Pressable accessibilityRole="button" onPress={()=>router.back()}><Text style={s.back}>‹ More</Text></Pressable>
 <Pressable accessibilityRole="link" accessibilityLabel="Open step-by-step CTSMD Help and Guides website" style={s.guideLink} onPress={()=>void openWebsite('helpGuides').catch(e=>Alert.alert('Unable to open Help & Guides',e instanceof Error?e.message:'Please try again.'))}>
  <View style={{flex:1,gap:5}}><Text style={s.guideTitle}>Full Help Center on the website</Text><Text style={s.guideDescription}>Open the same guides in your browser when you need to work with website-only tools.</Text><Text style={s.guideHint}>Opens CTSMD.me</Text></View><Ionicons name="open-outline" size={22} color={colors.primary}/>
 </Pressable>
 <Text style={s.heading}>Step-by-step guides</Text>
 <Text style={s.text}>Search practical instructions for your account, family and production responsibilities.</Text>
 <TextInput accessibilityLabel="Search help guides" placeholder="Search help guides" placeholderTextColor={colors.muted} value={query} onChangeText={setQuery} style={s.search}/>
 <View style={s.categories}>{categories.map(item=><Pressable key={item} accessibilityRole="button" accessibilityState={{selected:item===category}} onPress={()=>setCategory(item)} style={[s.category,item===category&&s.categoryActive]}><Text style={[s.categoryText,item===category&&s.categoryActiveText]}>{item}</Text></Pressable>)}</View>
 {loading&&<Text style={s.note}>Loading your Help guides…</Text>}
 {!!error&&<Text accessibilityRole="alert" style={s.note}>Guides could not load in the app: {error}. You can still open the website Help Center above.</Text>}
 {!loading&&!error&&filtered.length===0&&<Text style={s.note}>No guides match your search. Try a shorter phrase or choose All.</Text>}
 {filtered.map(g=><View key={g.id} style={s.article}>
  <Pressable accessibilityRole="button" accessibilityState={{expanded:open.includes(g.id)}} onPress={()=>toggle(g.id)} style={s.articleHeader}><View style={{flex:1,gap:4}}><Text style={s.articleCategory}>{g.category} · {g.audience}</Text><Text style={s.guideTitle}>{g.title}</Text><Text style={s.guideDescription}>{g.intro}</Text></View><Ionicons name={open.includes(g.id)?'chevron-up':'chevron-down'} size={21} color={colors.primary}/></Pressable>
  {open.includes(g.id)&&<View style={s.articleBody}>
   <Text style={s.articleCategory}>WHERE: {g.where}</Text>
   {g.steps.map((step,index)=><View key={index} style={s.guideStep}><Text style={s.stepNumber}>{index+1}.</Text><Text style={s.stepText}>{step}</Text></View>)}
   <View style={s.guideTip}><Text style={s.title}>Good to know</Text><Text style={s.text}>{g.tip}</Text></View>
   <Pressable accessibilityRole="link" onPress={()=>void openWebsite('helpGuides').catch(e=>Alert.alert('Unable to open Help',e instanceof Error?e.message:'Please try again.'))}><Text style={s.link}>Open website Help Center <Ionicons name="open-outline" size={13} color={colors.primary}/></Text></Pressable>
  </View>}
 </View>)}
 <Text style={s.heading}>Using CTSMD Connect</Text>
 <View style={s.card}><Text style={s.title}>Messages & Community</Text><Text style={s.text}>Use Messages for private conversations and Community for posts in channels you are authorized to access. Student conversations follow CTSMD's guardian safeguards.</Text></View>
 <View style={s.card}><Text style={s.title}>Forms & Volunteer</Text><Text style={s.text}>Assigned Forms shows paperwork requiring your attention. Volunteer shows eligible shifts and approval requests; a request is not a confirmed shift.</Text></View>
 <View style={s.card}><Text style={s.title}>Family information</Text><Text style={s.text}>My Family shows current linked relationships. Contact CTSMD leadership through established organization channels if a guardian or student relationship appears incorrect.</Text></View>
 <Text style={s.heading}>Official resources</Text>
 <Pressable accessibilityRole="link" style={s.row} onPress={()=>void link('https://www.ctsmd.org/')}><Text style={s.title}>CTSMD website</Text><Ionicons name="open-outline" size={19} color={colors.muted}/></Pressable>
 <Pressable accessibilityRole="link" style={s.row} onPress={()=>void link('https://www.ctsmd.org/youth-safety-policy.html')}><Text style={s.title}>Youth Safety Policy</Text><Ionicons name="open-outline" size={19} color={colors.muted}/></Pressable>
 <Text style={s.note}>If there is an immediate safety concern, follow CTSMD's established safeguarding procedures. This screen does not submit a report or contact staff.</Text>
 </Screen>}
const s=StyleSheet.create({
 search:{backgroundColor:colors.card,borderColor:colors.border,borderWidth:1,borderRadius:10,paddingHorizontal:12,paddingVertical:11,color:colors.text,fontSize:15},
 categories:{flexDirection:'row',flexWrap:'wrap',gap:7},
 category:{paddingVertical:9,paddingHorizontal:12,borderWidth:1,borderColor:colors.border,borderRadius:9,backgroundColor:colors.card},
 categoryActive:{backgroundColor:colors.primary,borderColor:colors.primary},
 categoryText:{fontSize:12,fontWeight:'700',color:colors.text},
 categoryActiveText:{color:'#fff'},
 article:{backgroundColor:colors.card,borderWidth:1,borderColor:colors.border,borderRadius:12,overflow:'hidden'},
 articleHeader:{padding:14,flexDirection:'row',alignItems:'center',gap:8},
 articleCategory:{fontSize:11,fontWeight:'800',color:colors.primary},
 articleBody:{padding:14,borderTopWidth:1,borderTopColor:colors.border,gap:12},
 guideStep:{flexDirection:'row',gap:10,alignItems:'flex-start'},
 stepNumber:{width:20,fontWeight:'800',fontSize:14,color:colors.primary},
 stepText:{flex:1,fontSize:14,lineHeight:21,color:colors.text},
 guideTip:{padding:12,backgroundColor:colors.background,borderRadius:9,gap:5},
 link:{color:colors.primary,fontSize:13,fontWeight:'800',paddingVertical:9},
 guideLink:{backgroundColor:colors.card,borderColor:colors.primary,borderWidth:1,borderRadius:12,padding:17,flexDirection:'row',alignItems:'center',gap:12,minHeight:104},guideTitle:{color:colors.text,fontWeight:'800',fontSize:17},guideDescription:{color:colors.text,fontSize:13,lineHeight:19},guideHint:{color:colors.primary,fontSize:12,fontWeight:'700'},back:{color:colors.primary,fontWeight:'700'},heading:{color:colors.navy,fontSize:18,fontWeight:'800',marginTop:9},card:{backgroundColor:colors.card,borderColor:colors.border,borderWidth:1,borderRadius:12,padding:15,gap:7},title:{color:colors.text,fontWeight:'700',fontSize:15},text:{color:colors.text,fontSize:14,lineHeight:21},row:{backgroundColor:colors.card,borderColor:colors.border,borderWidth:1,borderRadius:12,padding:16,flexDirection:'row',alignItems:'center',justifyContent:'space-between'},note:{color:colors.muted,fontSize:12,lineHeight:19,marginTop:9}});
