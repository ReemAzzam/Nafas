import { fireEvent, render } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { ImageWithFallback } from "./ImageWithFallback";
import { resolveMediaUrl } from "../../services/httpClient";

describe("ImageWithFallback", () => {
  it("shows the provided fallback when the image is missing", () => {
    const { container } = render(
      <ImageWithFallback
        src={null}
        alt="غلاف المقطع"
        className="cover"
        fallback="♫"
      />,
    );

    expect(container.querySelector(".image-fallback")).toHaveTextContent("♫");
    expect(container.querySelector("img")).toBeNull();
  });

  it("replaces a failed image with the provided fallback", () => {
    const { container } = render(
      <ImageWithFallback
        src="/storage/cover.webp"
        alt="غلاف المقطع"
        className="cover"
        fallback="♫"
      />,
    );

    fireEvent.error(container.querySelector("img")!);

    expect(container.querySelector(".image-fallback")).toHaveTextContent("♫");
  });

  it("resolves relative and absolute backend image paths without using audio URLs", () => {
    const { container, rerender } = render(
      <ImageWithFallback
        src="/storage/articles/cover.webp"
        alt="غلاف مقال"
        className="cover"
        fallback="▤"
      />,
    );

    expect(container.querySelector("img")).toHaveAttribute(
      "src",
      "http://127.0.0.1:8000/storage/articles/cover.webp",
    );

    rerender(
      <ImageWithFallback
        src="storage/counselors/profile.webp"
        alt="صورة مختص"
        className="cover"
        fallback="م"
      />,
    );
    expect(container.querySelector("img")).toHaveAttribute(
      "src",
      "http://127.0.0.1:8000/storage/counselors/profile.webp",
    );
    expect(resolveMediaUrl("https://cdn.example.test/image.png")).toBe(
      "https://cdn.example.test/image.png",
    );
    expect(resolveMediaUrl(null)).toBeNull();
  });
});
