type NavaiVoiceOrbDockMicIconProps = {
  isActive?: boolean;
  size?: number;
};

export default function NavaiVoiceOrbDockMicIcon({
  isActive = false,
  size = 20,
}: NavaiVoiceOrbDockMicIconProps) {
  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
      className={["navai-mini-mic-glyph", isActive ? "pulse" : ""].filter(Boolean).join(" ")}
    >
      <path d="M12 3a3 3 0 0 0-3 3v6a3 3 0 1 0 6 0V6a3 3 0 0 0-3-3Z" />
      <path d="M19 10v2a7 7 0 1 1-14 0v-2" />
      <path d="M12 19v3" />
    </svg>
  );
}
