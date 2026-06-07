import * as ImagePicker from 'expo-image-picker';
import { resizeForUpload, type UploadPhoto } from './resize';

/**
 * Launches the camera, then resizes the captured image for upload.
 * Returns null if the user cancels or denies permission.
 */
export async function capturePhoto(): Promise<UploadPhoto | null> {
  const perm = await ImagePicker.requestCameraPermissionsAsync();
  if (!perm.granted) return null;

  const result = await ImagePicker.launchCameraAsync({ quality: 1 });
  if (result.canceled || !result.assets?.[0]) return null;

  return resizeForUpload(result.assets[0].uri);
}
