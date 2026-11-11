import { fireEvent, render, within } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";
import { Pagination } from "./Pagination";
import type { PaginationMeta } from "../../types/api";

const meta: PaginationMeta = {
  current_page: 2,
  last_page: 4,
  per_page: 10,
  total: 35,
  from: 11,
  to: 20,
};

describe("Pagination", () => {
  it("does not render when there is only one page", () => {
    const { container } = render(
      <Pagination
        meta={{ ...meta, current_page: 1, last_page: 1 }}
        onPage={vi.fn()}
      />,
    );

    expect(container).toBeEmptyDOMElement();
  });

  it("keeps page navigation and uses RTL previous/next arrows", () => {
    const onPage = vi.fn();
    const { container } = render(<Pagination meta={meta} onPage={onPage} />);
    const view = within(container);

    const previous = view.getByRole("button", { name: "الصفحة السابقة" });
    const next = view.getByRole("button", { name: "الصفحة التالية" });

    expect(previous).toHaveTextContent("→");
    expect(next).toHaveTextContent("←");
    expect(view.getByText("2")).toHaveAttribute("aria-current", "page");
    fireEvent.click(previous);
    fireEvent.click(next);
    expect(onPage).toHaveBeenNthCalledWith(1, 1);
    expect(onPage).toHaveBeenNthCalledWith(2, 3);
  });

  it("disables the previous and next buttons at the corresponding edges", () => {
    const { container, rerender } = render(
      <Pagination meta={{ ...meta, current_page: 1 }} onPage={vi.fn()} />,
    );
    const view = within(container);

    expect(view.getByRole("button", { name: "الصفحة السابقة" })).toBeDisabled();
    expect(view.getByRole("button", { name: "الصفحة التالية" })).toBeEnabled();

    rerender(
      <Pagination
        meta={{ ...meta, current_page: meta.last_page }}
        onPage={vi.fn()}
      />,
    );

    expect(view.getByRole("button", { name: "الصفحة السابقة" })).toBeEnabled();
    expect(view.getByRole("button", { name: "الصفحة التالية" })).toBeDisabled();
  });
});
