import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState, type ReactNode } from "react";
import { Link, useParams, useSearchParams } from "react-router-dom";
import { AsyncState } from "../../components/ui/AsyncState";
import { ConfirmDialog } from "../../components/ui/ConfirmDialog";
import { Pagination } from "../../components/ui/Pagination";
import { adminApi } from "../../services/api/adminApi";

export function UsersPage() {
  const [sp, setSp] = useSearchParams();
  const [search, setSearch] = useState(sp.get("search") || "");
  const page = Number(sp.get("page") || 1);
  const params = {
    page,
    per_page: 20,
    search: sp.get("search") || undefined,
    status: sp.get("status") || undefined,
    role: sp.get("role") || undefined,
    onboarding_status: sp.get("onboarding_status") || undefined,
  };
  const q = useQuery({
    queryKey: ["admin", "users", params],
    queryFn: () => adminApi.users(params),
  });
  const update = (key: string, value: string) => {
    const n = new URLSearchParams(sp);
    value ? n.set(key, value) : n.delete(key);
    if (key !== "page") n.set("page", "1");
    setSp(n);
  };
  return (
    <>
      <PageHead
        title="المستخدمون"
        text="إدارة حالات الحسابات دون إظهار اليوميات أو جهات الاتصال الخاصة."
      />
      <div className="filters">
        <form
          className="search large"
          onSubmit={(e) => {
            e.preventDefault();
            update("search", search);
          }}
        >
          ⌕
          <input
            aria-label="بحث"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="الاسم، البريد، أو الاسم الظاهر"
          />
          <button>بحث</button>
        </form>
        <select
          value={sp.get("status") || ""}
          onChange={(e) => update("status", e.target.value)}
        >
          <option value="">كل الحالات</option>
          <option value="active">نشط</option>
          <option value="blocked">محظور</option>
          <option value="deleted">محذوف</option>
        </select>
        <select
          value={sp.get("onboarding_status") || ""}
          onChange={(e) => update("onboarding_status", e.target.value)}
        >
          <option value="">كل مراحل الرحلة</option>
          <option value="pending">معلّق</option>
          <option value="goals_set">حدد الأهداف</option>
          <option value="assessed">قيّم حالته</option>
          <option value="plan_ready">الخطة جاهزة</option>
          <option value="skipped">تخطّى</option>
        </select>
      </div>
      <AsyncState
        loading={q.isLoading}
        error={q.error}
        empty={q.data?.data.length === 0}
        onRetry={() => void q.refetch()}
      >
        <section className="panel">
          <div className="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>المستخدم</th>
                  <th>الرحلة</th>
                  <th>الحالة</th>
                  <th>التسجيل</th>
                  <th />
                </tr>
              </thead>
              <tbody>
                {q.data?.data.map((u) => (
                  <tr key={u.id}>
                    <td>
                      <div className="user-cell">
                        <span className="avatar">
                          {(u.display_name || u.name)[0]}
                        </span>
                        <div>
                          <b>{u.display_name || u.name}</b>
                          <small>{u.email}</small>
                        </div>
                      </div>
                    </td>
                    <td>{u.onboarding_status || "—"}</td>
                    <td>
                      <StatusBadge
                        active={!u.is_blocked}
                        deleted={Boolean(u.deleted_at)}
                      />
                    </td>
                    <td>{date(u.created_at)}</td>
                    <td>
                      <Link className="ghost small" to={`/users/${u.id}`}>
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
              onPage={(p) => update("page", String(p))}
            />
          )}
        </section>
      </AsyncState>
    </>
  );
}
export function UserDetailPage() {
  const id = Number(useParams().id);
  const qc = useQueryClient();
  const q = useQuery({
    queryKey: ["admin", "user", id],
    queryFn: () => adminApi.user(id),
  });
  const [confirm, setConfirm] = useState(false);
  const m = useMutation({
    mutationFn: (blocked: boolean) => adminApi.setUserBlocked(id, blocked),
    onSuccess: () => {
      setConfirm(false);
      void qc.invalidateQueries({ queryKey: ["admin", "user", id] });
      void qc.invalidateQueries({ queryKey: ["admin", "users"] });
    },
  });
  const u = q.data?.user;
  return (
    <>
      <Link className="back" to="/users">
        → العودة للمستخدمين
      </Link>
      <AsyncState
        loading={q.isLoading}
        error={q.error}
        onRetry={() => void q.refetch()}
      >
        {u && (
          <>
            <section className="profile-hero panel">
              <div className="user-profile">
                <span className="avatar xl">
                  {(u.display_name || u.name)[0]}
                </span>
                <div>
                  <h1>{u.display_name || u.name}</h1>
                  <p>{u.email}</p>
                  <div className="chips">
                    <span>{u.role}</span>
                    <span>{u.onboarding_status}</span>
                  </div>
                </div>
              </div>
              {!u.deleted_at && (
                <button
                  className={u.is_blocked ? "ghost" : "danger-btn"}
                  onClick={() => setConfirm(true)}
                >
                  {u.is_blocked ? "فك الحظر" : "حظر الحساب"}
                </button>
              )}
            </section>
            <div className="grid-2">
              <section className="panel">
                <h3>بيانات الحساب</h3>
                <Detail label="الاسم القانوني" value={u.name} />
                <Detail label="الهاتف" value={u.phone || u.formatted_phone} />
                <Detail label="العمر" value={u.age} />
                <Detail label="آخر نشاط" value={date(u.last_activity_at)} />
              </section>
              <section className="panel">
                <h3>الرحلة والعدادات</h3>
                {u.counts &&
                  Object.entries(u.counts).map(([k, v]) => (
                    <Detail key={k} label={countLabel(k)} value={v} />
                  ))}
              </section>
            </div>
            <div className="safe-note">
              تحترم هذه الشاشة حدود الخصوصية: لا تعرض اليوميات، محتواها، جهات
              SOS الخاصة، أو سجل من اتصل بمن.
            </div>
            <ConfirmDialog
              open={confirm}
              danger={!u.is_blocked}
              title={u.is_blocked ? "فك حظر المستخدم" : "حظر المستخدم"}
              description={
                u.is_blocked
                  ? "سيتمكن المستخدم من تسجيل الدخول مجدداً."
                  : "سيتم إلغاء جلسات المستخدم الحالية ومنع دخوله."
              }
              onCancel={() => setConfirm(false)}
              onConfirm={() => m.mutate(!u.is_blocked)}
            />
          </>
        )}
      </AsyncState>
    </>
  );
}
function PageHead({
  title,
  text,
  action,
}: {
  title: string;
  text: string;
  action?: ReactNode;
}) {
  return (
    <div className="section-head">
      <div>
        <span className="eyebrow">إدارة الحسابات</span>
        <h1>{title}</h1>
        <p>{text}</p>
      </div>
      {action}
    </div>
  );
}
function StatusBadge({
  active,
  deleted,
}: {
  active: boolean;
  deleted?: boolean;
}) {
  return (
    <span className={`badge ${deleted ? "muted" : active ? "" : "danger"}`}>
      {deleted ? "محذوف" : active ? "نشط" : "محظور"}
    </span>
  );
}
function Detail({ label, value }: { label: string; value: unknown }) {
  return (
    <div className="detail-row">
      <span>{label}</span>
      <b>
        {value === null || value === undefined || value === ""
          ? "—"
          : String(value)}
      </b>
    </div>
  );
}
function date(value?: string | null) {
  return value
    ? new Intl.DateTimeFormat("ar-SY", { dateStyle: "medium" }).format(
        new Date(value),
      )
    : "—";
}
function countLabel(k: string) {
  return (
    (
      {
        mood_logs: "تسجيلات المزاج",
        activity_completions: "أنشطة مكتملة",
        journal_entries: "اليوميات",
        comments: "التعليقات",
        comment_reports: "البلاغات",
      } as Record<string, string>
    )[k] || k
  );
}
