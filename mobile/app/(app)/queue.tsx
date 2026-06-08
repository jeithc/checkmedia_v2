import { useCallback, useState } from 'react';
import { View, Text, Pressable, ScrollView, RefreshControl, StyleSheet } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { router } from 'expo-router';
import { Screen } from '../../src/ui/Screen';
import { AppHeader } from '../../src/ui/AppHeader';
import { HeaderMenu } from '../../src/ui/HeaderMenu';
import { Card } from '../../src/ui/Card';
import { Badge } from '../../src/ui/Badge';
import { Button } from '../../src/ui/Button';
import { useSync } from '../../src/offline/SyncProvider';
import * as repo from '../../src/offline/queueRepo';
import { getDb } from '../../src/db';
import type { Submission, SubmissionStatus } from '../../src/offline/types';
import { humanize, fullDate, isoToMs } from '../../src/offline/format';
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
        <AppHeader onBack={() => router.back()} title="Envíos" right={<HeaderMenu />} />
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
        <View style={styles.syncBar}>
          <Button
            title="Sincronizar ahora"
            variant="secondary"
            icon="sync-outline"
            fullWidth
            loading={syncing}
            onPress={() => sync()}
          />
        </View>

        {items.length === 0 ? (
          <View style={styles.empty}>
            <Ionicons name="cloud-done-outline" size={44} color={colors.textMuted} />
            <Text style={styles.emptyHeading}>Sin envíos todavía</Text>
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
  const [showFull, setShowFull] = useState(false);

  const captured = isoToMs(item.capturedAt);
  const synced = item.syncedAt ?? NaN;
  // Show the sync time only when it meaningfully differs from capture time.
  const showSynced =
    item.status === 'synced' &&
    Number.isFinite(synced) &&
    (!Number.isFinite(captured) || Math.abs(synced - captured) > 60_000);

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

      {Number.isFinite(captured) || showSynced ? (
        <Pressable onPress={() => setShowFull((v) => !v)} hitSlop={6} style={styles.dates}>
          {Number.isFinite(captured) ? (
            <View style={styles.dateRow}>
              <Ionicons name="camera-outline" size={14} color={colors.textMuted} />
              <Text style={styles.dateText}>
                Tomada: {showFull ? fullDate(captured) : humanize(captured)}
              </Text>
            </View>
          ) : null}
          {showSynced ? (
            <View style={styles.dateRow}>
              <Ionicons name="cloud-done-outline" size={14} color={colors.textMuted} />
              <Text style={styles.dateText}>
                Sincronizada: {showFull ? fullDate(synced) : humanize(synced)}
              </Text>
            </View>
          ) : null}
        </Pressable>
      ) : null}

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
  syncBar: {
    marginBottom: spacing.lg,
  },
  dates: {
    marginTop: spacing.sm,
    gap: spacing.xs,
  },
  dateRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
  },
  dateText: {
    ...typography.small,
    color: colors.textSecondary,
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
