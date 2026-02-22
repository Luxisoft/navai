export function logout_user(context: { navigate: (path: string) => void }) {
  try {
    const storage = (globalThis as { localStorage?: { removeItem: (key: string) => void } })
      .localStorage;
    storage?.removeItem("auth_token");
    storage?.removeItem("refresh_token");
  } catch {
    // Ignore storage errors to avoid breaking other functions.
  }

  context.navigate("/");
  return { ok: true, message: "Session closed." };
}
