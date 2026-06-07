import { uploadMultipart } from '../../src/api/upload';
import { ApiError } from '../../src/api/errors';

// Minimal fake XHR we can drive synchronously from the test.
class FakeXHR {
  static instances: FakeXHR[] = [];
  method = '';
  url = '';
  responseType = '';
  status = 0;
  responseText = '';
  headers: Record<string, string> = {};
  upload: { onprogress?: (e: { lengthComputable: boolean; loaded: number; total: number }) => void } = {};
  onload?: () => void;
  onerror?: () => void;
  ontimeout?: () => void;
  sent: unknown = null;

  constructor() {
    FakeXHR.instances.push(this);
  }
  open(method: string, url: string) {
    this.method = method;
    this.url = url;
  }
  setRequestHeader(k: string, v: string) {
    this.headers[k] = v;
  }
  send(body: unknown) {
    this.sent = body;
  }
  // test helpers
  emitProgress(loaded: number, total: number) {
    this.upload.onprogress?.({ lengthComputable: true, loaded, total });
  }
  succeed(status: number, text: string) {
    this.status = status;
    this.responseText = text;
    this.onload?.();
  }
  fail() {
    this.onerror?.();
  }
}

beforeEach(() => {
  FakeXHR.instances = [];
  (global as unknown as { XMLHttpRequest: unknown }).XMLHttpRequest = FakeXHR;
});

it('resolves the parsed body on 2xx and sets auth + accept headers', async () => {
  const form = new FormData();
  const p = uploadMultipart('/audits', form, { token: 'tok' });
  const xhr = FakeXHR.instances[0];

  expect(xhr.method).toBe('POST');
  expect(xhr.url).toContain('/api/audits');
  expect(xhr.headers.Authorization).toBe('Bearer tok');
  expect(xhr.headers.Accept).toBe('application/json');
  expect(xhr.headers['Content-Type']).toBeUndefined(); // RN sets the boundary
  expect(xhr.sent).toBe(form);

  xhr.succeed(201, JSON.stringify({ data: { id: 9 } }));
  await expect(p).resolves.toEqual({ data: { id: 9 } });
});

it('rejects with ApiError carrying status and body on non-2xx', async () => {
  const p = uploadMultipart('/audits', new FormData(), {});
  const xhr = FakeXHR.instances[0];
  xhr.succeed(409, JSON.stringify({ message: 'dup', existing_audit: { id: 7 } }));

  const err = (await p.catch((e) => e)) as ApiError;
  expect(err).toBeInstanceOf(ApiError);
  expect(err.isConflict).toBe(true);
  expect(err.body).toEqual({ message: 'dup', existing_audit: { id: 7 } });
});

it('rejects with a network ApiError (status 0) on transport error', async () => {
  const p = uploadMultipart('/audits', new FormData(), {});
  FakeXHR.instances[0].fail();

  const err = (await p.catch((e) => e)) as ApiError;
  expect(err.status).toBe(0);
  expect(err.isNetwork).toBe(true);
});

it('reports upload progress as a 0..1 fraction', async () => {
  const seen: number[] = [];
  const p = uploadMultipart('/audits', new FormData(), { onProgress: (f) => seen.push(f) });
  const xhr = FakeXHR.instances[0];
  xhr.emitProgress(50, 200);
  xhr.emitProgress(200, 200);
  xhr.succeed(201, JSON.stringify({ data: { id: 1 } }));
  await p;

  expect(seen).toEqual([0.25, 1]);
});
