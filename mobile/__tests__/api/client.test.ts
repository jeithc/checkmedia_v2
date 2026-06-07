import { apiFetch } from '../../src/api/client';
import { ApiError } from '../../src/api/errors';

const okJson = (body: unknown, status = 200) =>
  Promise.resolve({
    ok: status >= 200 && status < 300,
    status,
    json: () => Promise.resolve(body),
    text: () => Promise.resolve(JSON.stringify(body)),
  } as Response);

describe('apiFetch', () => {
  afterEach(() => jest.restoreAllMocks());

  it('adds the bearer token and parses JSON', async () => {
    const fetchMock = jest.spyOn(global, 'fetch').mockReturnValue(okJson({ ok: true }));
    const res = await apiFetch('/ping', { token: 'abc' });

    expect(res).toEqual({ ok: true });
    const [, init] = fetchMock.mock.calls[0];
    expect((init?.headers as Record<string, string>).Authorization).toBe('Bearer abc');
    expect((init?.headers as Record<string, string>).Accept).toBe('application/json');
  });

  it('throws ApiError with status and body on non-2xx', async () => {
    jest.spyOn(global, 'fetch').mockReturnValue(okJson({ message: 'nope' }, 422));
    await expect(apiFetch('/x', {})).rejects.toMatchObject({
      name: 'ApiError',
      status: 422,
    });
  });

  it('exposes the parsed body on the error', async () => {
    jest.spyOn(global, 'fetch').mockReturnValue(okJson({ message: 'dup', existing_audit: { id: 7 } }, 409));
    const err = (await apiFetch('/x', {}).catch((e) => e)) as ApiError;
    expect(err.isConflict).toBe(true);
    expect(err.body).toEqual({ message: 'dup', existing_audit: { id: 7 } });
  });

  it('builds a query string and defaults to GET', async () => {
    const fetchMock = jest.spyOn(global, 'fetch').mockReturnValue(okJson({ data: [] }));
    await apiFetch('/criteria', { token: 't', query: { type: 'general', skip: undefined } });
    const [url, init] = fetchMock.mock.calls[0];
    expect(String(url)).toContain('/api/criteria?type=general');
    expect(String(url)).not.toContain('skip');
    expect(init?.method).toBe('GET');
  });
});
