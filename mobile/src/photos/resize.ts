import { manipulateAsync, SaveFormat } from 'expo-image-manipulator';

export interface UploadPhoto {
  uri: string;
  name: string;
  type: string;
}

const LONG_EDGE = 2560;
const QUALITY = 0.85;

/**
 * Resize a captured photo so its long edge is at most 2560px and re-encode as
 * JPEG q0.85. expo-image-manipulator's `resize` keeps the aspect ratio when
 * only one dimension is given, so passing `width` scales proportionally.
 */
export async function resizeForUpload(uri: string): Promise<UploadPhoto> {
  const result = await manipulateAsync(uri, [{ resize: { width: LONG_EDGE } }], {
    compress: QUALITY,
    format: SaveFormat.JPEG,
  });
  const name = `photo-${Date.now()}.jpg`;
  return { uri: result.uri, name, type: 'image/jpeg' };
}
