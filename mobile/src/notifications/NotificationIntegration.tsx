import {useEffect} from 'react';
import {router,type Href} from 'expo-router';
import {supportsNativePush} from './runtime';
import type * as Notifications from 'expo-notifications';
import {useAuth} from '../auth/AuthProvider';
import {storedPushIdentity,storeDeviceToken} from './device';
import {notificationDestination} from './destination';

/** Expo Go lacks the native push implementation. Never import it while rendering the app. */
export function NotificationIntegration(){
 const {user}=useAuth();
 useEffect(()=>{
  if(!user||!supportsNativePush())return;
  let active=true;
  let responseListener:{remove:()=>void}|undefined;
  let tokenListener:{remove:()=>void}|undefined;
  void import('expo-notifications').then(Notifications=>{
   if(!active)return;
   const navigate=(response:Notifications.NotificationResponse|null)=>{
    if(!active||!response)return;
    router.push(notificationDestination(response.notification.request.content.data||{}) as Href);
    Notifications.clearLastNotificationResponse();
   };
   responseListener=Notifications.addNotificationResponseReceivedListener(navigate);
   // Expo Go and mismatched native runtimes may reject push listeners.
   // The OS notification response listener can still remain functional.
   try{
    tokenListener=Notifications.addPushTokenListener(token=>{
     void storedPushIdentity().then(identity=>{
      if(active&&identity)return storeDeviceToken(token);
     }).catch(()=>{});
    });
   }catch(error){
    if(__DEV__)console.warn('Native push token listener is unavailable in this build.',error);
   }
   navigate(Notifications.getLastNotificationResponse());
  }).catch(error=>{
   if(active&&__DEV__)console.warn('Native notification integration is unavailable in this runtime.',error);
  });
  return()=>{active=false;responseListener?.remove();tokenListener?.remove()};
 },[user?.id]);
 return null;
}
