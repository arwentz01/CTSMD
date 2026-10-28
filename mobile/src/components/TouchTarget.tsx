import React from 'react';
import {Pressable,StyleSheet} from 'react-native';

/** Shared native hit area; keeps each caller's action, state and press feedback. */
export function TouchTarget({style,accessibilityRole='button',accessibilityState,disabled,...props}:React.ComponentProps<typeof Pressable>){
 return <Pressable {...props} disabled={disabled} accessibilityRole={accessibilityRole} accessibilityState={{...accessibilityState,disabled:disabled??accessibilityState?.disabled}} style={state=>[typeof style==='function'?style(state):style,s.target,disabled&&s.disabled]}/>;
}
const s=StyleSheet.create({target:{minHeight:48,minWidth:48,justifyContent:'center'},disabled:{opacity:0.6}});
