import { Pressable, Text, ActivityIndicator, StyleSheet } from 'react-native';

type Variant = 'primary' | 'success' | 'secondary';

export function Button({
  title,
  onPress,
  loading,
  disabled,
  testID,
  variant = 'primary',
}: {
  title: string;
  onPress: () => void;
  loading?: boolean;
  disabled?: boolean;
  testID?: string;
  variant?: Variant;
}) {
  const isSecondary = variant === 'secondary';
  return (
    <Pressable
      testID={testID}
      onPress={onPress}
      disabled={disabled || loading}
      style={[styles.btn, styles[variant], (disabled || loading) && styles.disabled]}
    >
      {loading ? (
        <ActivityIndicator color={isSecondary ? '#0f172a' : '#fff'} />
      ) : (
        <Text style={[styles.text, isSecondary && styles.textSecondary]}>{title}</Text>
      )}
    </Pressable>
  );
}

const styles = StyleSheet.create({
  btn: { padding: 14, borderRadius: 8, alignItems: 'center' },
  primary: { backgroundColor: '#1d4ed8' },
  success: { backgroundColor: '#16a34a' },
  secondary: { backgroundColor: '#e2e8f0' },
  disabled: { opacity: 0.5 },
  text: { color: '#fff', fontWeight: '600' },
  textSecondary: { color: '#0f172a' },
});
