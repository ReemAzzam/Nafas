import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, within } from "@testing-library/react";
import { MemoryRouter } from "react-router-dom";
import { afterEach, describe, expect, it, vi } from "vitest";
import { adminApi } from "../../services/api/adminApi";
import type { Article } from "../../types/api";
import { ArticlesPage } from "./ContentPages";

const articles: Article[] = [
  {
    id: 1,
    title: "التعامل مع الأكل العاطفي",
    slug: "emotional-eating",
    summary: "ملخص المقال",
    content: "محتوى المقال",
    author_name: "فريق نَفَس",
    image_url: "http://127.0.0.1:8000/storage/articles/emotional-eating.webp",
    is_published: true,
    comments_count: 0,
    favorites_count: 0,
    deleted_at: null,
  },
  {
    id: 2,
    title: "التعامل مع القلق",
    slug: "managing-anxiety",
    summary: "ملخص آخر",
    content: "محتوى آخر",
    author_name: "فريق نَفَس",
    image_url: "/storage/articles/managing-anxiety.webp",
    is_published: true,
    comments_count: 0,
    favorites_count: 0,
    deleted_at: null,
  },
];

afterEach(() => {
  vi.restoreAllMocks();
});

describe("article list images", () => {
  it("renders each article image in its row using the article image_url", async () => {
    vi.spyOn(adminApi, "articles").mockResolvedValue({
      success: true,
      data: articles,
      meta: {
        current_page: 1,
        last_page: 1,
        per_page: 20,
        total: articles.length,
        from: 1,
        to: articles.length,
      },
    });
    const queryClient = new QueryClient({
      defaultOptions: { queries: { retry: false } },
    });

    render(
      <QueryClientProvider client={queryClient}>
        <MemoryRouter>
          <ArticlesPage />
        </MemoryRouter>
      </QueryClientProvider>,
    );

    for (const article of articles) {
      const row = await screen.findByRole("row", {
        name: new RegExp(article.title),
      });
      const image = within(row).getByRole("img", {
        name: `صورة ${article.title}`,
      });

      expect(image.tagName).toBe("IMG");
      expect(image).toBeVisible();
      expect(image).toHaveClass("thumb-image");
      expect(image).toHaveAttribute(
        "src",
        article.image_url!.startsWith("http")
          ? article.image_url!
          : `http://127.0.0.1:8000${article.image_url}`,
      );
      expect(image.parentElement).toHaveClass("thumb", "gradient1");
    }
  });
});
