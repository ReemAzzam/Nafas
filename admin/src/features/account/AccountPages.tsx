import { useMutation } from "@tanstack/react-query";
import { useState, type FormEvent, type ReactNode } from "react";
import { Link, useNavigate } from "react-router-dom";
import { adminApi } from "../../services/api/adminApi";
import { authToken } from "../../services/httpClient";
import { BrandLogo } from "../../components/shared/BrandLogo";

export function RecoveryPage() {
  const [sent, setSent] = useState(false);
  const m = useMutation({
    mutationFn: (email: string) => adminApi.forgot(email),
    onSuccess: () => setSent(true),
  });
  return (
    <AuthShell
      title="استعادة كلمة المرور"
      text="يرسل الخادم رمز الاستعادة إن كان البريد مسجلاً، دون كشف وجود الحساب."
    >
      {sent ? (
        <div className="state-card">
          <b>تحقق من بريدك</b>
          <p>بعد وصول الرمز، انتقل إلى تعيين كلمة مرور جديدة.</p>
          <Link className="primary" to="/verify?mode=reset">
            إدخال الرمز
          </Link>
        </div>
      ) : (
        <form
          onSubmit={(e) => {
            e.preventDefault();
            m.mutate(String(new FormData(e.currentTarget).get("email")));
          }}
        >
          <label>
            البريد الإلكتروني
            <input name="email" type="email" required />
          </label>
          <MutationMessage mutation={m} />
          <button className="primary full">إرسال رمز الاستعادة</button>
        </form>
      )}
    </AuthShell>
  );
}
export function VerifyPage() {
  const nav = useNavigate();
  const m = useMutation({
    mutationFn: (v: {
      email: string;
      otp: string;
      password: string;
      password_confirmation: string;
    }) => (v.password ? adminApi.reset(v) : adminApi.verify(v.email, v.otp)),
    onSuccess: (data) => {
      if ("token" in data && typeof data.token === "string") {
        authToken.set(data.token);
        nav("/");
      } else nav("/login");
    },
  });
  return (
    <AuthShell
      title="التحقق من البريد"
      text="أدخل الرمز المرسل. عند الاستعادة أدخل كلمة المرور الجديدة أيضاً."
    >
      <form
        onSubmit={(e) => {
          e.preventDefault();
          const f = new FormData(e.currentTarget);
          m.mutate({
            email: String(f.get("email")),
            otp: String(f.get("otp")),
            password: String(f.get("password") || ""),
            password_confirmation: String(f.get("password_confirmation") || ""),
          });
        }}
      >
        <label>
          البريد
          <input name="email" type="email" required />
        </label>
        <label>
          رمز OTP
          <input
            name="otp"
            inputMode="numeric"
            minLength={6}
            maxLength={6}
            required
          />
        </label>
        <label>
          كلمة المرور الجديدة (للاستعادة فقط)
          <input name="password" type="password" minLength={8} />
        </label>
        <label>
          تأكيد كلمة المرور
          <input name="password_confirmation" type="password" minLength={8} />
        </label>
        <MutationMessage mutation={m} />
        <button className="primary full">تأكيد</button>
      </form>
    </AuthShell>
  );
}
export function SecurityPage() {
  const m = useMutation({
    mutationFn: (v: Record<string, unknown>) => adminApi.changePassword(v),
  });
  return (
    <>
      <div className="section-head">
        <div>
          <span className="eyebrow">الحساب</span>
          <h1>أمان الحساب الإداري</h1>
          <p>تغيير كلمة المرور يلغي جميع الجلسات الأخرى وفق تنفيذ Laravel.</p>
        </div>
      </div>
      <form
        className="panel form-panel narrow"
        onSubmit={(e: FormEvent<HTMLFormElement>) => {
          e.preventDefault();
          const f = new FormData(e.currentTarget);
          m.mutate({
            current_password: f.get("current_password"),
            password: f.get("password"),
            password_confirmation: f.get("password_confirmation"),
          });
        }}
      >
        <label>
          كلمة المرور الحالية
          <input
            name="current_password"
            type="password"
            autoComplete="current-password"
            required
          />
        </label>
        <label>
          كلمة المرور الجديدة
          <input
            name="password"
            type="password"
            autoComplete="new-password"
            minLength={8}
            required
          />
        </label>
        <label>
          التأكيد
          <input
            name="password_confirmation"
            type="password"
            autoComplete="new-password"
            minLength={8}
            required
          />
        </label>
        <MutationMessage mutation={m} />
        <button className="primary">تغيير كلمة المرور</button>
      </form>
    </>
  );
}
export function StatesPage() {
  return (
    <>
      <div className="section-head">
        <div>
          <span className="eyebrow">النظام</span>
          <h1>حالات الواجهة المشتركة</h1>
          <p>مرجع بصري للحالات التي تطبقها كل شاشة مرتبطة بالـAPI.</p>
        </div>
      </div>
      <div className="states-grid">
        <div className="state-card">
          <span className="spinner" />
          جارٍ التحميل
        </div>
        <div className="state-card">
          <b>لا توجد بيانات بعد</b>
          <p>ستظهر السجلات هنا عند توفرها.</p>
        </div>
        <div className="state-card">
          <b>لا نتائج مطابقة</b>
          <p>جرّب تغيير البحث أو المرشحات.</p>
        </div>
        <div className="state-card error">
          <b>تعذّر الاتصال</b>
          <p>تحقق من الخادم ثم أعد المحاولة.</p>
          <button className="ghost">إعادة المحاولة</button>
        </div>
        <div className="state-card error">
          <b>حقول تحتاج تصحيحاً</b>
          <p>تعرض رسائل Laravel 422 بجانب الحقول.</p>
        </div>
        <div className="state-card">
          <b>الجلسة منتهية</b>
          <p>استجابة 401 تمسح الرمز وتعيد المستخدم للدخول.</p>
        </div>
        <div className="state-card">
          <b>الوصول غير متاح</b>
          <p>403 يعني أن الخادم رفض الصلاحية.</p>
        </div>
        <div className="state-card">
          <b>السجل غير متاح</b>
          <p>404 يظهر دون افتراض أن السجل موجود.</p>
        </div>
      </div>
    </>
  );
}
function AuthShell({
  title,
  text,
  children,
}: {
  title: string;
  text: string;
  children: ReactNode;
}) {
  return (
    <main className="auth-page">
      <section className="auth-card compact">
        <div className="auth-art">
          <BrandLogo large />
          <h1>{title}</h1>
          <p>{text}</p>
          <Link to="/login">العودة للدخول</Link>
        </div>
        <div className="form-panel">{children}</div>
      </section>
    </main>
  );
}
function MutationMessage({
  mutation,
}: {
  mutation: { error: Error | null; data?: { message?: string } };
}) {
  return (
    <>
      {mutation.error && <p className="form-error">{mutation.error.message}</p>}
      {mutation.data?.message && (
        <p className="success-message">{mutation.data.message}</p>
      )}
    </>
  );
}
