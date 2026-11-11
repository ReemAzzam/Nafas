import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState, type FormEvent } from "react";
import { Link, useParams, useSearchParams } from "react-router-dom";
import { AsyncState } from "../../components/ui/AsyncState";
import { Pagination } from "../../components/ui/Pagination";
import { adminApi } from "../../services/api/adminApi";

export function CommentsPage() {
  const [sp, setSp] = useSearchParams();
  const params = {
    page: Number(sp.get("page") || 1),
    per_page: 20,
    status: sp.get("status") || "pending",
  };
  const q = useQuery({
    queryKey: ["admin", "comments", params],
    queryFn: () => adminApi.comments(params),
  });
  const set = (k: string, v: string) => {
    const n = new URLSearchParams(sp);
    n.set(k, v);
    setSp(n);
  };
  return (
    <>
      <Head
        eyebrow="الإشراف"
        title="مراجعة التعليقات"
        text="قرار بشري واضح: مراجعة البلاغ، إخفاء التعليق أو إظهاره، وتوثيق الملاحظة."
      />
      <div className="filters">
        <select
          value={params.status}
          onChange={(e) => set("status", e.target.value)}
        >
          <option value="pending">معلّق</option>
          <option value="resolved">محلول</option>
          <option value="dismissed">مرفوض</option>
          <option value="all">الكل</option>
        </select>
      </div>
      <AsyncState
        loading={q.isLoading}
        error={q.error}
        empty={q.data?.data.length === 0}
      >
        <section className="panel">
          <div className="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>التعليق</th>
                  <th>الكاتب</th>
                  <th>البلاغات</th>
                  <th>الحالة</th>
                  <th />
                </tr>
              </thead>
              <tbody>
                {q.data?.data.map((c) => (
                  <tr key={c.id}>
                    <td className="truncate">{c.body}</td>
                    <td>{c.author.display_name}</td>
                    <td>
                      {c.pending_reports_count} معلّق / {c.reports_count}
                    </td>
                    <td>
                      <span
                        className={`badge ${c.status === "visible" ? "" : "danger"}`}
                      >
                        {c.status}
                      </span>
                    </td>
                    <td>
                      <Link className="ghost small" to={`/comments/${c.id}`}>
                        مراجعة
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          {q.data && (
            <Pagination
              meta={q.data.meta}
              onPage={(p) => set("page", String(p))}
            />
          )}
        </section>
      </AsyncState>
    </>
  );
}
export function CommentDetailPage() {
  const id = Number(useParams().id);
  const qc = useQueryClient();
  const q = useQuery({
    queryKey: ["admin", "comments", "all-detail"],
    queryFn: () => adminApi.comments({ status: "all", per_page: 100 }),
  });
  const c = q.data?.data.find((x) => x.id === id);
  const [note, setNote] = useState("");
  const state = useMutation({
    mutationFn: (action: "hide" | "restore") =>
      adminApi.commentState(id, action, note),
    onSuccess: () =>
      void qc.invalidateQueries({ queryKey: ["admin", "comments"] }),
  });
  const report = useMutation({
    mutationFn: ({
      rid,
      status,
    }: {
      rid: number;
      status: "resolved" | "dismissed";
    }) => adminApi.resolveReport(rid, status, note),
    onSuccess: () =>
      void qc.invalidateQueries({ queryKey: ["admin", "comments"] }),
  });
  return (
    <>
      <Link className="back" to="/comments">
        → العودة للبلاغات
      </Link>
      <Head
        eyebrow="القرار والملاحظات"
        title="مراجعة تعليق وبلاغاته"
        text="تحقق من السياق قبل أي إجراء؛ لا يوجد تحليل آلي للتعليقات في النظام الحالي."
      />
      <AsyncState
        loading={q.isLoading}
        error={q.error}
        empty={!q.isLoading && !c}
      >
        {c && (
          <div className="grid-2 wide-left">
            <section className="stack">
              <article className="panel">
                <h3>نص التعليق</h3>
                <blockquote>{c.body}</blockquote>
                <p>
                  {c.author.display_name} · {c.article?.title || "مقال محذوف"}
                </p>
              </article>
              <article className="panel">
                <h3>البلاغات المرتبطة</h3>
                {c.reports.map((r) => (
                  <div className="report" key={r.id}>
                    <div>
                      <b>{r.reason}</b>
                      <small>
                        {r.reporter?.display_name || "مستخدم"} · {r.status}
                      </small>
                    </div>
                    {r.status === "pending" && (
                      <div className="row-actions">
                        <button
                          onClick={() =>
                            report.mutate({ rid: r.id, status: "resolved" })
                          }
                          className="ghost"
                        >
                          حلّ
                        </button>
                        <button
                          onClick={() =>
                            report.mutate({ rid: r.id, status: "dismissed" })
                          }
                          className="ghost"
                        >
                          رفض
                        </button>
                      </div>
                    )}
                  </div>
                ))}
              </article>
            </section>
            <aside className="panel form-panel">
              <h3>قرار الإشراف</h3>
              <label>
                ملاحظة داخلية
                <textarea
                  value={note}
                  onChange={(e) => setNote(e.target.value)}
                  maxLength={500}
                />
              </label>
              <div className="stack">
                <button
                  className="danger-btn"
                  onClick={() => state.mutate("hide")}
                >
                  إخفاء التعليق ومعالجة المفتوح
                </button>
                <button
                  className="ghost"
                  onClick={() => state.mutate("restore")}
                >
                  إعادة إظهار التعليق
                </button>
              </div>
            </aside>
          </div>
        )}
      </AsyncState>
    </>
  );
}

export function FeedbackPage() {
  const [sp, setSp] = useSearchParams();
  const params = {
    page: Number(sp.get("page") || 1),
    per_page: 20,
    status: sp.get("status") || undefined,
    rating: sp.get("rating") || undefined,
  };
  const q = useQuery({
    queryKey: ["admin", "feedback", params],
    queryFn: () => adminApi.feedback(params),
  });
  const set = (k: string, v: string) => {
    const n = new URLSearchParams(sp);
    v ? n.set(k, v) : n.delete(k);
    n.set("page", "1");
    setSp(n);
  };
  return (
    <>
      <Head
        eyebrow="صوت المستخدم"
        title="التقييمات والملاحظات"
        text="اعرض الرسائل، صفّها حسب الحالة والتقييم، ثم وثّق المراجعة."
      />
      <div className="filters">
        <select
          value={params.status || ""}
          onChange={(e) => set("status", e.target.value)}
        >
          <option value="">كل الحالات</option>
          <option value="pending">معلّق</option>
          <option value="reviewed">مراجع</option>
        </select>
        <select
          value={params.rating || ""}
          onChange={(e) => set("rating", e.target.value)}
        >
          <option value="">كل التقييمات</option>
          {[5, 4, 3, 2, 1].map((n) => (
            <option key={n} value={n}>
              {n} نجوم
            </option>
          ))}
        </select>
      </div>
      <AsyncState
        loading={q.isLoading}
        error={q.error}
        empty={q.data?.data.length === 0}
      >
        <section className="panel">
          <div className="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>التقييم</th>
                  <th>المستخدم</th>
                  <th>الرسالة</th>
                  <th>الحالة</th>
                  <th />
                </tr>
              </thead>
              <tbody>
                {q.data?.data.map((f) => (
                  <tr key={f.id}>
                    <td className="stars">
                      {"★".repeat(f.rating)}
                      {"☆".repeat(5 - f.rating)}
                    </td>
                    <td>{f.user?.display_name || "حساب غير متاح"}</td>
                    <td className="truncate">{f.message || "—"}</td>
                    <td>
                      <span
                        className={`badge ${f.status === "pending" ? "warn" : ""}`}
                      >
                        {f.status === "pending" ? "معلّق" : "مراجع"}
                      </span>
                    </td>
                    <td>
                      <Link className="ghost small" to={`/feedback/${f.id}`}>
                        التفاصيل
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          {q.data && (
            <Pagination
              meta={q.data.meta}
              onPage={(p) => {
                const n = new URLSearchParams(sp);
                n.set("page", String(p));
                setSp(n);
              }}
            />
          )}
        </section>
      </AsyncState>
    </>
  );
}
export function FeedbackDetailPage() {
  const id = Number(useParams().id);
  const q = useQuery({
    queryKey: ["admin", "feedback", id],
    queryFn: () => adminApi.feedbackItem(id),
  });
  const qc = useQueryClient();
  const m = useMutation({
    mutationFn: (note: string) => adminApi.reviewFeedback(id, note),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ["admin", "feedback", id] });
      void qc.invalidateQueries({ queryKey: ["admin", "feedback"] });
    },
  });
  const f = q.data?.feedback;
  return (
    <>
      <Link className="back" to="/feedback">
        → العودة للتقييمات
      </Link>
      <Head
        eyebrow="تفاصيل ومراجعة"
        title={`تقييم #F-${id}`}
        text="الملاحظة الداخلية لا تظهر للمستخدم."
      />
      <AsyncState loading={q.isLoading} error={q.error}>
        {f && (
          <div className="grid-2">
            <section className="panel">
              <div className="stars big-stars">
                {"★".repeat(f.rating)}
                {"☆".repeat(5 - f.rating)}
              </div>
              <blockquote>{f.message || "لا توجد رسالة."}</blockquote>
              <p>
                {f.user?.display_name || "حساب غير متاح"} ·{" "}
                {new Date(f.created_at).toLocaleDateString("ar-SY")}
              </p>
            </section>
            <form
              className="panel form-panel"
              onSubmit={(e: FormEvent<HTMLFormElement>) => {
                e.preventDefault();
                m.mutate(
                  String(new FormData(e.currentTarget).get("admin_note") || ""),
                );
              }}
            >
              <h3>حالة المراجعة</h3>
              <span className={`badge ${f.status === "pending" ? "warn" : ""}`}>
                {f.status}
              </span>
              <label>
                الملاحظة الداخلية
                <textarea
                  name="admin_note"
                  defaultValue={f.admin_note || ""}
                  maxLength={500}
                />
              </label>
              {m.error && <p className="form-error">{m.error.message}</p>}
              <button className="primary" disabled={m.isPending}>
                اعتماد المراجعة
              </button>
            </form>
          </div>
        )}
      </AsyncState>
    </>
  );
}
function Head({
  eyebrow,
  title,
  text,
}: {
  eyebrow: string;
  title: string;
  text: string;
}) {
  return (
    <div className="section-head">
      <div>
        <span className="eyebrow">{eyebrow}</span>
        <h1>{title}</h1>
        <p>{text}</p>
      </div>
    </div>
  );
}
