import { Link, Route, Routes, useLocation } from "react-router-dom";

import { HomePage } from "./pages/HomePage";
import { ProfilePage } from "./pages/ProfilePage";
import { SettingsPage } from "./pages/SettingsPage";
import { HelpPage } from "./pages/HelpPage";
import { NAVAI_ROUTE_ITEMS } from "./ai/routes";
import { VoiceNavigator } from "./voice/VoiceNavigator";

function HeaderNav() {
  const location = useLocation();

  return (
    <nav className="top-nav" aria-label="Main navigation">
      {NAVAI_ROUTE_ITEMS.map((route) => {
        const active = route.path === location.pathname;
        return (
          <Link key={route.path} to={route.path} className={active ? "top-nav-link active" : "top-nav-link"}>
            {route.name}
          </Link>
        );
      })}
    </nav>
  );
}

export function App() {
  return (
    <div className="app-shell">
      <div className="backdrop" aria-hidden />
      <header className="hero">
        <img className="hero-logo" src="/icon_navai.jpg" alt="NAVAI logo" />
        <p className="eyebrow">Navai Voice Playground</p>
        <h1>Voice-first app navigation</h1>
        <p className="hero-copy">
          Say: "llevame a perfil", "abre ajustes" or "cierra sesion". The agent can navigate routes and execute
          internal app functions through tools.
        </p>
        <HeaderNav />
        <VoiceNavigator />
      </header>

      <main className="page-wrap">
        <Routes>
          <Route path="/" element={<HomePage />} />
          <Route path="/profile" element={<ProfilePage />} />
          <Route path="/settings" element={<SettingsPage />} />
          <Route path="/help" element={<HelpPage />} />
        </Routes>
      </main>
    </div>
  );
}
