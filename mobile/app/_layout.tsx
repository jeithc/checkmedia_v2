import { Stack } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { AuthProvider } from '../src/auth/AuthContext';
import { colors, typography } from '../src/theme';

const queryClient = new QueryClient();

export default function RootLayout() {
  return (
    <SafeAreaProvider>
      <QueryClientProvider client={queryClient}>
        <AuthProvider>
          <StatusBar style="light" />
          <Stack
            screenOptions={{
              headerShown: false,
              headerStyle: { backgroundColor: colors.surface },
              headerTintColor: colors.brand,
              headerTitleStyle: {
                color: colors.text,
                fontWeight: typography.h2.fontWeight,
              },
              headerShadowVisible: false,
              contentStyle: { backgroundColor: colors.appBg },
            }}
          />
        </AuthProvider>
      </QueryClientProvider>
    </SafeAreaProvider>
  );
}
