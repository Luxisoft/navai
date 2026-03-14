export const open_help_center = (context: { navigate: (path: string) => void }) => {
  context.navigate("/help");
  return { ok: true, path: "/help" };
};
