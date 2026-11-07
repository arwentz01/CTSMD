import * as SecureStore from 'expo-secure-store';
import {Alert} from 'react-native';
import type * as Notifications from 'expo-notifications';
import {supportsNativePush} from './runtime';
import {Platform} from 'react-native';
import {registerPush,unregisterPush} from '../api/client';
const KEY='ctsmd-mobile-push-identity';
const FIRST_RUN_KEY='ctsmd-native-notifications-first-run-v1';
let firstRunPromptPending=false;
export const storedPushIdentity=()=>SecureStore.getItemAsync(KEY);
export const forgetPushIdentity=()=>SecureStore.deleteItemAsync(KEY);
/**
 * Ask once per installation, after sign-in (so the device can be attached to
 * the correct account). A declined invitation is never shown again automatically.
 * Settings remains available for someone who changes their mind.
 */
export async function offerNotificationsOnFirstSignIn():Promise<void>{
 if(!supportsNativePush()||firstRunPromptPending)return;
 firstRunPromptPending=true;
 try{
  if(await SecureStore.getItemAsync(FIRST_RUN_KEY))return;
  const Notifications=await import('expo-notifications');
  const permission=await Notifications.getPermissionsAsync();
  // An OS-level decision already exists. Don't nag or reprompt.
  if(permission.granted){
   await SecureStore.setItemAsync(FIRST_RUN_KEY,'completed');
   try{await enableDeviceNotifications();}
   catch(error){if(typeof __DEV__!=='undefined'&&__DEV__)console.warn('Automatic notification registration failed.',error);}
   return;
  }
  if(!permission.canAskAgain){
   await SecureStore.setItemAsync(FIRST_RUN_KEY,'completed');
   return;
  }
  await SecureStore.setItemAsync(FIRST_RUN_KEY,'offered');
  Alert.alert(
   'Stay in the loop',
   'Allow CTSMD Connect to notify you about messages, schedule updates, casting, and other theatre activity? You can change this later in Settings.',
   [
    {text:'Not now',style:'cancel'},
    {text:'Enable notifications',onPress:()=>{
     void enableDeviceNotifications().catch(error=>{
      Alert.alert('Notifications not enabled',error instanceof Error?error.message:'Please try again in Settings.');
     });
    }},
   ],
   {cancelable:false}
  );
 }finally{firstRunPromptPending=false;}
}
export async function enableDeviceNotifications(){
 if(!supportsNativePush())throw new Error('Remote push notifications require a CTSMD development build. Expo Go can be used to preview the rest of the app.');
 const Notifications=await import('expo-notifications');
 if(Platform.OS!=='ios'&&Platform.OS!=='android')throw new Error('Use an iOS or Android development build for native notifications.');
 if(Platform.OS==='android'){
  try{
   await Notifications.setNotificationChannelAsync('default',{name:'CTSMD updates',importance:Notifications.AndroidImportance.DEFAULT});
  }catch(error){
   if(typeof __DEV__!=='undefined'&&__DEV__)console.warn('Unable to configure Android notification channel.',error);
   throw new Error('This app build cannot configure Android notifications. Your CTSMD updates are still available in the app. Install a current CTSMD development or production build to enable device alerts.');
  }
 }
 let permission=await Notifications.getPermissionsAsync();
 if(!permission.granted)permission=await Notifications.requestPermissionsAsync();
 if(!permission.granted){
  const previous=await storedPushIdentity();
  if(previous){await unregisterPush(previous);await forgetPushIdentity();}
  return 'Permission denied. Native alerts are off on this device.';
 }
 let token:Notifications.DevicePushToken;
 try{token=await Notifications.getDevicePushTokenAsync();}
 catch(error){
  if(typeof __DEV__!=='undefined'&&__DEV__)console.warn('Native device push token unavailable.',error);
  throw new Error('Notifications are allowed, but this app build cannot register for device alerts yet. Please use an updated CTSMD app build.');
 }
 return storeDeviceToken(token);
}
export async function storeDeviceToken(token:Notifications.DevicePushToken){
 if(typeof token.data!=='string'||!['ios','android'].includes(token.type))throw new Error('No supported native device token was returned.');
 const previous=(await storedPushIdentity())||undefined;
 const result=await registerPush(token.type==='ios'?{apnsToken:token.data}:{fcmToken:token.data},previous);
 await SecureStore.setItemAsync(KEY,token.data,{keychainAccessible:SecureStore.WHEN_UNLOCKED_THIS_DEVICE_ONLY});
 return result.delivery_type==='native_pending'?'Device registered. Native push delivery is not configured or verified.':'Device registration updated.';
}
export async function disableDeviceNotifications(){
 const identity=await storedPushIdentity();
 if(identity)await unregisterPush(identity);
 await forgetPushIdentity();
 return 'This device registration was removed.';
}
