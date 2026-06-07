import { ReactNode } from 'react';
import { ScrollView, View, StyleSheet, StyleProp, ViewStyle } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { colors, spacing } from '../theme';


/**
 * Screen — SafeArea + ScrollView wrapper.
 * NAMED export. Applies the app background and horizontal page padding
 * (spacing.xl). Set `scroll={false}` for fixed (non-scrolling) screens.
 *
 * Pass `header` (e.g. an <AppHeader />) to render a full-bleed bar flush
 * to the top edge, OUTSIDE the scroll area (it does not scroll). The header
 * is responsible for the top safe-area inset; when present the content's
 * top padding is the normal spacing.xl. Without a header, the content's top
 * padding includes the top safe-area inset so content clears the status bar.
 */
export function Screen({
  children,
  scroll = true,
  style,
  header,
}: {
  children: ReactNode;
  scroll?: boolean;
  style?: StyleProp<ViewStyle>;
  header?: ReactNode;
}) {
  const insets = useSafeAreaInsets();
  const pad = { paddingTop: header ? spacing.xl : insets.top + spacing.xl };

  const body = scroll ? (
    <ScrollView
      style={styles.fill}
      contentContainerStyle={[styles.content, pad, style]}
      keyboardShouldPersistTaps="handled"
    >
      {children}
    </ScrollView>
  ) : (
    <View style={[styles.fill, pad, styles.content, style]}>{children}</View>
  );

  return (
    <View style={styles.fill}>
      {header}
      {body}
    </View>
  );
}

const styles = StyleSheet.create({
  fill: {
    flex: 1,
    backgroundColor: colors.appBg,
  },
  content: {
    paddingHorizontal: spacing.xl,
    paddingBottom: spacing.xxl,
  },
});
