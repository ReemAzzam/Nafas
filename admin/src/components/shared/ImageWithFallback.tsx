import { useState, type ReactNode } from "react";
import { resolveMediaUrl } from "../../services/httpClient";

export function ImageWithFallback({
  src,
  alt,
  className,
  fallback,
}: {
  src: string | null | undefined;
  alt: string;
  className: string;
  fallback: ReactNode;
}) {
  const imageUrl = resolveMediaUrl(src);
  const [failedUrl, setFailedUrl] = useState<string | null>(null);

  if (!imageUrl || failedUrl === imageUrl) {
    return (
      <span
        className={`${className} image-fallback`}
        role="img"
        aria-label={alt}
      >
        {fallback}
      </span>
    );
  }

  return (
    <img
      className={className}
      src={imageUrl}
      alt={alt}
      loading="lazy"
      onError={() => setFailedUrl(imageUrl)}
    />
  );
}
