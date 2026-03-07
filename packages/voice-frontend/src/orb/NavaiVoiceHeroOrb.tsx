import { useEffect } from "react";

import NavaiHeroOrb, { type NavaiHeroOrbProps } from "./NavaiHeroOrb";
import { resolveNavaiVoiceOrbRuntimeSnapshot } from "./NavaiVoiceOrbDock";
import type { NavaiVoiceOrbRuntimeSnapshot, NavaiVoiceOrbThemeMode, NavaiWebVoiceAgentLike } from "./types";

export type NavaiVoiceHeroOrbProps = Omit<NavaiHeroOrbProps, "backgroundColor" | "isAgentSpeaking"> & {
  agent: NavaiWebVoiceAgentLike;
  themeMode?: NavaiVoiceOrbThemeMode;
  backgroundColorLight?: string;
  backgroundColorDark?: string;
  onRuntimeSnapshotChange?: (snapshot: NavaiVoiceOrbRuntimeSnapshot) => void;
};

export default function NavaiVoiceHeroOrb({
  agent,
  themeMode = "dark",
  backgroundColorLight = "#ffffff",
  backgroundColorDark = "#000000",
  onRuntimeSnapshotChange,
  ...orbProps
}: NavaiVoiceHeroOrbProps) {
  const runtimeSnapshot = resolveNavaiVoiceOrbRuntimeSnapshot(agent);

  useEffect(() => {
    if (typeof onRuntimeSnapshotChange === "function") {
      onRuntimeSnapshotChange(runtimeSnapshot);
    }
  }, [onRuntimeSnapshotChange, runtimeSnapshot]);

  return (
    <NavaiHeroOrb
      {...orbProps}
      isAgentSpeaking={runtimeSnapshot.isAgentSpeaking}
      backgroundColor={themeMode === "light" ? backgroundColorLight : backgroundColorDark}
      className={themeMode === "light" ? "is-light" : ""}
    />
  );
}
