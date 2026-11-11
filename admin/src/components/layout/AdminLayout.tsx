import { NavLink, Outlet, useLocation, useNavigate } from "react-router-dom";
import { useAuth } from "../../features/auth/AuthProvider";
import { BrandLogo } from "../shared/BrandLogo";

const nav = [
  ["home", "الرئيسية", "/"],
  ["users", "المستخدمون", "/users"],
  ["articles", "المقالات", "/articles"],
  ["exercises", "التمارين", "/exercises"],
  ["spaces", "مساحات نَفَس", "/spaces"],
  ["support", "SOS", "/sos/counselors"],
  ["comments", "التعليقات", "/comments"],
  ["feedback", "التقييمات", "/feedback"],
] as const;

function NavIcon({ name }: { name: (typeof nav)[number][0] }) {
  const common = {
    fill: "none",
    stroke: "currentColor",
    strokeLinecap: "round" as const,
    strokeLinejoin: "round" as const,
    strokeWidth: 1.8,
  };

  return (
    <svg
      aria-hidden="true"
      className="nav-icon-svg"
      viewBox="0 0 24 24"
      {...common}
    >
      {name === "home" && <path d="m3.5 10 8.5-7 8.5 7v10.5h-6v-6h-5v6h-6z" />}
      {name === "users" && (
        <>
          <circle cx="9" cy="8" r="3.5" />
          <path d="M2.5 20v-1.5a6.5 6.5 0 0 1 13 0V20zM16 5a3.5 3.5 0 0 1 0 6.8m2 2a5.5 5.5 0 0 1 3.5 5.2v1" />
        </>
      )}
      {name === "articles" && (
        <>
          <path d="M6 3.5h8l4 4v13H6z" />
          <path d="M14 3.5v4h4M9 12h6M9 16h6" />
        </>
      )}
      {name === "exercises" && (
        <>
          <path d="m4 6 1.5 1.5L8 5M11 6h9M4 12l1.5 1.5L8 11m3 1h9M4 18l1.5 1.5L8 17m3 1h9" />
        </>
      )}
      {name === "spaces" && (
        <path d="M6 19.5a4.5 4.5 0 0 1-.6-9 6.7 6.7 0 0 1 12.9-1.4 5.2 5.2 0 0 1 .2 10.4z" />
      )}
      {name === "support" && (
        <>
          <circle cx="12" cy="12" r="9" />
          <circle cx="12" cy="12" r="3.5" />
          <path d="m5.65 5.65 3.88 3.88m8.82-3.88-3.88 3.88m3.88 8.82-3.88-3.88m-8.82 3.88 3.88-3.88" />
        </>
      )}
      {name === "comments" && (
        <>
          <path d="M20.5 11.5a8.5 8.5 0 0 1-12.8 7.3L3 20l1.2-4.1a8.5 8.5 0 1 1 16.3-4.4Z" />
          <path d="M8 11.5h8M8 15h5" />
        </>
      )}
      {name === "feedback" && (
        <path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-3-5.6 3 1.1-6.2L3 9.6l6.2-.9z" />
      )}
    </svg>
  );
}

export function AdminLayout() {
  const { user, logout } = useAuth();
  const navTo = useNavigate();
  const location = useLocation();
  const title =
    nav.find(([, , path]) =>
      path === "/"
        ? location.pathname === "/"
        : location.pathname.startsWith(path),
    )?.[1] || "لوحة الإدارة";
  return (
    <div className="app-shell">
      <aside className="sidebar">
        <div className="brand">
          <BrandLogo />
        </div>
        <nav>
          {nav.map(([icon, label, path]) => (
            <NavLink
              key={path}
              to={path}
              end={path === "/"}
              className={({ isActive }) =>
                `nav-item ${isActive ? "active" : ""}`
              }
            >
              <span className="nav-ico">
                <NavIcon name={icon} />
              </span>
              {label}
            </NavLink>
          ))}
        </nav>
        <div className="side-bottom">
          <div className="mini-user">
            <span className="avatar sm">{user?.display_name?.[0] || "م"}</span>
            <div>
              <b>{user?.display_name || user?.name}</b>
              <small>{user?.email}</small>
            </div>
          </div>
          <button
            className="logout"
            onClick={() => void logout().then(() => navTo("/login"))}
          >
            تسجيل الخروج
          </button>
        </div>
      </aside>
      <main className="admin-main">
        <header className="topbar">
          <div>
            <h2>{title}</h2>
            <p>إدارة هادئة، واضحة، وآمنة لمحتوى نَفَس</p>
          </div>
          <div className="top-actions">
            <span className="date-pill">
              {new Intl.DateTimeFormat("ar-SY", { dateStyle: "long" }).format(
                new Date(),
              )}
            </span>
            <NavLink
              className="icon-btn"
              aria-label="أمان الحساب"
              to="/security"
            >
              ⚙
            </NavLink>
          </div>
        </header>
        <div className="content">
          <Outlet />
        </div>
      </main>
    </div>
  );
}
