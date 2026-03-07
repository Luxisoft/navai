import type { CSSProperties } from "react";

type NavaiVoiceOrbDockSpinnerIconProps = {
  size?: number;
};

export default function NavaiVoiceOrbDockSpinnerIcon({
  size = 20
}: NavaiVoiceOrbDockSpinnerIconProps) {
  const style: CSSProperties = {
    width: size,
    height: size
  };

  return <span aria-hidden="true" className="navai-voice-orb-spinner" style={style} />;
}
