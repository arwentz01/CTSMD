import React,{useState} from 'react';
import {StyleSheet,Text,View} from 'react-native';
import Constants from 'expo-constants';
import {Ionicons} from '@expo/vector-icons';
import {Screen} from '../../../src/components/Screen';
import {TouchTarget} from '../../../src/components/TouchTarget';
import {colors} from '../../../src/theme/colors';
import {nextReleasePreview,releaseNotes,releaseStatusLabel} from '../../../src/content/releaseNotes';

export default function WhatsNew(){
 const [expanded,setExpanded]=useState<string[]>(releaseNotes[0]?[releaseNotes[0].version]:[]);
 const [openSections,setOpenSections]=useState<string[]>([]);
 const [previewOpen,setPreviewOpen]=useState(true);
 const toggleSection=(key:string)=>setOpenSections(previous=>previous.includes(key)?previous.filter(item=>item!==key):[...previous,key]);
 const currentVersion=Constants.expoConfig?.version||'Unknown';
 const toggle=(version:string)=>setExpanded(previous=>previous.includes(version)?previous.filter(item=>item!==version):[...previous,version]);
 return <Screen title="What's New" compact>
  <View style={s.intro}>
   <View style={s.introIcon}><Ionicons name="sparkles-outline" size={25} color={colors.gold}/></View>
   <View style={s.introCopy}><Text style={s.introHeading}>What's new in CTSMD Connect</Text><Text style={s.introText}>See what's changed with each app update, from new features to everyday improvements.</Text></View>
  </View>
  <Text style={s.installed}>App version on this device: {currentVersion}</Text>
  <View style={s.preview}>
   <TouchTarget accessibilityRole="button" accessibilityState={{expanded:previewOpen}} accessibilityLabel="Coming in the next app update" onPress={()=>setPreviewOpen(value=>!value)} style={s.cardHeader}>
    <View style={s.cardHeaderCopy}><Text style={s.version}>NEXT APP UPDATE · PREVIEW</Text><Text style={s.releaseTitle}>{nextReleasePreview.title}</Text><Text style={s.status}>Not released yet · Version and date to be announced</Text></View>
    <Ionicons name={previewOpen?'chevron-up':'chevron-down'} size={22} color={colors.primary}/>
   </TouchTarget>
   {previewOpen&&<View style={s.cardBody}>
    <Text style={s.description}>{nextReleasePreview.introduction}</Text>
    {nextReleasePreview.sections.map(section=>{
     const key='preview:'+section.title;const open=openSections.includes(key);
     return <View key={key} style={s.section}>
      <TouchTarget accessibilityRole="button" accessibilityState={{expanded:open}} onPress={()=>toggleSection(key)} style={s.sectionHeader}>
       <View style={s.sectionCopy}><Text style={s.sectionTitle}>{section.title}</Text><Text style={s.sectionSummary}>{section.summary}</Text></View>
       <Ionicons name={open?'chevron-up':'chevron-down'} size={20} color={colors.primary}/>
      </TouchTarget>
      {open&&<View style={s.details}>{section.items.map((item,i)=><View key={i} style={s.item}><View style={s.dot}/><Text style={s.itemText}>{item}</Text></View>)}</View>}
     </View>;
    })}
   </View>}
  </View>
  {releaseNotes.map((note,index)=>{
   const open=expanded.includes(note.version);
   return <View key={note.version} style={s.card}>
    <TouchTarget accessibilityRole="button" accessibilityState={{expanded:open}} accessibilityLabel={'Version '+note.version+', '+note.title+', '+releaseStatusLabel(note)} onPress={()=>toggle(note.version)} style={s.cardHeader}>
     <View style={s.cardHeaderCopy}>
      <Text style={s.version}>RELEASE {note.releaseNumber} · VERSION {note.version}{index===0?' · LATEST NOTES':''}</Text>
      <Text style={s.releaseTitle}>{note.title}</Text>
      <Text style={s.status}>{releaseStatusLabel(note)}</Text>
     </View>
     <Ionicons name={open?'chevron-up':'chevron-down'} size={22} color={colors.primary}/>
    </TouchTarget>
    {open&&<View style={s.cardBody}>
     <Text style={s.description}>{note.introduction}</Text>
     {note.sections.map(section=>{
      const sectionKey=note.version+':'+section.title;
      const sectionOpen=openSections.includes(sectionKey);
      return <View key={sectionKey} style={s.section}>
       <TouchTarget accessibilityRole="button" accessibilityState={{expanded:sectionOpen}} accessibilityLabel={section.title+', '+section.summary} onPress={()=>toggleSection(sectionKey)} style={s.sectionHeader}>
        <View style={s.sectionCopy}>
         <Text style={s.sectionTitle}>{section.title}</Text>
         <Text style={s.sectionSummary}>{section.summary}</Text>
        </View>
        <Ionicons name={sectionOpen?'chevron-up':'chevron-down'} size={20} color={colors.primary}/>
       </TouchTarget>
       {sectionOpen&&<View style={s.details}>
        {section.items.map((item,i)=><View key={i} style={s.item}>
         <View style={s.dot}/>
         <Text style={s.itemText}>{item}</Text>
        </View>)}
       </View>}
      </View>;
     })}
    </View>}
   </View>;
  })}
  <Text style={s.footer}>Coming next is a preview, not a released update. Release 2 is for our closed-testing group. Features appear only when the corresponding app version and services are available.</Text>
 </Screen>;
}
const s=StyleSheet.create({
 intro:{flexDirection:'row',alignItems:'flex-start',gap:12,padding:15,backgroundColor:colors.burgundy,borderRadius:14},
 introIcon:{width:42,height:42,borderRadius:12,borderWidth:1,borderColor:colors.gold,justifyContent:'center',alignItems:'center'},
 introCopy:{flex:1,gap:6},introHeading:{fontSize:17,fontWeight:'800',color:colors.ivory},introText:{fontSize:13,color:colors.ivory,lineHeight:19},
 installed:{fontSize:13,color:colors.muted,paddingHorizontal:2},
 card:{backgroundColor:colors.card,borderWidth:1,borderColor:colors.border,borderRadius:14,overflow:'hidden'},
 preview:{backgroundColor:colors.card,borderWidth:1,borderColor:colors.primary,borderRadius:14,overflow:'hidden'},
 cardHeader:{minHeight:90,paddingHorizontal:15,paddingVertical:14,flexDirection:'row',alignItems:'center',gap:12},
 cardHeaderCopy:{flex:1,gap:5},version:{fontSize:11,fontWeight:'800',letterSpacing:1.2,color:colors.primary},
 releaseTitle:{fontSize:18,fontWeight:'800',color:colors.text},status:{fontSize:12,color:colors.muted},
 cardBody:{paddingHorizontal:16,paddingBottom:16,gap:16,borderTopWidth:1,borderTopColor:colors.border,paddingTop:14},
 description:{fontSize:14,lineHeight:21,color:colors.text},section:{backgroundColor:colors.background,borderRadius:10,overflow:'hidden'},
 sectionHeader:{flexDirection:'row',alignItems:'center',gap:12,paddingVertical:10,paddingHorizontal:12,minHeight:66},sectionCopy:{flex:1,gap:4},sectionSummary:{fontSize:13,lineHeight:18,color:colors.muted},sectionTitle:{fontSize:16,fontWeight:'800',color:colors.navy},details:{paddingHorizontal:12,paddingBottom:14,gap:9,borderTopWidth:StyleSheet.hairlineWidth,borderTopColor:colors.border,paddingTop:12},
 item:{flexDirection:'row',alignItems:'flex-start',gap:10},
 dot:{width:6,height:6,borderRadius:3,backgroundColor:colors.gold,marginTop:8},
 itemText:{flex:1,fontSize:14,lineHeight:21,color:colors.text},
 footer:{fontSize:12,color:colors.muted,lineHeight:18,paddingHorizontal:3}
});
