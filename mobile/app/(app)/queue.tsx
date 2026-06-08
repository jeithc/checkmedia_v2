import { useCallback, useState } from 'react';
import { View, Text, ScrollView, RefreshControl, StyleSheet } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { router } from 'expo-router';
import { Screen } from '../../src/ui/Screen';
import { AppHeader } from '../../src/ui/AppHeader';
import { Card } from '../../src/ui/Card';
import { Badge } from '../../src/ui/Badge';
import { Button } from '../../src/ui/Button';
import { useSync } from '../../src/offline/SyncProvider';
import * as repo from '../../src/offline/queueRepo';
import { getDb } from '../../src/db';
import type { Submission, SubmissionStatus } from '../../src/offline/types';
import { colors, spacing, typography } from '../../src/theme';

type IconName = keyof typeof Ionicons.glyphMap;
type Tone = 'good' | 'bad' | 'primary' | 'structural' | 'neutral' | 'warning';

const STATUS: Record<SubmissionStatus, { label: string; tone: Tone }> = {
  queued: { label: 'En cola', tone: 'neutral' },
  uploading: { label: 'Enviando', tone: 'primary' },
  synced: { label: 'Enviado ✓', tone: 'good' },
  failed: { label: 'Error', tone: 'warning' },
  conflict: { label: 'Duplicado', tone: 'bad' },
};

const STATUS_ICON: Record<SubmissionStatus, IconName> = {
  queued: 'time-outline',
  uploading: 'cloud-upload-outline',
  synced: 'checkmark-circle-outline',
  failed: 'alert-circle-outline',
  conflict: 'copy-outline',
};

export default function QueueScreen() {
  const { items, syncing, sync, refresh } = useSync();
  const [refreshing, setRefreshing] = useState(false);

  const onRefresh = useCallback(async () => {
    setRefreshing(true);
    try {
      await refresh();
    } finally {
      setRefreshing(false);
    }
  }, [refresh]);

  const retry = useCallback(
    async (id: number) => {
      const db = await getDb();
      await repo.requeue(db, id);
      await sync();
    },
    [sync],
  );

  return (
    <Screen
      header={
        <AppHeader
          onBack={() => router.back()}
          title="Envíos"
          right={
            <Button
              title="Sincronizar ahora"
              variant="secondary"
              icon="sync-outline"
              loading={syncing}
              onPress={() => sync()}
            />
          }
        />
      }
      scroll={false}
    >
      <ScrollView
        style={styles.fill}
        contentContainerStyle={styles.scrollContent}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor={colors.brand} />
        }
      >
        {items.length === 0 ? (
          <View style={styles.empty}>
            <Ionicons name="cloud-done-outline" size={44} color={colors.textMuted} />
            <Text style={styles.emptyHeading}>Sin envíos pendientes</Text>
            <Text style={styles.emptyHelper}>
              Las auditorías guardadas aparecerán aquí hasta enviarse.
            </Text>
          </View>
        ) : (
          items.map((item) => <QueueRow key={item.id} item={item} onRetry={retry} />)
        )}
      </ScrollView>
    </Screen>
  );
}

function QueueRow({ item, onRetry }: { item: Submission; onRetry: (id: number) => void }) {
  const s = STATUS[item.status];
  const isError = item.status === 'failed' || item.status === 'conflict';

  return (
    <Card>
      <View style={styles.row}>
        <Text style={styles.code}>{item.externalCode}</Text>
        <Badge label={s.label} tone={s.tone} icon={STATUS_ICON[item.status]} />
      </View>

      <Text style={styles.meta}>
        {item.photos.length} {item.photos.length === 1 ? 'foto' : 'fotos'}
        {item.attempts > 0 ? `  ·  ${item.attempts} intentos` : ''}
      </Text>

      {isError && item.lastError ? <Text style={styles.error}>{item.lastError}</Text> : null}

      {isError ? (
        <View style={styles.actions}>
          <Button
            title="Reintentar"
            variant="primary"
            icon="refresh-outline"
            onPress={() => onRetry(item.id)}
          />
          {item.status === 'conflict' && item.serverAuditId != null ? (
            <Button
              title="Ver auditoría"
              variant="secondary"
              icon="open-outline"
              onPress={() => router.push(`/(app)/audit/${item.serverAuditId}`)}
            />
          ) : null}
        </View>
      ) : null}
    </Card>
  );
}

const styles = StyleSheet.create({
  fill: {
    flex: 1,
  },
  scrollContent: {
    paddingTop: spacing.lg,
    paddingBottom: spacing.xxl,
  },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: spacing.sm,
  },
  code: {
    ...typography.h2,
    flexShrink: 1,
  },
  meta: {
    ...typography.small,
    marginTop: spacing.xs,
  },
  error: {
    ...typography.small,
    color: colors.danger,
    marginTop: spacing.sm,
  },
  actions: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: spacing.sm,
    marginTop: spacing.md,
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
