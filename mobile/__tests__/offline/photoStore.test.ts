import * as FileSystem from 'expo-file-system';
import { persistPhoto, deletePhotos } from '../../src/offline/photoStore';

describe('photoStore', () => {
  beforeEach(() => jest.clearAllMocks());

  it('copies a photo into documentDirectory and returns the new uri', async () => {
    const out = await persistPhoto('file:///cache/tmp123.jpg', 'uuid-1', 0);
    expect(out).toBe('file:///doc/audit-photos/uuid-1-0.jpg');
    expect(FileSystem.makeDirectoryAsync).toHaveBeenCalled();
    expect(FileSystem.copyAsync).toHaveBeenCalledWith({
      from: 'file:///cache/tmp123.jpg',
      to: 'file:///doc/audit-photos/uuid-1-0.jpg',
    });
  });

  it('deletes given uris, ignoring missing files', async () => {
    (FileSystem.deleteAsync as jest.Mock).mockRejectedValueOnce(new Error('missing'));
    await expect(deletePhotos(['file:///doc/a.jpg', 'file:///doc/b.jpg'])).resolves.toBeUndefined();
    expect(FileSystem.deleteAsync).toHaveBeenCalledTimes(2);
  });
});
