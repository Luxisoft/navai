'use client';

import { Mesh, Program, Renderer, Triangle, Vec3 } from "ogl";
import { useEffect, useRef } from "react";

import "./Orb.css";

export type OrbProps = {
  hue?: number;
  autoHueShift?: boolean;
  hueShiftMin?: number;
  hueShiftMax?: number;
  hueShiftHalfCycleSeconds?: number;
  hoverIntensity?: number;
  rotateOnHover?: boolean;
  forceHoverState?: boolean;
  enablePointerHover?: boolean;
  backgroundColor?: string;
  animate?: boolean;
};

const VERTEX_SHADER = /* glsl */ `
  precision highp float;
  attribute vec2 position;
  attribute vec2 uv;
  varying vec2 vUv;
  void main() {
    vUv = uv;
    gl_Position = vec4(position, 0.0, 1.0);
  }
`;

const FRAGMENT_SHADER = /* glsl */ `
  precision highp float;

  uniform float iTime;
  uniform vec3 iResolution;
  uniform float hue;
  uniform float hover;
  uniform float rot;
  uniform float hoverIntensity;
  uniform vec3 backgroundColor;
  varying vec2 vUv;

  vec3 rgb2yiq(vec3 c) {
    float y = dot(c, vec3(0.299, 0.587, 0.114));
    float i = dot(c, vec3(0.596, -0.274, -0.322));
    float q = dot(c, vec3(0.211, -0.523, 0.312));
    return vec3(y, i, q);
  }

  vec3 yiq2rgb(vec3 c) {
    float r = c.x + 0.956 * c.y + 0.621 * c.z;
    float g = c.x - 0.272 * c.y - 0.647 * c.z;
    float b = c.x - 1.106 * c.y + 1.703 * c.z;
    return vec3(r, g, b);
  }

  vec3 adjustHue(vec3 color, float hueDeg) {
    float hueRad = hueDeg * 3.14159265 / 180.0;
    vec3 yiq = rgb2yiq(color);
    float cosA = cos(hueRad);
    float sinA = sin(hueRad);
    float i = yiq.y * cosA - yiq.z * sinA;
    float q = yiq.y * sinA + yiq.z * cosA;
    yiq.y = i;
    yiq.z = q;
    return yiq2rgb(yiq);
  }

  vec3 hash33(vec3 p3) {
    p3 = fract(p3 * vec3(0.1031, 0.11369, 0.13787));
    p3 += dot(p3, p3.yxz + 19.19);
    return -1.0 + 2.0 * fract(vec3(
      p3.x + p3.y,
      p3.x + p3.z,
      p3.y + p3.z
    ) * p3.zyx);
  }

  float snoise3(vec3 p) {
    const float K1 = 0.333333333;
    const float K2 = 0.166666667;
    vec3 i = floor(p + (p.x + p.y + p.z) * K1);
    vec3 d0 = p - (i - (i.x + i.y + i.z) * K2);
    vec3 e = step(vec3(0.0), d0 - d0.yzx);
    vec3 i1 = e * (1.0 - e.zxy);
    vec3 i2 = 1.0 - e.zxy * (1.0 - e);
    vec3 d1 = d0 - (i1 - K2);
    vec3 d2 = d0 - (i2 - K1);
    vec3 d3 = d0 - 0.5;
    vec4 h = max(0.6 - vec4(
      dot(d0, d0),
      dot(d1, d1),
      dot(d2, d2),
      dot(d3, d3)
    ), 0.0);
    vec4 n = h * h * h * h * vec4(
      dot(d0, hash33(i)),
      dot(d1, hash33(i + i1)),
      dot(d2, hash33(i + i2)),
      dot(d3, hash33(i + 1.0))
    );
    return dot(vec4(31.316), n);
  }

  vec4 extractAlpha(vec3 colorIn) {
    float a = max(max(colorIn.r, colorIn.g), colorIn.b);
    return vec4(colorIn.rgb / (a + 1e-5), a);
  }

  const vec3 baseColor1 = vec3(0.611765, 0.262745, 0.996078);
  const vec3 baseColor2 = vec3(0.298039, 0.760784, 0.913725);
  const vec3 baseColor3 = vec3(0.062745, 0.078431, 0.600000);
  const float innerRadius = 0.6;
  const float noiseScale = 0.65;

  float light1(float intensity, float attenuation, float dist) {
    return intensity / (1.0 + dist * attenuation);
  }
  float light2(float intensity, float attenuation, float dist) {
    return intensity / (1.0 + dist * dist * attenuation);
  }

  vec4 draw(vec2 uv) {
    vec3 color1 = adjustHue(baseColor1, hue);
    vec3 color2 = adjustHue(baseColor2, hue);
    vec3 color3 = adjustHue(baseColor3, hue);

    float ang = atan(uv.y, uv.x);
    float len = length(uv);
    float invLen = len > 0.0 ? 1.0 / len : 0.0;

    float bgLuminance = dot(backgroundColor, vec3(0.299, 0.587, 0.114));

    float n0 = snoise3(vec3(uv * noiseScale, iTime * 0.5)) * 0.5 + 0.5;
    float r0 = mix(mix(innerRadius, 1.0, 0.4), mix(innerRadius, 1.0, 0.6), n0);
    float d0 = distance(uv, (r0 * invLen) * uv);
    float v0 = light1(1.0, 10.0, d0);

    v0 *= smoothstep(r0 * 1.05, r0, len);
    float innerFade = smoothstep(r0 * 0.8, r0 * 0.95, len);
    v0 *= mix(innerFade, 1.0, bgLuminance * 0.7);
    float cl = cos(ang + iTime * 2.0) * 0.5 + 0.5;

    float a = iTime * -1.0;
    vec2 pos = vec2(cos(a), sin(a)) * r0;
    float d = distance(uv, pos);
    float v1 = light2(1.5, 5.0, d);
    v1 *= light1(1.0, 50.0, d0);

    float v2 = smoothstep(1.0, mix(innerRadius, 1.0, n0 * 0.5), len);
    float v3 = smoothstep(innerRadius, mix(innerRadius, 1.0, 0.5), len);

    vec3 colBase = mix(color1, color2, cl);
    float fadeAmount = mix(1.0, 0.1, bgLuminance);

    vec3 darkCol = mix(color3, colBase, v0);
    darkCol = (darkCol + v1) * v2 * v3;
    darkCol = clamp(darkCol, 0.0, 1.0);

    vec3 lightCol = (colBase + v1) * mix(1.0, v2 * v3, fadeAmount);
    lightCol = mix(backgroundColor, lightCol, v0);
    lightCol = clamp(lightCol, 0.0, 1.0);

    vec3 finalCol = mix(darkCol, lightCol, bgLuminance);

    return extractAlpha(finalCol);
  }

  vec4 mainImage(vec2 fragCoord) {
    vec2 center = iResolution.xy * 0.5;
    float size = min(iResolution.x, iResolution.y);
    vec2 uv = (fragCoord - center) / size * 2.0;

    float angle = rot;
    float s = sin(angle);
    float c = cos(angle);
    uv = vec2(c * uv.x - s * uv.y, s * uv.x + c * uv.y);

    uv.x += hover * hoverIntensity * 0.1 * sin(uv.y * 10.0 + iTime);
    uv.y += hover * hoverIntensity * 0.1 * sin(uv.x * 10.0 + iTime);

    return draw(uv);
  }

  void main() {
    vec2 fragCoord = vUv * iResolution.xy;
    vec4 col = mainImage(fragCoord);
    gl_FragColor = vec4(col.rgb * col.a, col.a);
  }
`;

export default function Orb({
  hue = 0,
  autoHueShift = true,
  hueShiftMin = 0,
  hueShiftMax = 360,
  hueShiftHalfCycleSeconds = 30,
  hoverIntensity = 0.2,
  rotateOnHover = true,
  forceHoverState = false,
  enablePointerHover = true,
  backgroundColor = "#000000",
  animate = true,
}: OrbProps) {
  const containerRef = useRef<HTMLDivElement | null>(null);
  const hueRef = useRef(hue);
  const autoHueShiftRef = useRef(autoHueShift);
  const hueShiftMinRef = useRef(hueShiftMin);
  const hueShiftMaxRef = useRef(hueShiftMax);
  const hueShiftHalfCycleSecondsRef = useRef(hueShiftHalfCycleSeconds);
  const hoverIntensityRef = useRef(hoverIntensity);
  const rotateOnHoverRef = useRef(rotateOnHover);
  const forceHoverStateRef = useRef(forceHoverState);
  const enablePointerHoverRef = useRef(enablePointerHover);
  const backgroundColorVecRef = useRef(hexToVec3(backgroundColor));
  const animateRef = useRef(animate);

  useEffect(() => {
    hueRef.current = hue;
  }, [hue]);

  useEffect(() => {
    autoHueShiftRef.current = autoHueShift;
  }, [autoHueShift]);

  useEffect(() => {
    hueShiftMinRef.current = hueShiftMin;
  }, [hueShiftMin]);

  useEffect(() => {
    hueShiftMaxRef.current = hueShiftMax;
  }, [hueShiftMax]);

  useEffect(() => {
    hueShiftHalfCycleSecondsRef.current = hueShiftHalfCycleSeconds;
  }, [hueShiftHalfCycleSeconds]);

  useEffect(() => {
    hoverIntensityRef.current = hoverIntensity;
  }, [hoverIntensity]);

  useEffect(() => {
    rotateOnHoverRef.current = rotateOnHover;
  }, [rotateOnHover]);

  useEffect(() => {
    forceHoverStateRef.current = forceHoverState;
  }, [forceHoverState]);

  useEffect(() => {
    enablePointerHoverRef.current = enablePointerHover;
  }, [enablePointerHover]);

  useEffect(() => {
    backgroundColorVecRef.current = hexToVec3(backgroundColor);
  }, [backgroundColor]);

  useEffect(() => {
    animateRef.current = animate;
  }, [animate]);

  useEffect(() => {
    const container = containerRef.current;
    if (!container) {
      return;
    }

    const renderer = new Renderer({ alpha: true, premultipliedAlpha: false });
    const gl = renderer.gl;
    gl.clearColor(0, 0, 0, 0);
    container.appendChild(gl.canvas);

    const geometry = new Triangle(gl);
    const program = new Program(gl, {
      vertex: VERTEX_SHADER,
      fragment: FRAGMENT_SHADER,
      uniforms: {
        iTime: { value: 0 },
        iResolution: {
          value: new Vec3(gl.canvas.width, gl.canvas.height, gl.canvas.width / gl.canvas.height),
        },
        hue: { value: hueRef.current },
        hover: { value: 0 },
        rot: { value: 0 },
        hoverIntensity: { value: hoverIntensityRef.current },
        backgroundColor: { value: backgroundColorVecRef.current },
      },
    });

    const mesh = new Mesh(gl, { geometry, program });

    const resize = () => {
      const dpr = window.devicePixelRatio || 1;
      const width = container.clientWidth;
      const height = container.clientHeight;
      renderer.setSize(width * dpr, height * dpr);
      gl.canvas.style.width = `${width}px`;
      gl.canvas.style.height = `${height}px`;
      program.uniforms.iResolution.value.set(gl.canvas.width, gl.canvas.height, gl.canvas.width / gl.canvas.height);
    };

    window.addEventListener("resize", resize);
    resize();

    let targetHover = 0;
    let lastTime = 0;
    let currentRotation = 0;
    const rotationSpeed = 0.3;

    const handleMouseMove = (event: MouseEvent) => {
      if (!enablePointerHoverRef.current) {
        return;
      }

      const rect = container.getBoundingClientRect();
      const x = event.clientX - rect.left;
      const y = event.clientY - rect.top;
      const width = rect.width;
      const height = rect.height;
      const size = Math.min(width, height);
      const centerX = width / 2;
      const centerY = height / 2;
      const uvX = ((x - centerX) / size) * 2;
      const uvY = ((y - centerY) / size) * 2;

      targetHover = Math.sqrt(uvX * uvX + uvY * uvY) < 0.8 ? 1 : 0;
    };

    const handleMouseLeave = () => {
      if (!enablePointerHoverRef.current) {
        return;
      }
      targetHover = 0;
    };

    container.addEventListener("mousemove", handleMouseMove);
    container.addEventListener("mouseleave", handleMouseLeave);

    let animationFrameId = 0;
    let idleTimerId = 0;
    let lastRenderTime = 0;
    let hasRenderedStaticFrame = false;
    const frameIntervalMs = 1000 / 24;
    const idleCheckIntervalMs = 750;

    const getAnimatedHueValue = (timeMs = 0) => {
      if (!autoHueShiftRef.current) {
        return hueRef.current;
      }

      const minHue = hueShiftMinRef.current;
      const maxHue = hueShiftMaxRef.current;
      const halfCycleSeconds = hueShiftHalfCycleSecondsRef.current;
      const hueRange = maxHue - minHue;
      if (halfCycleSeconds <= 0 || hueRange <= 0) {
        return minHue;
      }

      const fullCycleSeconds = halfCycleSeconds * 2;
      const elapsedSeconds = timeMs * 0.001;
      const cycleSeconds = ((elapsedSeconds % fullCycleSeconds) + fullCycleSeconds) % fullCycleSeconds;
      const halfCycleProgress = cycleSeconds / halfCycleSeconds;
      const wave = halfCycleProgress <= 1 ? halfCycleProgress : 2 - halfCycleProgress;
      return minHue + wave * hueRange;
    };

    const renderStaticFrame = (timeMs = 0) => {
      program.uniforms.iTime.value = 0;
      program.uniforms.hue.value = getAnimatedHueValue(timeMs);
      program.uniforms.hoverIntensity.value = hoverIntensityRef.current;
      program.uniforms.backgroundColor.value = backgroundColorVecRef.current;
      program.uniforms.hover.value = 0;
      program.uniforms.rot.value = currentRotation;
      renderer.render({ scene: mesh });
    };

    const scheduleNextFrame = () => {
      if (animateRef.current && !document.hidden) {
        animationFrameId = requestAnimationFrame(update);
        return;
      }

      idleTimerId = window.setTimeout(() => {
        animationFrameId = requestAnimationFrame(update);
      }, idleCheckIntervalMs);
    };

    const update = (timeMs: number) => {
      if (document.hidden) {
        scheduleNextFrame();
        return;
      }

      if (!animateRef.current) {
        if (!hasRenderedStaticFrame) {
          renderStaticFrame(timeMs);
          hasRenderedStaticFrame = true;
        }
        scheduleNextFrame();
        return;
      }

      hasRenderedStaticFrame = false;
      if (timeMs - lastRenderTime < frameIntervalMs) {
        scheduleNextFrame();
        return;
      }
      lastRenderTime = timeMs;

      const deltaSeconds = (timeMs - lastTime) * 0.001;
      lastTime = timeMs;
      program.uniforms.iTime.value = timeMs * 0.001;
      program.uniforms.hue.value = getAnimatedHueValue(timeMs);
      program.uniforms.hoverIntensity.value = hoverIntensityRef.current;
      program.uniforms.backgroundColor.value = backgroundColorVecRef.current;

      const effectiveHover = forceHoverStateRef.current ? 1 : enablePointerHoverRef.current ? targetHover : 0;
      program.uniforms.hover.value += (effectiveHover - program.uniforms.hover.value) * 0.1;

      if (rotateOnHoverRef.current && effectiveHover > 0.5) {
        currentRotation += deltaSeconds * rotationSpeed;
      }
      program.uniforms.rot.value = currentRotation;

      renderer.render({ scene: mesh });
      scheduleNextFrame();
    };

    scheduleNextFrame();

    return () => {
      cancelAnimationFrame(animationFrameId);
      window.clearTimeout(idleTimerId);
      window.removeEventListener("resize", resize);
      container.removeEventListener("mousemove", handleMouseMove);
      container.removeEventListener("mouseleave", handleMouseLeave);
      if (gl.canvas.parentElement === container) {
        container.removeChild(gl.canvas);
      }
      gl.getExtension("WEBGL_lose_context")?.loseContext();
    };
  }, []);

  return <div ref={containerRef} className="orb-container" />;
}

function hslToRgb(hue: number, saturation: number, lightness: number) {
  if (saturation === 0) {
    return new Vec3(lightness, lightness, lightness);
  }

  const hueToRgb = (p: number, q: number, t: number) => {
    let normalizedT = t;
    if (normalizedT < 0) {
      normalizedT += 1;
    }
    if (normalizedT > 1) {
      normalizedT -= 1;
    }
    if (normalizedT < 1 / 6) {
      return p + (q - p) * 6 * normalizedT;
    }
    if (normalizedT < 1 / 2) {
      return q;
    }
    if (normalizedT < 2 / 3) {
      return p + (q - p) * (2 / 3 - normalizedT) * 6;
    }
    return p;
  };

  const q = lightness < 0.5 ? lightness * (1 + saturation) : lightness + saturation - lightness * saturation;
  const p = 2 * lightness - q;
  const red = hueToRgb(p, q, hue + 1 / 3);
  const green = hueToRgb(p, q, hue);
  const blue = hueToRgb(p, q, hue - 1 / 3);

  return new Vec3(red, green, blue);
}

function hexToVec3(color: string) {
  if (color.startsWith("#")) {
    const red = Number.parseInt(color.slice(1, 3), 16) / 255;
    const green = Number.parseInt(color.slice(3, 5), 16) / 255;
    const blue = Number.parseInt(color.slice(5, 7), 16) / 255;
    return new Vec3(red, green, blue);
  }

  const rgbMatch = color.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/);
  if (rgbMatch) {
    return new Vec3(
      Number.parseInt(rgbMatch[1], 10) / 255,
      Number.parseInt(rgbMatch[2], 10) / 255,
      Number.parseInt(rgbMatch[3], 10) / 255
    );
  }

  const hslMatch = color.match(/hsla?\((\d+),\s*(\d+)%,\s*(\d+)%/);
  if (hslMatch) {
    const hue = Number.parseInt(hslMatch[1], 10) / 360;
    const saturation = Number.parseInt(hslMatch[2], 10) / 100;
    const lightness = Number.parseInt(hslMatch[3], 10) / 100;
    return hslToRgb(hue, saturation, lightness);
  }

  return new Vec3(0, 0, 0);
}
