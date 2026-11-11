import { afterEach, describe, expect, it, vi } from "vitest";
import {
  authToken,
  http,
  httpBlob,
  objectToFormData,
  resolveMediaUrl,
  toSearch,
} from "./httpClient";

afterEach(() => {
  vi.restoreAllMocks();
  sessionStorage.clear();
});
describe("http client", () => {
  it("builds query strings without empty values", () =>
    expect(toSearch({ page: 2, search: "نفس", status: undefined })).toBe(
      "?page=2&search=%D9%86%D9%81%D8%B3",
    ));
  it("serializes multipart values without setting a fake boundary", () => {
    const f = objectToFormData({ title: "test", ids: [1, 2], empty: null });
    expect(f.get("title")).toBe("test");
    expect(f.getAll("ids[0]")).toEqual(["1"]);
    expect(f.has("empty")).toBe(false);
  });
  it("resolves Laravel image paths against the configured API origin", () => {
    expect(resolveMediaUrl("/storage/exercises/cover.webp")).toBe(
      "http://127.0.0.1:8000/storage/exercises/cover.webp",
    );
    expect(resolveMediaUrl("storage/audio/cover.webp")).toBe(
      "http://127.0.0.1:8000/storage/audio/cover.webp",
    );
    expect(resolveMediaUrl("https://cdn.example.test/cover.webp")).toBe(
      "https://cdn.example.test/cover.webp",
    );
  });
  it("rewrites Android emulator image origins while preserving path and query", () => {
    expect(
      resolveMediaUrl(
        "http://10.0.2.2:8000/storage/exercises/cover.webp?size=large",
      ),
    ).toBe("http://127.0.0.1:8000/storage/exercises/cover.webp?size=large");
    expect(
      resolveMediaUrl(
        "http://10.0.2.2:8000/api/v1/audio-tracks/calm/stream?download=1",
      ),
    ).toBe("http://127.0.0.1:8000/api/v1/audio-tracks/calm/stream?download=1");
  });
  it("does not rewrite unrelated absolute URLs", () => {
    expect(
      resolveMediaUrl("http://10.0.2.2:8000/external/cover.png?size=large"),
    ).toBe("http://10.0.2.2:8000/external/cover.png?size=large");
  });
  it("rejects missing and non-HTTP media URLs", () => {
    expect(resolveMediaUrl(null)).toBeNull();
    expect(resolveMediaUrl("javascript:alert(1)")).toBeNull();
  });
  it("sends the documented bearer token", async () => {
    authToken.set("secret");
    const fetchMock = vi.spyOn(globalThis, "fetch").mockResolvedValue(
      new Response(JSON.stringify({ success: true }), {
        status: 200,
        headers: { "Content-Type": "application/json" },
      }),
    );
    await http("/admin/dashboard");
    expect(fetchMock.mock.calls[0][0]).toBe(
      "http://127.0.0.1:8000/api/v1/admin/dashboard",
    );
    expect(
      new Headers(fetchMock.mock.calls[0][1]?.headers).get("Authorization"),
    ).toBe("Bearer secret");
  });
  it("downloads audio from the configured admin preview route with bearer auth", async () => {
    authToken.set("test-token");
    const fetchMock = vi.spyOn(globalThis, "fetch").mockResolvedValue(
      new Response(new Blob(["audio"], { type: "audio/mp4" }), {
        status: 200,
        headers: { "Content-Type": "audio/mp4" },
      }),
    );

    const result = await httpBlob("/admin/audio-tracks/12/preview");

    expect(result).toBeInstanceOf(Blob);
    expect(fetchMock.mock.calls[0][0]).toBe(
      "http://127.0.0.1:8000/api/v1/admin/audio-tracks/12/preview",
    );
    const headers = new Headers(fetchMock.mock.calls[0][1]?.headers);
    expect(headers.get("Accept")).toBe("audio/*");
    expect(headers.get("Authorization")).toBe("Bearer test-token");
  });
  it("preserves an admin preview authorization failure", async () => {
    vi.spyOn(globalThis, "fetch").mockResolvedValue(
      new Response(JSON.stringify({ message: "Forbidden" }), {
        status: 403,
        headers: { "Content-Type": "application/json" },
      }),
    );

    await expect(httpBlob("/admin/audio-tracks/12/preview")).rejects.toEqual(
      expect.objectContaining({
        status: 403,
        message: expect.stringContaining("Forbidden (HTTP 403"),
      }),
    );
  });
  it("describes HTTP status and response content type for preview errors", async () => {
    vi.spyOn(globalThis, "fetch").mockResolvedValue(
      new Response(JSON.stringify({ message: "Unauthenticated." }), {
        status: 401,
        headers: { "Content-Type": "application/json" },
      }),
    );

    await expect(httpBlob("/admin/audio-tracks/12/preview")).rejects.toEqual(
      expect.objectContaining({
        status: 401,
        message: expect.stringContaining(
          "Unauthenticated. (HTTP 401; application/json)",
        ),
        details: expect.objectContaining({
          url: expect.stringContaining("/api/v1/admin/audio-tracks/12/preview"),
          contentType: "application/json",
        }),
      }),
    );
  });
  it("includes plain-text error detail but redacts authorization values", async () => {
    vi.spyOn(globalThis, "fetch").mockResolvedValue(
      new Response("Forbidden token=private-value", {
        status: 403,
        headers: { "Content-Type": "text/plain" },
      }),
    );

    const request = httpBlob("/admin/audio-tracks/12/preview");
    await expect(request).rejects.toMatchObject({
      status: 403,
      message: expect.stringContaining("token [محذوف]"),
    });
    await expect(request).rejects.not.toThrow("private-value");
  });
  it("rejects successful JSON responses instead of treating them as audio", async () => {
    vi.spyOn(globalThis, "fetch").mockResolvedValue(
      new Response(JSON.stringify({ message: "Preview unavailable" }), {
        status: 200,
        headers: { "Content-Type": "application/json" },
      }),
    );

    await expect(httpBlob("/admin/audio-tracks/12/preview")).rejects.toEqual(
      expect.objectContaining({
        status: 200,
        message: expect.stringContaining("استجابة JSON بدلاً من ملف صوتي"),
      }),
    );
  });
  it("explains network and CORS failures without exposing request headers", async () => {
    vi.spyOn(globalThis, "fetch").mockRejectedValue(
      new TypeError("Failed to fetch"),
    );

    await expect(httpBlob("/admin/audio-tracks/12/preview")).rejects.toEqual(
      expect.objectContaining({
        status: 0,
        message: expect.stringContaining("قد يمنع CORS الطلب"),
      }),
    );
  });
  it("exposes Laravel validation errors", async () => {
    vi.spyOn(globalThis, "fetch").mockResolvedValue(
      new Response(
        JSON.stringify({ message: "Invalid", errors: { title: ["مطلوب"] } }),
        { status: 422, headers: { "Content-Type": "application/json" } },
      ),
    );
    await expect(http("/admin/articles")).rejects.toEqual(
      expect.objectContaining({
        status: 422,
        errors: { title: ["مطلوب"] },
      }),
    );
  });
});
