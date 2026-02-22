import type { ReactNode } from "react";

type PageSectionProps = {
  title: string;
  subtitle: string;
  children: ReactNode;
};

export function PageSection({ title, subtitle, children }: PageSectionProps) {
  return (
    <article className="page-card">
      <header>
        <p className="page-kicker">Section</p>
        <h2>{title}</h2>
        <p className="page-subtitle">{subtitle}</p>
      </header>
      <div className="page-content">{children}</div>
    </article>
  );
}
