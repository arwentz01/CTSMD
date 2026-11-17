import {TouchTarget as Pressable} from '../src/components/TouchTarget';
import React,{useState} from 'react';
import {Alert,StyleSheet,Text,View} from 'react-native';
import {Ionicons} from '@expo/vector-icons';
import {router} from 'expo-router';
import {Screen} from '../src/components/Screen';
import {useAuth} from '../src/auth/AuthProvider';
import {colors} from '../src/theme/colors';
import {authorizationRoleLabels} from '../src/participants/identity';
import {MemberAvatar} from '../src/components/MemberAvatar';
import {uploadProfilePhoto} from '../src/api/client';
import * as ImagePicker from 'expo-image-picker';
export default function MyAccount(){
 const {user,refresh}=useAuth();
 const [photoBusy,setPhotoBusy]=useState(false);
 const [avatarVersion,setAvatarVersion]=useState(0);
 const choosePhoto=async()=>{
  if(photoBusy||!user)return;
  const granted=await ImagePicker.requestMediaLibraryPermissionsAsync();
  if(!granted.granted){Alert.alert('Photo permission needed','Allow CTSMD Connect to select a profile photo in your phone settings.');return;}
  const picked=await ImagePicker.launchImageLibraryAsync({mediaTypes:['images'],allowsEditing:true,aspect:[1,1],quality:0.85});
  if(picked.canceled||!picked.assets.length)return;
  const asset=picked.assets[0];
  if(asset.fileSize&&asset.fileSize>6*1024*1024){Alert.alert('Photo too large','Select an image smaller than 6 MB.');return;}
  const type=asset.mimeType||((asset.fileName||'').toLowerCase().endsWith('.png')?'image/png':'image/jpeg');
  if(!['image/jpeg','image/png','image/webp'].includes(type)){
   Alert.alert('Unsupported image format','Please choose a JPEG, PNG, or WebP image. If your iPhone photo is HEIC, export it as a JPEG first.');return;
  }
  setPhotoBusy(true);
  try{
   await uploadProfilePhoto({uri:asset.uri,name:asset.fileName||'profile-photo.'+(type==='image/png'?'png':type==='image/webp'?'webp':'jpg'),type});
   setAvatarVersion(n=>n+1);
   await refresh();
   Alert.alert('Profile photo updated','Your new picture will appear in Community and Messages.');
  }catch(error){Alert.alert('Could not save photo',error instanceof Error?error.message:'Please try again.')}
  finally{setPhotoBusy(false);}
 };
 return <Screen title="My Account">
  <Pressable accessibilityRole="button" onPress={()=>router.back()} style={s.back}><Ionicons name="chevron-back" size={20} color={colors.primary}/><Text style={s.backText}>More</Text></Pressable>
  <View style={s.profile}><MemberAvatar key={avatarVersion} version={avatarVersion} hasAvatar={!!user?.has_avatar} userId={user?.id||0} initials={user?.initials||'?'} size={78}/><Text style={s.name}>{user?.name||'Member'}</Text><Text style={s.muted}>CTSMD Connect account</Text><Pressable accessibilityRole="button" disabled={photoBusy} onPress={()=>void choosePhoto()} style={{paddingVertical:9,paddingHorizontal:15,borderRadius:9,backgroundColor:colors.primary,marginTop:10}}><Text style={{color:'#fff',fontWeight:'800'}}>{photoBusy?'Saving…':'Change profile photo'}</Text></Pressable><Text style={s.muted}>Choose and crop a JPEG, PNG, or WebP photo (up to 6 MB). Your photo is visible only to authorized members.</Text></View>
  <Text style={s.heading}>Account information</Text>
  <View style={s.card}><Text style={s.label}>Name</Text><Text style={s.value}>{user?.name||'Not available'}</Text></View>
  <View style={s.card}><Text style={s.label}>Email</Text><Text style={s.value}>{user?.email||'Not available'}</Text></View>
  <View style={s.card}><Text style={s.label}>Authorization roles</Text><Text style={s.value}>{authorizationRoleLabels(user?.roles)}</Text></View>
  <View style={s.card}><Text style={s.label}>Account label</Text><Text style={s.value}>{user?.role||'Member'}</Text></View>
  <Text style={s.hint}>Adult Performer supports production participation. Family relationships and staff permissions are separate. Student conversations include their active guardians.</Text>
  <Text style={s.hint}>These details are provided by your CTSMD account. Other profile details and contact preferences remain managed through the website.</Text>
 </Screen>;
}
const s=StyleSheet.create({back:{flexDirection:'row',alignItems:'center',gap:4},backText:{color:colors.primary,fontWeight:'700'},profile:{backgroundColor:colors.card,borderRadius:14,borderWidth:1,borderColor:colors.border,padding:20,alignItems:'center',gap:8},avatar:{backgroundColor:colors.navy,width:68,height:68,borderRadius:34,alignItems:'center',justifyContent:'center'},initials:{color:'#fff',fontSize:23,fontWeight:'800'},name:{color:colors.navy,fontSize:21,fontWeight:'800'},muted:{fontSize:13,color:colors.muted},heading:{fontSize:18,fontWeight:'800',color:colors.navy,marginTop:8},card:{backgroundColor:colors.card,padding:14,borderWidth:1,borderColor:colors.border,borderRadius:12,gap:5},label:{fontSize:12,color:colors.muted},value:{fontSize:16,color:colors.text,fontWeight:'600'},hint:{color:colors.muted,fontSize:13,lineHeight:20}});
