import { manipulateAsync } from 'expo-image-manipulator';
import { resizeForUpload } from '../../src/photos/resize';

describe('resizeForUpload', () => {
  afterEach(() => jest.clearAllMocks());

  it('resizes to a 3840px long edge at quality 1 and returns a file descriptor', async () => {
    const result = await resizeForUpload('file://orig.jpg');

    expect(manipulateAsync).toHaveBeenCalledWith(
      'file://orig.jpg',
      [{ resize: { width: 3840 } }],
      expect.objectContaining({ compress: 1 }),
    );
    expect(result.uri).toContain('#resized');
    expect(result.type).toBe('image/jpeg');
    expect(result.name).toMatch(/\.jpg$/);
  });
});
