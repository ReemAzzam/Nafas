import type { PaginationMeta } from "../../types/api";
export function Pagination({
  meta,
  onPage,
}: {
  meta: PaginationMeta;
  onPage: (page: number) => void;
}) {
  if (meta.last_page <= 1) return null;

  return (
    <nav className="pagination" aria-label="ترقيم الصفحات">
      <button
        className="pagination-button"
        aria-label="الصفحة السابقة"
        disabled={meta.current_page <= 1}
        onClick={() => onPage(meta.current_page - 1)}
      >
        →
      </button>
      <span className="pagination-current" aria-current="page">
        {meta.current_page}
      </span>
      <small className="pagination-summary">
        من {meta.last_page} · {meta.total} سجل
      </small>
      <button
        className="pagination-button"
        aria-label="الصفحة التالية"
        disabled={meta.current_page >= meta.last_page}
        onClick={() => onPage(meta.current_page + 1)}
      >
        ←
      </button>
    </nav>
  );
}
