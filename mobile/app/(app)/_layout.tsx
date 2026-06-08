import { Redirect, Stack } from 'expo-router';
import { useAuth } from '../../src/auth/AuthContext';
import { SyncProvider } from '../../src/offline/SyncProvider';
import { colors, typography } from '../../src/theme';

export default function AppLayout() {
  const { status, token } = useAuth();
  if (status !== 'authenticated') {
    return <Redirect href="/login" />;
  }
  return (
    <SyncProvider token={token}>
    <Stack
      screenOptions={{
        headerShown: false,
        headerStyle: { backgroundColor: colors.surface },
        headerTintColor: colors.brand,
        headerTitleStyle: {
          color: colors.text,
          fontSize: typography.h2.fontSize,
          fontWeight: typography.h2.fontWeight,
        },
        headerShadowVisible: false,
        contentStyle: { backgroundColor: colors.appBg },
      }}
    />
    </SyncProvider>
  );
}
