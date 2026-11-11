import { useState, type FormEvent } from "react";
import { Link, Navigate, useLocation, useNavigate } from "react-router-dom";
import { useAuth } from "./AuthProvider";
import { BrandLogo } from "../../components/shared/BrandLogo";
export function LoginPage() {
  const { user, login } = useAuth();
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);
  const nav = useNavigate();
  const loc = useLocation();
  if (user) return <Navigate to="/" replace />;
  async function submit(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    setBusy(true);
    setError("");
    const f = new FormData(e.currentTarget);
    try {
      await login(String(f.get("email")), String(f.get("password")));
      nav((loc.state as { from?: string } | null)?.from || "/", {
        replace: true,
      });
    } catch (ex) {
      setError(ex instanceof Error ? ex.message : "تعذر تسجيل الدخول");
    } finally {
      setBusy(false);
    }
  }
  return (
    <main className="auth-page">
      <section className="auth-card">
        <div className="auth-art login-welcome">
          <BrandLogo large />
          <p>
            لوحة إدارة تحافظ على خصوصية المستخدم وتضع العمل المهم في الواجهة.
          </p>
        </div>
        <form className="login-form" onSubmit={submit}>
          <h2>تسجيل دخول الإدارة</h2>
          <label>
            البريد الإلكتروني
            <input name="email" type="email" autoComplete="username" required />
          </label>
          <label>
            كلمة المرور
            <input
              name="password"
              type="password"
              autoComplete="current-password"
              required
              minLength={8}
            />
          </label>
          {error && (
            <p className="form-error" role="alert">
              {error}
            </p>
          )}
          <button className="primary full" disabled={busy}>
            {busy ? "جارٍ الدخول…" : "دخول آمن"}
          </button>
          <Link className="login-recovery-link" to="/recovery">
            نسيت كلمة المرور؟
          </Link>
        </form>
      </section>
    </main>
  );
}
