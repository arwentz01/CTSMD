import {useEffect} from 'react';
import {router,type Href} from 'expo-router';
import * as Notifications from 'expo-notifications';
import {useAuth} from '../auth/AuthProvider';
import {storedPushIdentity,storeDeviceToken} from './device';
import {notificationDestination} from './destination';
export function NotificationIntegration(){
 const {user}=useAuth();
 useEffect(()=>{
  if(!user)return;
  let active=true;
  const navigate=(response:Notifications.NotificationResponse|null)=>{
   if(!active||!response)return;
   router.push(notificationDestination(response.notification.request.content.data||{}) as Href);
   Notifications.clearLastNotificationResponse();
  };
  const response=Notifications.addNotificationResponseReceivedListener(navigate);
  const rotation=Notifications.addPushTokenListener(token=>{void storedPushIdentity().then(identity=>{if(active&&identity)return storeDeviceToken(token)}).catch(()=>{});});
  navigate(Notifications.getLastNotificationResponse());
  return()=>{active=false;response.remove();rotation.remove()};
 },[user?.id]);
 return null;
}
