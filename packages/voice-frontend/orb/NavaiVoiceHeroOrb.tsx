'use client';

import { useEffect, useMemo, useState } from "react";

import NavaiHeroOrb, { type NavaiHeroOrbProps } from "./NavaiHeroOrb";
import type {
  NavaiVoiceOrbHeroControllerApi,
  NavaiVoiceOrbResolveSnapshotInput,
  NavaiVoiceOrbRuntimeSnapshot,
  NavaiVoiceOrbThemeMode,
} from "./types";

export type NavaiVoiceHeroOrbProps = Omit<
  NavaiHeroOrbProps,
  "backgroundColor" | "isAgentSpeaking"
> & {
  themeMode?: NavaiVoiceOrbThemeMode;
  backgroundColorLight?: string;
  backgroundColorDark?: string;
  voiceController: NavaiVoiceOrbHeroControllerApi;
  resolveAgentRuntimeSnapshot: (
    input: NavaiVoiceOrbResolveSnapshotInput
  ) => NavaiVoiceOrbRuntimeSnapshot;
  onRuntimeSnapshotChange?: (snapshot: NavaiVoiceOrbRuntimeSnapshot) => void;
};

export default function NavaiVoiceHeroOrb({
  themeMode = "dark",
  backgroundColorLight = "#ffffff",
  backgroundColorDark = "#000000",
  voiceController,
  resolveAgentRuntimeSnapshot,
  onRuntimeSnapshotChange,
  ...orbProps
}: NavaiVoiceHeroOrbProps) {
  const [voiceSnapshot, setVoiceSnapshot] = useState(voiceController.createInitialVoiceSnapshot);
  const runtimeSnapshot = useMemo(() => {
    return resolveAgentRuntimeSnapshot(voiceSnapshot);
  }, [resolveAgentRuntimeSnapshot, voiceSnapshot]);

  useEffect(() => {
    setVoiceSnapshot(voiceController.getVoiceSnapshot());
    return voiceController.subscribeVoiceSnapshot((snapshot) => {
      setVoiceSnapshot(snapshot);
    });
  }, [voiceController]);

  useEffect(() => {
    if (typeof onRuntimeSnapshotChange !== "function") {
      return;
    }
    onRuntimeSnapshotChange(runtimeSnapshot);
  }, [onRuntimeSnapshotChange, runtimeSnapshot]);

  return (
    <NavaiHeroOrb
      {...orbProps}
      isAgentSpeaking={runtimeSnapshot.isAgentSpeaking}
      backgroundColor={themeMode === "light" ? backgroundColorLight : backgroundColorDark}
    />
  );
}
