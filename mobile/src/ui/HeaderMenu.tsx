import { useState } from 'react';
import { View, Text, Pressable, Modal, Image, StyleSheet } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { router } from 'expo-router';
import { useAuth } from '../auth/AuthContext';
import { useSync } from '../offline/SyncProvider';
import { colors, spacing, radius, typography, shadow } from '../theme';

/**
 * Account menu shown in the right slot of every authenticated header.
 * A profile chip opens a dropdown with the current user, a link to the
 * sync queue, and full sign-out — keeping those actions clearly separated.
 */
export function HeaderMenu() {
  const { user, signOut } = useAuth();
  const { pendingCount } = useSync();
  const [open, setOpen] = useState(false);

  return (
    <>
      <Pressable
        onPress={() => setOpen(true)}
        hitSlop={8}
        style={styles.chip}
        accessibilityRole="button"
        accessibilityLabel="Menú de cuenta"
      >
        {user?.avatar ? (
          <Image source={{ uri: user.avatar }} style={styles.avatar} />
        ) : (
          <Ionicons name="person-circle" size={30} color={colors.white} />
        )}
        {pendingCount > 0 && (
          <View style={styles.dot}>
            <Text style={styles.dotText}>{pendingCount}</Text>
          </View>
        )}
        <Ionicons name="chevron-down" size={16} color={colors.white} />
      </Pressable>

      <Modal visible={open} transparent animationType="fade" onRequestClose={() => setOpen(false)}>
        <Pressable style={styles.backdrop} onPress={() => setOpen(false)}>
          <View style={styles.menu}>
            <Text style={styles.menuLabel}>Conectado como</Text>
            <Text style={styles.menuUser} numberOfLines={1}>
              {user?.name ?? user?.username ?? '—'}
            </Text>

            <View style={styles.sep} />

            <Pressable
              style={styles.item}
              onPress={() => {
                setOpen(false);
                router.push('/(app)/queue');
              }}
            >
              <Ionicons name="cloud-upload-outline" size={20} color={colors.text} />
              <Text style={styles.itemText}>Ver sincronizados</Text>
              {pendingCount > 0 && (
                <View style={styles.badge}>
                  <Text style={styles.badgeText}>{pendingCount}</Text>
                </View>
              )}
            </Pressable>

            <View style={styles.sep} />

            <Pressable
              style={styles.item}
              onPress={() => {
                setOpen(false);
                signOut();
              }}
            >
              <Ionicons name="log-out-outline" size={20} color={colors.danger} />
              <Text style={[styles.itemText, styles.danger]}>Cerrar sesión</Text>
            </Pressable>
          </View>
        </Pressable>
      </Modal>
    </>
  );
}

const styles = StyleSheet.create({
  chip: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 2,
    minHeight: 44,
    paddingLeft: spacing.sm,
  },
  avatar: {
    width: 30,
    height: 30,
    borderRadius: 15,
    borderWidth: 1.5,
    borderColor: colors.white,
    backgroundColor: 'rgba(255,255,255,0.25)',
  },
  dot: {
    position: 'absolute',
    top: 4,
    left: 22,
    minWidth: 18,
    height: 18,
    borderRadius: 9,
    paddingHorizontal: 4,
    backgroundColor: colors.white,
    alignItems: 'center',
    justifyContent: 'center',
  },
  dotText: {
    color: colors.brand,
    fontSize: 11,
    fontWeight: '700',
  },
  backdrop: {
    flex: 1,
    paddingTop: 92,
    paddingRight: spacing.lg,
    alignItems: 'flex-end',
  },
  menu: {
    minWidth: 240,
    backgroundColor: colors.surface,
    borderRadius: radius.md,
    paddingVertical: spacing.sm,
    ...shadow.elevated,
  },
  menuLabel: {
    ...typography.small,
    color: colors.textMuted,
    paddingHorizontal: spacing.lg,
    paddingTop: spacing.sm,
  },
  menuUser: {
    ...typography.body,
    fontWeight: '700',
    paddingHorizontal: spacing.lg,
    paddingBottom: spacing.sm,
  },
  sep: {
    height: 1,
    backgroundColor: colors.borderSubtle,
    marginVertical: spacing.xs,
  },
  item: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
    paddingHorizontal: spacing.lg,
    paddingVertical: spacing.md,
  },
  itemText: {
    ...typography.body,
    flex: 1,
  },
  danger: {
    color: colors.danger,
    fontWeight: '600',
  },
  badge: {
    minWidth: 22,
    height: 22,
    borderRadius: 11,
    paddingHorizontal: 6,
    backgroundColor: colors.warningBg,
    alignItems: 'center',
    justifyContent: 'center',
  },
  badgeText: {
    color: colors.warningText,
    fontSize: 12,
    fontWeight: '700',
  },
});
