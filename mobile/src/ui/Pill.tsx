import { Pressable, Text, StyleSheet } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { colors, spacing, radius } from '../theme';

type Tone = 'good' | 'bad';

const TONE: Record<Tone, { ring: string; bg: string; text: string; icon: keyof typeof Ionicons.glyphMap }> = {
  good: { ring: colors.success, bg: colors.successBg, text: colors.successText, icon: 'checkmark-circle' },
  bad: { ring: colors.danger, bg: colors.dangerBg, text: colors.danger, icon: 'close-circle' },
};

/**
 * Pill — selectable good/bad toggle.
 * Selected: 2px semantic ring + tinted bg + semantic text.
 * Unselected: white bg, neutral border, muted text.
 */
export function Pill({
  label,
  selected,
  onPress,
  tone,
  disabled,
  testID,
}: {
  label: string;
  selected: boolean;
  onPress: () => void;
  tone: Tone;
  disabled?: boolean;
  testID?: string;
}) {
  const t = TONE[tone];
  const fg = selected ? t.text : colors.textMuted;

  return (
    <Pressable
      testID={testID}
      onPress={onPress}
      disabled={disabled}
      style={({ pressed }) => [
        styles.pill,
        selected
          ? { backgroundColor: t.bg, borderColor: t.ring, borderWidth: 2 }
          : { backgroundColor: colors.surface, borderColor: colors.border, borderWidth: 1 },
        disabled && styles.disabled,
        pressed && !disabled && styles.pressed,
      ]}
    >
      <Ionicons name={t.icon} size={18} color={fg} style={styles.icon} />
      <Text style={[styles.label, { color: fg }]}>{label}</Text>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  pill: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    minHeight: 44,
    paddingHorizontal: spacing.lg,
    paddingVertical: spacing.sm,
    borderRadius: radius.full,
  },
  icon: {
    marginRight: spacing.sm,
  },
  label: {
    fontSize: 15,
    fontWeight: '600',
  },
  disabled: {
    opacity: 0.5,
  },
  pressed: {
    opacity: 0.85,
  },
});
