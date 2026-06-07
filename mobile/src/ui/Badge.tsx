import { View, Text, StyleSheet } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { colors, spacing, radius } from '../theme';

type Tone = 'good' | 'bad' | 'primary' | 'structural' | 'neutral' | 'warning';
type IconName = keyof typeof Ionicons.glyphMap;

const TONE: Record<Tone, { bg: string; text: string }> = {
  good: { bg: colors.successBg, text: colors.successText },
  bad: { bg: colors.dangerBg, text: colors.dangerText },
  primary: { bg: colors.primaryBg, text: colors.primaryText },
  structural: { bg: colors.structuralBg, text: colors.structuralText },
  neutral: { bg: colors.borderSubtle, text: colors.textSecondary },
  warning: { bg: colors.warningBg, text: colors.warningText },
};

/**
 * Badge — rounded-full status pill, tinted bg + semantic text, self-start.
 */
export function Badge({
  label,
  tone,
  icon,
}: {
  label: string;
  tone: Tone;
  icon?: IconName;
}) {
  const t = TONE[tone];

  return (
    <View style={[styles.badge, { backgroundColor: t.bg }]}>
      {icon ? (
        <Ionicons name={icon} size={13} color={t.text} style={styles.icon} />
      ) : null}
      <Text style={[styles.label, { color: t.text }]}>{label}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  badge: {
    alignSelf: 'flex-start',
    flexDirection: 'row',
    alignItems: 'center',
    borderRadius: radius.full,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.xs,
  },
  icon: {
    marginRight: spacing.xs,
  },
  label: {
    fontSize: 13,
    fontWeight: '600',
  },
});
