import { useState } from 'react';
import { View, Text, Pressable, StyleSheet } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { router } from 'expo-router';
import { useAuth } from '../../src/auth/AuthContext';
import * as spacesApi from '../../src/api/spaces';
import { resolveAuditOptions } from '../../src/audit/auditType';
import { useSync } from '../../src/offline/SyncProvider';
import { Screen } from '../../src/ui/Screen';
import { AppHeader } from '../../src/ui/AppHeader';
import { Card } from '../../src/ui/Card';
import { Field } from '../../src/ui/Field';
import { Button } from '../../src/ui/Button';
import { Badge } from '../../src/ui/Badge';
import { colors, spacing, radius, shadow, typography } from '../../src/theme';

export default function HomeScreen() {
  const { token, permissions, lock } = useAuth();
  const { pendingCount } = useSync();
  const [code, setCode] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const auditType = permissions ? resolveAuditOptions(permissions).defaultType : 'general';

  const search = async () => {
    setError(null);
    setBusy(true);
    try {
      await spacesApi.searchSpace(code.trim(), auditType, token ?? '');
      router.push(`/(app)/space/${encodeURIComponent(code.trim())}`);
    } catch (e) {
      const msg = e instanceof Error ? e.message : 'Error al buscar.';
      setError(msg.includes('no encontrado') ? 'Espacio no encontrado.' : msg);
    } finally {
      setBusy(false);
    }
  };

  return (
    <Screen
      header={
        <AppHeader
          brand
          right={
            <View style={styles.headerActions}>
              <Pressable
                onPress={() => router.push('/(app)/queue')}
                hitSlop={8}
                style={styles.headerBtn}
                accessibilityRole="button"
                accessibilityLabel="Ver envíos"
              >
                <Ionicons name="cloud-upload-outline" size={24} color={colors.white} />
                {pendingCount > 0 && (
                  <View style={styles.countBubble}>
                    <Text style={styles.countText}>{pendingCount}</Text>
                  </View>
                )}
              </Pressable>
              <Pressable
                onPress={lock}
                hitSlop={8}
                style={styles.headerBtn}
                accessibilityRole="button"
                accessibilityLabel="Bloquear"
              >
                <Ionicons name="lock-closed-outline" size={24} color={colors.white} />
              </Pressable>
            </View>
          }
        />
      }
    >
      <Pressable
        onPress={() => router.push('/(app)/queue')}
        style={styles.enviosLink}
        accessibilityRole="button"
      >
        <View style={styles.enviosLeft}>
          <Ionicons name="cloud-upload-outline" size={20} color={colors.primary} />
          <Text style={styles.enviosText}>Últimos envíos</Text>
        </View>
        <View style={styles.enviosRight}>
          {pendingCount > 0 && <Badge label={`${pendingCount} pendientes`} tone="warning" />}
          <Ionicons name="chevron-forward" size={18} color={colors.textMuted} />
        </View>
      </Pressable>

      <Text style={styles.title}>Buscar espacio</Text>

      <Card title="Buscar espacio">
        <Field
          label="Código del espacio"
          icon="search"
          testID="code"
          value={code}
          onChangeText={setCode}
          keyboardType="number-pad"
          inputMode="numeric"
          returnKeyType="search"
          onSubmitEditing={search}
        />
        {error && <Text style={styles.error}>{error}</Text>}
        <Button
          title="Buscar"
          variant="dark"
          icon="search"
          fullWidth
          onPress={search}
          loading={busy}
          disabled={code.trim() === ''}
        />
      </Card>

      <View style={styles.empty}>
        <Ionicons name="search" size={44} color={colors.textMuted} />
        <Text style={styles.emptyHeading}>Busque un espacio</Text>
        <Text style={styles.emptyHelper}>
          Ingrese el código del elemento publicitario para comenzar.
        </Text>
      </View>
    </Screen>
  );
}

const styles = StyleSheet.create({
  headerActions: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
  },
  headerBtn: {
    minWidth: 44,
    minHeight: 44,
    alignItems: 'center',
    justifyContent: 'center',
  },
  countBubble: {
    position: 'absolute',
    top: 4,
    right: 2,
    minWidth: 18,
    height: 18,
    borderRadius: 9,
    paddingHorizontal: 4,
    backgroundColor: colors.white,
    alignItems: 'center',
    justifyContent: 'center',
  },
  countText: {
    color: colors.brand,
    fontSize: 11,
    fontWeight: '700',
  },
  enviosLink: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    backgroundColor: colors.surface,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.borderSubtle,
    paddingHorizontal: spacing.lg,
    paddingVertical: spacing.md,
    marginBottom: spacing.lg,
    ...shadow.card,
  },
  enviosLeft: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
  },
  enviosText: {
    ...typography.body,
    fontWeight: '600',
  },
  enviosRight: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
  },
  title: {
    ...typography.title,
    marginBottom: spacing.lg,
  },
  error: {
    ...typography.small,
    color: colors.danger,
    marginBottom: spacing.md,
  },
  empty: {
    alignItems: 'center',
    paddingHorizontal: spacing.xl,
    paddingTop: spacing.xxl,
    gap: spacing.sm,
  },
  emptyHeading: {
    ...typography.h2,
    marginTop: spacing.sm,
  },
  emptyHelper: {
    ...typography.bodySecondary,
    textAlign: 'center',
  },
});
