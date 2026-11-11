import {
  cleanup,
  fireEvent,
  render,
  screen,
  waitFor,
} from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";
import { adminApi } from "../../services/api/adminApi";
import type { AudioTrack } from "../../types/api";
import { AudioPreview } from "./SpacesPages";

const track: AudioTrack = {
  id: 12,
  slug: "draft-space",
  title: "مساحة تجريبية",
  description: "وصف المساحة",
  category: "calming",
  duration_seconds: 10,
  audio_url: "http://10.0.2.2:8000/api/v1/audio-tracks/draft-space/stream",
  preview_url: "http://10.0.2.2:8000/api/v1/admin/audio-tracks/12/preview",
  image_url: null,
  credit: "فريق نفس",
  license_type: "owned",
  mime_type: "audio/mp4",
  file_size_bytes: 10,
  is_published: false,
  sort_order: 1,
  deleted_at: null,
};

afterEach(() => {
  cleanup();
  vi.restoreAllMocks();
});

describe("admin audio preview", () => {
  it("loads a published track through the admin preview route", async () => {
    vi.spyOn(adminApi, "audioPreview").mockResolvedValue(
      new Blob(["published"], { type: "audio/mp4" }),
    );
    vi.stubGlobal(
      "URL",
      Object.assign(URL, {
        createObjectURL: vi.fn(() => "blob:published"),
        revokeObjectURL: vi.fn(),
      }),
    );

    render(<AudioPreview track={{ ...track, is_published: true }} />);
    fireEvent.click(screen.getByRole("button", { name: "معاينة الصوت" }));

    expect(
      await screen.findByLabelText(`معاينة ${track.title}`),
    ).toHaveAttribute("src", "blob:published");
    expect(adminApi.audioPreview).toHaveBeenCalledWith(
      track.id,
      expect.any(AbortSignal),
    );
  });

  it("downloads the protected draft through the API client and exposes native seek and stop controls", async () => {
    const blob = new Blob(["0123456789"], { type: "audio/mp4" });
    vi.spyOn(adminApi, "audioPreview").mockResolvedValue(blob);
    vi.stubGlobal(
      "URL",
      Object.assign(URL, {
        createObjectURL: vi.fn(() => "blob:preview"),
        revokeObjectURL: vi.fn(),
      }),
    );
    const pause = vi
      .spyOn(HTMLMediaElement.prototype, "pause")
      .mockImplementation(() => {});

    const { unmount } = render(<AudioPreview track={track} />);
    expect(adminApi.audioPreview).not.toHaveBeenCalled();
    fireEvent.click(screen.getByRole("button", { name: "معاينة الصوت" }));

    const audio = (await screen.findByLabelText(
      `معاينة ${track.title}`,
    )) as HTMLAudioElement;
    await waitFor(() =>
      expect(adminApi.audioPreview).toHaveBeenCalledWith(
        track.id,
        expect.any(AbortSignal),
      ),
    );
    expect(audio).toHaveAttribute("src", "blob:preview");
    expect(audio).toHaveAttribute("controls");

    Object.defineProperty(audio, "currentTime", {
      configurable: true,
      writable: true,
      value: 7,
    });
    fireEvent.click(screen.getByRole("button", { name: "إيقاف" }));
    expect(pause).toHaveBeenCalled();
    expect(audio.currentTime).toBe(0);

    fireEvent.click(screen.getByRole("button", { name: "إغلاق المعاينة" }));
    expect(URL.revokeObjectURL).toHaveBeenCalledWith("blob:preview");

    unmount();
    expect(URL.revokeObjectURL).toHaveBeenCalledTimes(1);
  });

  it("aborts the in-flight authorized fetch when preview closes", async () => {
    let requestSignal: AbortSignal | undefined;
    vi.spyOn(adminApi, "audioPreview").mockImplementation(
      (_id, signal) =>
        new Promise<Blob>((_resolve, reject) => {
          requestSignal = signal;
          signal?.addEventListener("abort", () =>
            reject(new DOMException("Aborted", "AbortError")),
          );
        }),
    );

    render(<AudioPreview track={track} />);
    fireEvent.click(screen.getByRole("button", { name: "معاينة الصوت" }));
    await screen.findByText("جارٍ تحميل المعاينة…");
    fireEvent.click(screen.getByRole("button", { name: "إلغاء" }));

    await waitFor(() => expect(requestSignal?.aborted).toBe(true));
  });

  it("shows an explicit error when the admin preview endpoint rejects the request", async () => {
    vi.spyOn(adminApi, "audioPreview").mockRejectedValue(
      new Error("تعذر تحميل معاينة المقطع."),
    );

    render(<AudioPreview track={track} />);
    fireEvent.click(screen.getByRole("button", { name: "معاينة الصوت" }));

    expect(await screen.findByRole("alert")).toHaveTextContent(
      "تعذر تحميل معاينة المقطع.",
    );
    expect(
      screen.getByRole("button", { name: "إعادة المحاولة" }),
    ).toBeInTheDocument();
  });
});
