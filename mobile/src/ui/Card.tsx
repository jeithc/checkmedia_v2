import { ReactNode } from 'react';
import { View, Text, StyleSheet, StyleProp, ViewStyle } from 'react-native';
import { colors, spacing, radius, typography, shadow } from '../theme';

type Accent = 'danger' | 'success' | 'warning' | 'primary';

const ACCENT: Record<Accent, { border: string; headerBg: string; title: string }> = {
  danger: { border: colors.danger, headerBg: colors.dangerBg, title: colors.dangerText },
  success: { border: colors.success, headerBg: colors.successBg, title: colors.successText },
  warning: { border: colors.warningBorder, headerBg: colors.warningBg, title: colors.warningText },
  primary: { border: colors.primary, headerBg: colors.primaryBg, title: colors.primaryText },
};

/**
 * Card — white surface, soft shadow, optional OVERLINE header strip.
 * `accent` tints the border + header for alert states.
 */
export function Card({
  title,
  children,
  padded = true,
  accent,
  style,
}: {
  title?: string;
  children: ReactNode;
  padded?: boolean;
  accent?: Accent;
  style?: StyleProp<ViewStyle>;
}) {
  const a = accent ? ACCENT[accent] : null;

  return (
    <View
      style={[
        styles.card,
        a && { borderColor: a.border, borderWidth: 1 },
        style,
      ]}
    >
      {title ? (
        <View
          style={[
            styles.header,
            a ? { backgroundColor: a.headerBg } : null,
          ]}
        >
          <Text style={[styles.title, a ? { color: a.title } : null]}>{title}</Text>
        </View>
      ) : null}
      <View style={padded ? styles.padded : undefined}>{children}</View>
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    backgroundColor: colors.surface,
    borderRadius: 14,
    marginBottom: spacing.lg,
    overflow: 'hidden',
    ...shadow.card,
  },
  header: {
    backgroundColor: colors.surfaceAlt,
    paddingHorizontal: spacing.lg,
    paddingVertical: spacing.md,
    borderBottomWidth: 1,
    borderBottomColor: colors.borderSubtle,
  },
  title: {
    ...typography.overline,
  },
  padded: {
    padding: spacing.lg,
  },
});
