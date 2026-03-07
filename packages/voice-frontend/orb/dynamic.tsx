import { lazy, Suspense, useEffect, useState, type ComponentType, type ReactNode } from "react";

type DynamicLoader<Props> = () => Promise<{ default: ComponentType<Props> } | ComponentType<Props>>;

type DynamicOptions = {
  ssr?: boolean;
  loading?: ComponentType<Record<string, never>>;
};

export default function dynamic<Props extends object>(
  loader: DynamicLoader<Props>,
  options: DynamicOptions = {}
): ComponentType<Props> {
  const { ssr = true, loading: LoadingComponent } = options;

  const LazyComponent = lazy(async () => {
    const loaded = await loader();
    if (typeof loaded === "function") {
      return { default: loaded };
    }
    return loaded;
  });

  function DynamicComponent(props: Props) {
    const [isClientReadyForNoSsr, setIsClientReadyForNoSsr] = useState(ssr);

    useEffect(() => {
      if (!ssr) {
        setIsClientReadyForNoSsr(true);
      }
    }, [ssr]);

    if (!isClientReadyForNoSsr) {
      return null;
    }

    const fallback: ReactNode = LoadingComponent ? <LoadingComponent /> : null;
    return (
      <Suspense fallback={fallback}>
        <LazyComponent {...props} />
      </Suspense>
    );
  }

  DynamicComponent.displayName = "DynamicComponent";
  return DynamicComponent;
}
