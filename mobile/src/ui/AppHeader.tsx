import { ReactNode } from 'react';
import { View, Text, Pressable, StyleSheet } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { colors, spacing, typography } from '../theme';

/**
 * AppHeader — branded Efectimedios RED top bar (full bleed).
 * Consumes the top safe-area inset so the red extends under the status bar
 * like a native header. All text/icons are white.
 *
 * - `brand` renders the white "Efectimedios" wordmark (home screens).
 * - `onBack` renders a white back arrow (detail screens).
 * - `title` renders a white screen title.
 * - `right` overrides the right region; otherwise `onSignOut` renders a
 *   white log-out button.
 */
export function AppHeader({
  title,
  onBack,
  onSignOut,
  right,
  brand,
}: {
  title?: string;
  onBack?: () => void;
  onSignOut?: () => void;
  right?: ReactNode;
  brand?: boolean;
}) {
  const insets = useSafeAreaInsets();

  return (
    <View style={[styles.bar, { paddingTop: insets.top + spacing.sm }]}>
      <View style={styles.left}>
        {onBack ? (
          <Pressable
            onPress={onBack}
            hitSlop={8}
            style={({ pressed }) => [styles.action, pressed && styles.pressed]}
            accessibilityRole="button"
            accessibilityLabel="Volver"
          >
            <Ionicons name="arrow-back" size={24} color={colors.white} />
          </Pressable>
        ) : null}

        {brand ? <Text style={styles.wordmark}>Efectimedios</Text> : null}

        {title ? (
          <Text style={styles.title} numberOfLines={1}>
            {title}
          </Text>
        ) : null}
      </View>

      <View style={styles.right}>
        {right ? (
          right
        ) : onSignOut ? (
          <Pressable
            onPress={onSignOut}
            hitSlop={8}
            style={({ pressed }) => [styles.action, pressed && styles.pressed]}
            accessibilityRole="button"
            accessibilityLabel="Cerrar sesión"
          >
            <Ionicons name="log-out-outline" size={24} color={colors.white} />
          </Pressable>
        ) : null}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  bar: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    backgroundColor: colors.brand,
    paddingHorizontal: spacing.lg,
    paddingBottom: spacing.md,
  },
  left: {
    flexDirection: 'row',
    alignItems: 'center',
    flexShrink: 1,
    gap: spacing.sm,
  },
  right: {
    flexShrink: 0,
  },
  wordmark: {
    fontSize: 18,
    fontWeight: '700',
    color: colors.white,
  },
  title: {
    ...typography.h2,
    color: colors.white,
    flexShrink: 1,
  },
  action: {
    minWidth: 44,
    minHeight: 44,
    alignItems: 'center',
    justifyContent: 'center',
  },
  pressed: {
    opacity: 0.6,
  },
});
