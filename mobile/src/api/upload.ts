import { getApiBaseUrl } from '../config';
import { ApiError } from './errors';

export interface UploadOptions {
  token?: string | null;
  /** Called with upload progress as a 0..1 fraction. */
  onProgress?: (fraction: number) => void;
}

/**
 * POST a multipart FormData via XMLHttpRequest.
 *
 * We use XHR (React Native's native networking) rather than the global `fetch`
 * because Expo SDK 56's winter `fetch` cannot serialize RN file parts
 * (`{ uri, name, type }`) and throws "Unsupported FormDataPart implementation".
 * XHR streams files from disk (no full in-memory buffer), supports multiple
 * file parts in one request, and exposes upload progress. Errors are mapped to
 * `ApiError` so callers handle them exactly like `apiFetch` (status 0 = network).
 */
export function uploadMultipart<T = unknown>(
  path: string,
  form: FormData,
  opts: UploadOptions = {},
): Promise<T> {
  const url = `${getApiBaseUrl()}/api${path}`;

  return new Promise<T>((resolve, reject) => {
    const xhr = new XMLHttpRequest();
    xhr.open('POST', url);
    xhr.responseType = 'text';
    xhr.setRequestHeader('Accept', 'application/json');
    if (opts.token) {
      xhr.setRequestHeader('Authorization', `Bearer ${opts.token}`);
    }
    // Do NOT set Content-Type — RN derives the multipart boundary from the FormData.

    if (opts.onProgress && xhr.upload) {
      xhr.upload.onprogress = (e: ProgressEvent) => {
        if (e.lengthComputable && e.total > 0) {
          opts.onProgress!(e.loaded / e.total);
        }
      };
    }

    xhr.onload = () => {
      let parsed: unknown = null;
      try {
        parsed = xhr.responseText ? JSON.parse(xhr.responseText) : null;
      } catch {
        parsed = null;
      }

      if (xhr.status >= 200 && xhr.status < 300) {
        resolve(parsed as T);
      } else {
        const message =
          (parsed as { message?: string } | null)?.message ?? `Request failed (${xhr.status})`;
        reject(new ApiError(xhr.status, message, parsed));
      }
    };

    xhr.onerror = () =>
      reject(new ApiError(0, 'No se pudo conectar con el servidor. Revisa tu conexión.'));
    xhr.ontimeout = () =>
      reject(new ApiError(0, 'La conexión tardó demasiado. Revisa tu red.'));

    xhr.send(form);
  });
}
