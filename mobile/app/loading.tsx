import React from 'react';
import {ActivityIndicator,StyleSheet,Text,View} from 'react-native';
import {colors} from '../src/theme/colors';
export default function Loading(){return <View style={styles.root}><ActivityIndicator color={colors.primary} size="large"/><Text style={styles.label}>Opening CTSMD Connect…</Text></View>}
const styles=StyleSheet.create({root:{flex:1,backgroundColor:colors.background,justifyContent:'center',alignItems:'center',gap:14},label:{color:colors.muted,fontSize:14}});
