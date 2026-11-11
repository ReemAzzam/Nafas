import { render, screen, waitFor } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";
import { AuthProvider, useAuth } from "./AuthProvider";
import { authToken } from "../../services/httpClient";
import { adminApi } from "../../services/api/adminApi";
function Probe() {
  const { user, loading } = useAuth();
  return <span>{loading ? "loading" : user?.role || "anonymous"}</span>;
}
describe("authentication bootstrap", () => {
  it("does not call me without a token", async () => {
    sessionStorage.clear();
    const spy = vi.spyOn(adminApi, "me");
    render(
      <AuthProvider>
        <Probe />
      </AuthProvider>,
    );
    await waitFor(() =>
      expect(screen.getByText("anonymous")).toBeInTheDocument(),
    );
    expect(spy).not.toHaveBeenCalled();
  });
  it("rejects a non-admin session", async () => {
    authToken.set("x");
    vi.spyOn(adminApi, "me").mockResolvedValue({
      success: true,
      user: {
        id: 1,
        name: "u",
        display_name: null,
        email: "u@e.test",
        role: "user",
      },
    });
    render(
      <AuthProvider>
        <Probe />
      </AuthProvider>,
    );
    await waitFor(() =>
      expect(screen.getByText("anonymous")).toBeInTheDocument(),
    );
    expect(authToken.get()).toBeNull();
  });
});
