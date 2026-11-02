import * as SecureStore from 'expo-secure-store';
import * as Notifications from 'expo-notifications';
import {Platform} from 'react-native';
import {registerPush,unregisterPush} from '../api/client';
const KEY='ctsmd-mobile-push-identity';
export const storedPushIdentity=()=>SecureStore.getItemAsync(KEY);
export const forgetPushIdentity=()=>SecureStore.deleteItemAsync(KEY);
export async function enableDeviceNotifications(){
 if(Platform.OS!=='ios'&&Platform.OS!=='android')throw new Error('Use an iOS or Android development build for native notifications.');
 if(Platform.OS==='android')await Notifications.setNotificationChannelAsync('default',{name:'CTSMD updates',importance:Notifications.AndroidImportance.DEFAULT});
 let permission=await Notifications.getPermissionsAsync();
 if(!permission.granted)permission=await Notifications.requestPermissionsAsync();
 if(!permission.granted){
  const previous=await storedPushIdentity();
  if(previous){await unregisterPush(previous);await forgetPushIdentity();}
  return 'Permission denied. Native alerts are off on this device.';
 }
 const token=await Notifications.getDevicePushTokenAsync();
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
