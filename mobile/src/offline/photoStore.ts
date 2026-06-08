import * as FileSystem from 'expo-file-system/legacy';

const DIR = `${FileSystem.documentDirectory}audit-photos/`;

async function ensureDir() {
  await FileSystem.makeDirectoryAsync(DIR, { intermediates: true }).catch(() => {});
}

/** Copy a (resized) photo into durable storage. Returns the persistent uri. */
export async function persistPhoto(srcUri: string, clientUuid: string, index: number): Promise<string> {
  await ensureDir();
  const to = `${DIR}${clientUuid}-${index}.jpg`;
  await FileSystem.copyAsync({ from: srcUri, to });
  return to;
}

/** Best-effort delete; ignores already-missing files. */
export async function deletePhotos(uris: string[]): Promise<void> {
  await Promise.all(
    uris.map((uri) => FileSystem.deleteAsync(uri, { idempotent: true }).catch(() => {})),
  );
}
