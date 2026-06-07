import { manipulateAsync } from 'expo-image-manipulator';
import { resizeForUpload } from '../../src/photos/resize';

describe('resizeForUpload', () => {
  afterEach(() => jest.clearAllMocks());

  it('resizes to a 2560px long edge at quality 0.85 and returns a file descriptor', async () => {
    const result = await resizeForUpload('file://orig.jpg');

    expect(manipulateAsync).toHaveBeenCalledWith(
      'file://orig.jpg',
      [{ resize: { width: 2560 } }],
      expect.objectContaining({ compress: 0.85 }),
    );
    expect(result.uri).toContain('#resized');
    expect(result.type).toBe('image/jpeg');
    expect(result.name).toMatch(/\.jpg$/);
  });
});
