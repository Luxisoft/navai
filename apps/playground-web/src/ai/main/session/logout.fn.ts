export function logout_user(context: { navigate: (path: string) => void }) {
  try {
    localStorage.removeItem("auth_token");
    localStorage.removeItem("refresh_token");
  } catch {
    // Ignore storage errors to avoid breaking other functions.
  }

  context.navigate("/");
  return { ok: true, message: "Session closed." };
}
