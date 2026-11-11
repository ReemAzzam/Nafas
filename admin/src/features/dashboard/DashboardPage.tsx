import { useQuery } from "@tanstack/react-query";
import { Link } from "react-router-dom";
import { AsyncState } from "../../components/ui/AsyncState";
import { adminApi } from "../../services/api/adminApi";

const Stat = ({
  icon,
  label,
  value,
  tone = "",
}: {
  icon: string;
  label: string;
  value: number | string;
  tone?: string;
}) => (
  <div className={`stat-card ${tone}`}>
    <div className="stat-head">
      <span className="stat-icon">{icon}</span>
    </div>
    <small>{label}</small>
    <b>{value}</b>
  </div>
);
export function DashboardPage() {
  const q = useQuery({
    queryKey: ["admin", "dashboard"],
    queryFn: adminApi.dashboard,
  });
  const d = q.data?.data;
  return (
    <>
      <section className="hero-row">
        <div>
          <span className="eyebrow">مؤشرات اليوم</span>
          <h1>نظرة واضحة على نَفَس</h1>
          <p>
            الأرقام التشغيلية التي تحتاج قراراً، دون كشف بيانات المستخدمين
            الحساسة.
          </p>
        </div>
        <div className="hero-orb">
          <svg
            className="hero-decoration"
            viewBox="0 0 120 120"
            fill="none"
            aria-hidden="true"
          >
            <defs>
              <linearGradient id="cloud-fill" x1="24" y1="27" x2="91" y2="89">
                <stop stopColor="var(--blue)" />
                <stop offset="1" stopColor="var(--purple)" />
              </linearGradient>
              <linearGradient id="leaf-fill" x1="25" y1="25" x2="91" y2="96">
                <stop stopColor="var(--mint)" />
                <stop offset="1" stopColor="var(--blue)" />
              </linearGradient>
            </defs>
            <g
              stroke="var(--violet)"
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth="2.5"
            >
              <path
                d="M25 52c-5.5 0-10-4.2-10-9.5S19.5 33 25 33c1.1-8.1 8.2-14 16.5-14 7.2 0 13.3 4.5 15.5 10.8 1.7-1 3.7-1.6 5.8-1.6 6.1 0 11.1 4.8 11.1 10.8 0 1.1-.2 2.2-.5 3.2 4.6 1.1 8 5.1 8 9.8 0 5.6-4.7 10-10.5 10H25Z"
                fill="url(#cloud-fill)"
                strokeOpacity=".72"
              />
              <path
                d="M56 88c-4.8 0-8.7-3.6-8.7-8s3.9-8 8.7-8c1-6.8 7.1-11.8 14.3-11.8 6.2 0 11.5 3.8 13.4 9.1 1.5-.9 3.2-1.3 5-1.3 5.3 0 9.6 4.1 9.6 9.1 0 1-.2 1.9-.4 2.7 4 .9 6.9 4.3 6.9 8.4 0 4.8-4.1 8.6-9.1 8.6H56Z"
                fill="url(#cloud-fill)"
                strokeOpacity=".58"
              />
              <path
                d="M24 82c5.6-4.4 10.7-9.3 15-14.8m-9.7 10.1c-5.8.5-9.8-2.6-11.4-8.3 5.7-.7 9.7 2.2 11.4 8.3Zm5.1-5.2c.1-5.8 3.5-9.5 9.2-10.6.2 5.8-2.9 9.5-9.2 10.6Zm41.2 31.1c4.9-4.8 8.9-10 12-15.7m-7.6 10.3c-5.8-.4-9.3-4-10.2-9.8 5.7 0 9.3 3.3 10.2 9.8Zm5.5-5.6c.8-5.8 4.6-9 10.4-9.4-.6 5.7-4.1 9-10.4 9.4Z"
                fill="url(#leaf-fill)"
                strokeOpacity=".8"
              />
            </g>
          </svg>
        </div>
      </section>
      <AsyncState
        loading={q.isLoading}
        error={q.error}
        onRetry={() => void q.refetch()}
      >
        {d && (
          <>
            <div className="stats-grid four">
              <Stat icon="♙" label="مستخدمون نشطون" value={d.users.active} />
              <Stat
                icon="◌"
                label="نشاط خلال 7 أيام"
                value={d.engagement.active_users_last_7_days}
                tone="blue"
              />
              <Stat
                icon="♡"
                label="فتح SOS خلال 7 أيام"
                value={d.engagement.sos_opens_last_7_days}
                tone="pink"
              />
              <Stat
                icon="↗"
                label="احتفاظ أول 7 أيام"
                value={`${d.engagement.first_7_days_retention.rate_percent}%`}
                tone="mint"
              />
            </div>
            <div className="grid-2 wide-left">
              <section className="panel">
                <div className="panel-head">
                  <div>
                    <small>آخر سبعة أيام</small>
                    <h3>النشاط اليومي</h3>
                  </div>
                </div>
                <div className="chart" aria-label="رسم النشاط الأسبوعي">
                  {d.weekly_activity.map((x) => {
                    const max = Math.max(
                      1,
                      ...d.weekly_activity.map((i) => i.mood_logs),
                    );
                    return (
                      <div key={x.date} title={`${x.mood_logs} تسجيل مزاج`}>
                        <i
                          style={{
                            height: `${Math.max(8, (x.mood_logs / max) * 100)}%`,
                          }}
                        />
                        <small>
                          {new Date(`${x.date}T00:00:00`).toLocaleDateString(
                            "ar-SY",
                            { weekday: "short" },
                          )}
                        </small>
                      </div>
                    );
                  })}
                </div>
              </section>
              <section className="panel">
                <div className="panel-head">
                  <div>
                    <small>تحتاج تدخلاً</small>
                    <h3>قائمة المراجعة</h3>
                  </div>
                </div>
                <div className="review-list">
                  <Link to="/comments">
                    <span>بلاغات التعليقات</span>
                    <b>{d.review_queue.pending_comment_reports}</b>
                  </Link>
                  <Link to="/feedback">
                    <span>تقييمات معلقة</span>
                    <b>{d.review_queue.pending_feedback}</b>
                  </Link>
                  <Link to="/users?status=blocked">
                    <span>حسابات محظورة</span>
                    <b>{d.users.blocked}</b>
                  </Link>
                </div>
              </section>
            </div>
            <div className="grid-2">
              <section className="panel">
                <h3>حالة الحسابات</h3>
                <div className="mini-metrics">
                  <span>
                    <b>{d.users.new_last_7_days}</b>
                    <small>جديد هذا الأسبوع</small>
                  </span>
                  <span>
                    <b>{d.users.new_last_30_days}</b>
                    <small>جديد هذا الشهر</small>
                  </span>
                  <span>
                    <b>{d.users.deleted}</b>
                    <small>محذوف</small>
                  </span>
                </div>
              </section>
              <section className="panel">
                <h3>المحتوى</h3>
                <div className="mini-metrics">
                  <span>
                    <b>{d.content.published_articles}</b>
                    <small>مقال منشور</small>
                  </span>
                  <span>
                    <b>{d.content.published_exercises}</b>
                    <small>تمرين منشور</small>
                  </span>
                  <span>
                    <b>
                      {d.content.draft_articles + d.content.draft_exercises}
                    </b>
                    <small>مسودة</small>
                  </span>
                </div>
              </section>
            </div>
          </>
        )}
      </AsyncState>
    </>
  );
}
