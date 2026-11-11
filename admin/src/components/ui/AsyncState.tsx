import type { ReactNode } from "react";
export function AsyncState({
  loading,
  error,
  empty,
  onRetry,
  children,
}: {
  loading: boolean;
  error: Error | null;
  empty?: boolean;
  onRetry?: () => void;
  children: ReactNode;
}) {
  if (loading)
    return (
      <div className="state-card" role="status">
        <span className="spinner" />
        جارٍ تحميل البيانات…
      </div>
    );
  if (error)
    return (
      <div className="state-card error" role="alert">
        <b>تعذّر الاتصال</b>
        <p>{error.message}</p>
        {onRetry && (
          <button className="ghost" onClick={onRetry}>
            إعادة المحاولة
          </button>
        )}
      </div>
    );
  if (empty)
    return (
      <div className="state-card">
        <b>لا توجد بيانات بعد</b>
        <p>ستظهر السجلات هنا عند توفرها.</p>
      </div>
    );
  return <>{children}</>;
}
