import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type PropsWithChildren,
} from "react";
import { adminApi } from "../../services/api/adminApi";
import { authToken } from "../../services/httpClient";
import type { User } from "../../types/api";

interface AuthValue {
  user: User | null;
  loading: boolean;
  login: (email: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
}
const AuthContext = createContext<AuthValue | null>(null);
export function AuthProvider({ children }: PropsWithChildren) {
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(Boolean(authToken.get()));
  useEffect(() => {
    if (!authToken.get()) {
      setLoading(false);
      return;
    }
    adminApi
      .me()
      .then(({ user: u }) => {
        if (u.role === "admin") setUser(u);
        else authToken.clear();
      })
      .catch(() => authToken.clear())
      .finally(() => setLoading(false));
  }, []);
  const login = useCallback(async (email: string, password: string) => {
    const data = await adminApi.login(email, password);
    if (data.user.role !== "admin")
      throw new Error("هذا الحساب لا يملك صلاحية الإدارة.");
    authToken.set(data.token);
    setUser(data.user);
  }, []);
  const logout = useCallback(async () => {
    try {
      await adminApi.logout();
    } finally {
      authToken.clear();
      setUser(null);
    }
  }, []);
  const value = useMemo(
    () => ({ user, loading, login, logout }),
    [user, loading, login, logout],
  );
  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}
export function useAuth() {
  const v = useContext(AuthContext);
  if (!v) throw new Error("AuthProvider is missing");
  return v;
}
