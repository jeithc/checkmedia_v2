import { Pressable, Text, ActivityIndicator, StyleSheet, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { colors, radius, spacing, shadow } from '../theme';

type Variant = 'brand' | 'primary' | 'success' | 'danger' | 'dark' | 'secondary';
type IconName = keyof typeof Ionicons.glyphMap;

const FILL: Record<Exclude<Variant, 'secondary'>, string> = {
  brand: colors.brand,
  primary: colors.primary,
  success: colors.success,
  danger: colors.danger,
  dark: colors.dark,
};

// Colored soft shadow per variant (button shadow tint).
const SHADOW_TINT: Record<Variant, string> = {
  brand: colors.brand,
  primary: colors.primary,
  success: colors.success,
  danger: colors.danger,
  dark: colors.dark,
  secondary: '#0f172a',
};

export function Button({
  title,
  onPress,
  loading,
  disabled,
  testID,
  variant = 'primary',
  icon,
  fullWidth,
}: {
  title: string;
  onPress: () => void;
  loading?: boolean;
  disabled?: boolean;
  testID?: string;
  variant?: Variant;
  icon?: IconName;
  fullWidth?: boolean;
}) {
  const isSecondary = variant === 'secondary';
  const fill = isSecondary ? colors.surface : FILL[variant];
  // secondary uses the would-be primary fill color for its label/icon.
  const labelColor = isSecondary ? colors.primary : colors.white;
  const isDisabled = disabled || loading;

  return (
    <Pressable
      testID={testID}
      onPress={onPress}
      disabled={isDisabled}
      style={({ pressed }) => [
        styles.btn,
        { backgroundColor: fill },
        isSecondary && styles.secondaryBorder,
        { shadowColor: SHADOW_TINT[variant], ...shadow.button },
        fullWidth && styles.fullWidth,
        isDisabled && styles.disabled,
        pressed && !isDisabled && styles.pressed,
      ]}
    >
      {loading ? (
        <ActivityIndicator color={labelColor} />
      ) : (
        <View style={styles.row}>
          {icon ? (
            <Ionicons name={icon} size={18} color={labelColor} style={styles.icon} />
          ) : null}
          <Text style={[styles.text, { color: labelColor }]}>{title}</Text>
        </View>
      )}
    </Pressable>
  );
}

const styles = StyleSheet.create({
  btn: {
    paddingVertical: 14,
    paddingHorizontal: spacing.lg,
    borderRadius: radius.md,
    alignItems: 'center',
    justifyContent: 'center',
    minHeight: 48,
  },
  secondaryBorder: {
    borderWidth: 1,
    borderColor: colors.border,
  },
  fullWidth: {
    alignSelf: 'stretch',
  },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
  },
  icon: {
    marginRight: spacing.sm,
  },
  disabled: {
    opacity: 0.5,
  },
  pressed: {
    opacity: 0.85,
    transform: [{ scale: 0.97 }],
  },
  text: {
    fontSize: 15,
    fontWeight: '700',
  },
});
