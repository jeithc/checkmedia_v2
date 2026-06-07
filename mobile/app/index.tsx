import { Redirect } from 'expo-router';
import { ActivityIndicator, Image, StyleSheet, View } from 'react-native';
import { useAuth } from '../src/auth/AuthContext';
import { colors, spacing } from '../src/theme';

export default function Index() {
  const { status } = useAuth();
  if (status === 'loading') {
    return (
      <View style={styles.container}>
        <Image
          source={require('../assets/logo.png')}
          style={styles.logo}
          resizeMode="contain"
        />
        <ActivityIndicator size="small" color={colors.white} style={styles.spinner} />
      </View>
    );
  }
  return <Redirect href={status === 'authenticated' ? '/(app)/home' : '/login'} />;
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.brand,
  },
  logo: {
    width: 220,
    height: 52,
  },
  spinner: {
    marginTop: spacing.lg,
  },
});
