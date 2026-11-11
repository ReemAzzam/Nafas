import type { ValidationErrorBody } from "../types/api";

const baseUrl = (
  import.meta.env.VITE_API_BASE_URL || "http://127.0.0.1:8000"
).replace(/\/$/, "");
const TOKEN_KEY = "nafas_admin_token";
const backendMediaPath = /^\/(?:storage|api\/v1)\//;

export function resolveMediaUrl(
  value: string | null | undefined,
): string | null {
  if (!value?.trim()) return null;
  try {
    const url = new URL(value, `${baseUrl}/`);
    if (
      url.origin === "http://10.0.2.2:8000" &&
      backendMediaPath.test(url.pathname)
    ) {
      const apiOrigin = new URL(baseUrl);
      url.protocol = apiOrigin.protocol;
      url.host = apiOrigin.host;
    }
    return url.protocol === "http:" || url.protocol === "https:"
      ? url.href
      : null;
  } catch {
    return null;
  }
}

export class ApiError extends Error {
  constructor(
    public status: number,
    message: string,
    public errors: Record<string, string[]> = {},
    public details?: { url?: string; contentType?: string },
  ) {
    super(message);
  }
}
export const authToken = {
  get: () => sessionStorage.getItem(TOKEN_KEY),
  set: (token: string) => sessionStorage.setItem(TOKEN_KEY, token),
  clear: () => sessionStorage.removeItem(TOKEN_KEY),
};

export async function http<T>(
  path: string,
  init: RequestInit = {},
): Promise<T> {
  const headers = new Headers(init.headers);
  headers.set("Accept", "application/json");
  const token = authToken.get();
  if (token) headers.set("Authorization", `Bearer ${token}`);
  if (init.body && !(init.body instanceof FormData))
    headers.set("Content-Type", "application/json");
  const response = await fetch(`${baseUrl}/api/v1${path}`, {
    ...init,
    headers,
    signal: init.signal,
  });
  if (response.status === 401) authToken.clear();
  if (!response.ok) {
    let body: ValidationErrorBody = {};
    try {
      body = (await response.json()) as ValidationErrorBody;
    } catch {
      /* non-JSON error */
    }
    throw new ApiError(
      response.status,
      body.message || `تعذر إكمال الطلب (${response.status})`,
      body.errors,
    );
  }
  if (response.status === 204) return undefined as T;
  return response.json() as Promise<T>;
}
export async function httpBlob(
  path: string,
  signal?: AbortSignal,
): Promise<Blob> {
  const url = `${baseUrl}/api/v1${path}`;
  const headers = new Headers({ Accept: "audio/*" });
  const token = authToken.get();
  if (token) headers.set("Authorization", `Bearer ${token}`);
  let response: Response;
  try {
    response = await fetch(url, { headers, signal });
  } catch (cause) {
    if (signal?.aborted) throw cause;
    if (cause instanceof TypeError) {
      const mixedContent =
        typeof window !== "undefined" &&
        window.location.protocol === "https:" &&
        new URL(url).protocol === "http:";
      throw new ApiError(
        0,
        mixedContent
          ? `تعذر تحميل الصوت من ${url}: اتصال HTTP غير آمن من لوحة HTTPS (Mixed Content). اضبط VITE_API_BASE_URL على عنوان HTTPS.`
          : `تعذر الاتصال بعنوان معاينة الصوت ${url}. قد يمنع CORS الطلب أو يكون الخادم غير متاح؛ راجع Network وConsole للتفريق بينهما.`,
        {},
        { url },
      );
    }
    throw cause;
  }
  if (response.status === 401) authToken.clear();
  const contentType = response.headers
    .get("Content-Type")
    ?.split(";")[0]
    .trim();
  if (!response.ok) {
    let responseText = "";
    try {
      responseText = await response.text();
    } catch {
      // The status and content type still identify the failed response.
    }
    const body = parseErrorBody(responseText);
    const responseDetail =
      body.message ||
      (contentType === "text/plain"
        ? sanitizeResponseText(responseText)
        : undefined);
    throw new ApiError(
      response.status,
      `${audioStatusMessage(response.status, responseDetail)} (HTTP ${response.status}; ${contentType || "نوع محتوى غير معروف"}) — ${url}`,
      body.errors,
      { url, contentType },
    );
  }

  const audioBytes = await response.arrayBuffer();
  if (
    isJsonContentType(contentType) ||
    (!contentType?.startsWith("audio/") && looksLikeJson(audioBytes))
  ) {
    const responseText = new TextDecoder().decode(audioBytes);
    const message = parseErrorBody(responseText).message;
    throw new ApiError(
      response.status,
      `أعاد مسار المعاينة استجابة JSON بدلاً من ملف صوتي (${contentType || "نوع محتوى غير معروف"})${message ? `: ${message}` : ""} — ${url}`,
      {},
      { url, contentType },
    );
  }
  if (audioBytes.byteLength === 0) {
    throw new ApiError(
      response.status,
      `أعاد مسار المعاينة ملفاً فارغاً بدلاً من الصوت (${contentType || "نوع محتوى غير معروف"}) — ${url}`,
      {},
      { url, contentType },
    );
  }
  if (
    contentType &&
    !contentType.startsWith("audio/") &&
    contentType !== "application/octet-stream" &&
    contentType !== "application/mp4"
  ) {
    throw new ApiError(
      response.status,
      `نوع استجابة معاينة الصوت غير متوقع: ${contentType} — ${url}`,
      {},
      { url, contentType },
    );
  }
  return new Blob([audioBytes], { type: contentType });
}

function parseErrorBody(text: string): ValidationErrorBody {
  try {
    const parsed = JSON.parse(text) as ValidationErrorBody;
    return {
      message:
        typeof parsed.message === "string"
          ? sanitizeResponseText(parsed.message)
          : undefined,
      errors: parsed.errors,
    };
  } catch {
    return {};
  }
}

function sanitizeResponseText(text: string): string | undefined {
  const safeText = text
    .replace(/\bBearer\s+\S+/gi, "Bearer [محذوف]")
    .replace(/\b(authorization|token)\s*[:=]\s*\S+/gi, "$1 [محذوف]")
    .trim()
    .slice(0, 300);
  return safeText || undefined;
}

function isJsonContentType(contentType?: string): boolean {
  return Boolean(
    contentType &&
    (contentType === "application/json" || contentType.endsWith("+json")),
  );
}

function looksLikeJson(bytes: ArrayBuffer): boolean {
  const prefix = new TextDecoder().decode(bytes.slice(0, 160)).trimStart();
  return prefix.startsWith("{") || prefix.startsWith("[");
}

function audioStatusMessage(status: number, serverMessage?: string): string {
  if (serverMessage) return serverMessage;
  if (status === 401)
    return "انتهت جلسة الأدمن أو لم تُرسل بيانات اعتماد صالحة";
  if (status === 403) return "ليس لديك صلاحية لمعاينة هذا المقطع";
  if (status === 404) return "مسار المعاينة أو المقطع غير موجود";
  if (status === 429) return "تجاوزت عدد طلبات المعاينة المسموح";
  if (status >= 500) return "حدث خطأ في خادم معاينة الصوت";
  return "فشل طلب معاينة الصوت";
}
export function toSearch(params: object): string {
  const q = new URLSearchParams();
  Object.entries(params).forEach(([k, v]: [string, unknown]) => {
    if ((typeof v === "string" || typeof v === "number") && v !== "")
      q.set(k, String(v));
  });
  return q.size ? `?${q}` : "";
}
export function objectToFormData(values: Record<string, unknown>): FormData {
  const form = new FormData();
  Object.entries(values).forEach(([key, value]) => {
    if (value === undefined || value === null || value === "") return;
    if (Array.isArray(value))
      value.forEach((item, index) =>
        form.append(
          `${key}[${index}]`,
          typeof item === "object" ? JSON.stringify(item) : String(item),
        ),
      );
    else if (value instanceof Blob) form.append(key, value);
    else if (typeof value === "object") form.append(key, JSON.stringify(value));
    else form.append(key, String(value));
  });
  return form;
}
