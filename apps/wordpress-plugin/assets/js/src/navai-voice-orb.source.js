import { Mesh, Program, Renderer, Triangle, Vec3 } from "ogl";

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

    return extractAlpha(mix(darkCol, lightCol, bgLuminance));
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

const DEFAULT_OPTIONS = {
  hue: 0,
  autoHueShift: true,
  hueShiftMin: 0,
  hueShiftMax: 360,
  hueShiftHalfCycleSeconds: 30,
  hoverIntensity: 0.08,
  rotateOnHover: true,
  forceHoverState: false,
  enablePointerHover: false,
  backgroundColor: "#060914",
  animate: true
};

class NavaiVoiceOrb {
  constructor(surface, options) {
    this.surface = surface;
    this.options = { ...DEFAULT_OPTIONS, ...(options || {}) };
    this.backgroundColorVec = hexToVec3(this.options.backgroundColor);
    this.targetHover = 0;
    this.lastTime = 0;
    this.currentRotation = 0;
    this.animationFrameId = 0;
    this.idleTimerId = 0;
    this.lastRenderTime = 0;
    this.hasRenderedStaticFrame = false;
    this.frameIntervalMs = 1000 / 24;
    this.idleCheckIntervalMs = 750;
    this.rotationSpeed = 0.3;
    this.handlePointerMove = this.handlePointerMove.bind(this);
    this.handlePointerLeave = this.handlePointerLeave.bind(this);
    this.handleResize = this.handleResize.bind(this);
    this.update = this.update.bind(this);
    this.init();
  }

  init() {
    if (!this.surface) {
      return;
    }

    this.surface.innerHTML = "";
    this.container = document.createElement("span");
    this.container.className = "navai-orb-container";
    this.surface.appendChild(this.container);

    try {
      this.renderer = new Renderer({ alpha: true, premultipliedAlpha: false });
    } catch (_error) {
      return;
    }

    this.gl = this.renderer.gl;
    this.gl.clearColor(0, 0, 0, 0);
    this.container.appendChild(this.gl.canvas);

    this.geometry = new Triangle(this.gl);
    this.program = new Program(this.gl, {
      vertex: VERTEX_SHADER,
      fragment: FRAGMENT_SHADER,
      uniforms: {
        iTime: { value: 0 },
        iResolution: {
          value: new Vec3(
            this.gl.canvas.width,
            this.gl.canvas.height,
            this.gl.canvas.width / Math.max(this.gl.canvas.height, 1)
          )
        },
        hue: { value: this.options.hue },
        hover: { value: 0 },
        rot: { value: 0 },
        hoverIntensity: { value: this.options.hoverIntensity },
        backgroundColor: { value: this.backgroundColorVec }
      }
    });
    this.mesh = new Mesh(this.gl, {
      geometry: this.geometry,
      program: this.program
    });

    this.bindPointerEvents();
    window.addEventListener("resize", this.handleResize);
    if (typeof window.ResizeObserver === "function") {
      this.resizeObserver = new window.ResizeObserver(this.handleResize);
      this.resizeObserver.observe(this.surface);
    }

    this.handleResize();
    this.surface.classList.add("is-runtime-ready");
    this.scheduleNextFrame();
  }

  bindPointerEvents() {
    if (!this.container) {
      return;
    }

    this.container.removeEventListener("mousemove", this.handlePointerMove);
    this.container.removeEventListener("mouseleave", this.handlePointerLeave);

    if (this.options.enablePointerHover) {
      this.container.addEventListener("mousemove", this.handlePointerMove);
      this.container.addEventListener("mouseleave", this.handlePointerLeave);
    }
  }

  handleResize() {
    if (!this.renderer || !this.program || !this.gl || !this.surface) {
      return;
    }

    const dpr = window.devicePixelRatio || 1;
    const width = Math.max(this.surface.clientWidth, 1);
    const height = Math.max(this.surface.clientHeight, 1);
    this.renderer.setSize(width * dpr, height * dpr);
    this.gl.canvas.style.width = width + "px";
    this.gl.canvas.style.height = height + "px";
    this.program.uniforms.iResolution.value.set(
      this.gl.canvas.width,
      this.gl.canvas.height,
      this.gl.canvas.width / Math.max(this.gl.canvas.height, 1)
    );
  }

  handlePointerMove(event) {
    if (!this.options.enablePointerHover || !this.container) {
      return;
    }

    const rect = this.container.getBoundingClientRect();
    const x = event.clientX - rect.left;
    const y = event.clientY - rect.top;
    const size = Math.min(rect.width, rect.height);
    const centerX = rect.width / 2;
    const centerY = rect.height / 2;
    const uvX = ((x - centerX) / size) * 2;
    const uvY = ((y - centerY) / size) * 2;
    this.targetHover = Math.sqrt(uvX * uvX + uvY * uvY) < 0.8 ? 1 : 0;
  }

  handlePointerLeave() {
    if (!this.options.enablePointerHover) {
      return;
    }

    this.targetHover = 0;
  }

  getAnimatedHueValue(timeMs) {
    if (!this.options.autoHueShift) {
      return this.options.hue;
    }

    const minHue = this.options.hueShiftMin;
    const maxHue = this.options.hueShiftMax;
    const halfCycleSeconds = this.options.hueShiftHalfCycleSeconds;
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
  }

  renderStaticFrame(timeMs) {
    if (!this.program || !this.renderer || !this.mesh) {
      return;
    }

    this.program.uniforms.iTime.value = 0;
    this.program.uniforms.hue.value = this.getAnimatedHueValue(timeMs);
    this.program.uniforms.hoverIntensity.value = this.options.hoverIntensity;
    this.program.uniforms.backgroundColor.value = this.backgroundColorVec;
    this.program.uniforms.hover.value = 0;
    this.program.uniforms.rot.value = this.currentRotation;
    this.renderer.render({ scene: this.mesh });
  }

  scheduleNextFrame() {
    if (this.options.animate && !document.hidden) {
      this.animationFrameId = window.requestAnimationFrame(this.update);
      return;
    }

    this.idleTimerId = window.setTimeout(() => {
      this.animationFrameId = window.requestAnimationFrame(this.update);
    }, this.idleCheckIntervalMs);
  }

  update(timeMs) {
    if (!this.program || !this.renderer || !this.mesh) {
      return;
    }

    this.animationFrameId = 0;
    if (document.hidden) {
      this.scheduleNextFrame();
      return;
    }

    if (!this.options.animate) {
      if (!this.hasRenderedStaticFrame) {
        this.renderStaticFrame(timeMs);
        this.hasRenderedStaticFrame = true;
      }
      this.scheduleNextFrame();
      return;
    }

    this.hasRenderedStaticFrame = false;
    if (timeMs - this.lastRenderTime < this.frameIntervalMs) {
      this.scheduleNextFrame();
      return;
    }

    this.lastRenderTime = timeMs;
    const deltaSeconds = (timeMs - this.lastTime) * 0.001;
    this.lastTime = timeMs;
    this.program.uniforms.iTime.value = timeMs * 0.001;
    this.program.uniforms.hue.value = this.getAnimatedHueValue(timeMs);
    this.program.uniforms.hoverIntensity.value = this.options.hoverIntensity;
    this.program.uniforms.backgroundColor.value = this.backgroundColorVec;

    const effectiveHover = this.options.forceHoverState ? 1 : this.options.enablePointerHover ? this.targetHover : 0;
    this.program.uniforms.hover.value += (effectiveHover - this.program.uniforms.hover.value) * 0.1;

    if (this.options.rotateOnHover && effectiveHover > 0.5) {
      this.currentRotation += deltaSeconds * this.rotationSpeed;
    }

    this.program.uniforms.rot.value = this.currentRotation;
    this.renderer.render({ scene: this.mesh });
    this.scheduleNextFrame();
  }

  updateOptions(nextOptions) {
    this.options = { ...this.options, ...(nextOptions || {}) };
    this.backgroundColorVec = hexToVec3(this.options.backgroundColor);
    this.hasRenderedStaticFrame = false;
    this.bindPointerEvents();
    if (!this.animationFrameId && !this.idleTimerId) {
      this.scheduleNextFrame();
    }
  }

  destroy() {
    window.cancelAnimationFrame(this.animationFrameId);
    window.clearTimeout(this.idleTimerId);
    window.removeEventListener("resize", this.handleResize);
    if (this.resizeObserver) {
      this.resizeObserver.disconnect();
      this.resizeObserver = null;
    }

    if (this.container) {
      this.container.removeEventListener("mousemove", this.handlePointerMove);
      this.container.removeEventListener("mouseleave", this.handlePointerLeave);
    }

    if (this.gl) {
      this.gl.getExtension("WEBGL_lose_context")?.loseContext();
    }

    if (this.surface) {
      this.surface.classList.remove("is-runtime-ready");
      this.surface.innerHTML = "";
    }
  }
}

function createOrb(surface, options) {
  if (!surface) {
    return null;
  }

  destroyOrb(surface.__navaiVoiceOrbInstance || null);
  const instance = new NavaiVoiceOrb(surface, options);
  surface.__navaiVoiceOrbInstance = instance;
  return instance;
}

function updateOrb(target, options) {
  const instance = resolveInstance(target);
  if (!instance) {
    return null;
  }

  instance.updateOptions(options);
  return instance;
}

function destroyOrb(target) {
  const instance = resolveInstance(target);
  if (!instance) {
    return;
  }

  instance.destroy();
  if (instance.surface && instance.surface.__navaiVoiceOrbInstance === instance) {
    delete instance.surface.__navaiVoiceOrbInstance;
  }
}

function resolveInstance(target) {
  if (!target) {
    return null;
  }

  if (typeof target.updateOptions === "function") {
    return target;
  }

  if (target.__navaiVoiceOrbInstance) {
    return target.__navaiVoiceOrbInstance;
  }

  return null;
}

function hslToRgb(hue, saturation, lightness) {
  if (saturation === 0) {
    return new Vec3(lightness, lightness, lightness);
  }

  const hueToRgb = (p, q, t) => {
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
  return new Vec3(hueToRgb(p, q, hue + 1 / 3), hueToRgb(p, q, hue), hueToRgb(p, q, hue - 1 / 3));
}

function hexToVec3(color) {
  if (typeof color !== "string") {
    return new Vec3(0, 0, 0);
  }

  if (color.startsWith("#")) {
    const hex = color.slice(1);
    if (hex.length === 3) {
      return new Vec3(
        Number.parseInt(hex[0] + hex[0], 16) / 255,
        Number.parseInt(hex[1] + hex[1], 16) / 255,
        Number.parseInt(hex[2] + hex[2], 16) / 255
      );
    }

    if (hex.length >= 6) {
      return new Vec3(
        Number.parseInt(hex.slice(0, 2), 16) / 255,
        Number.parseInt(hex.slice(2, 4), 16) / 255,
        Number.parseInt(hex.slice(4, 6), 16) / 255
      );
    }
  }

  const rgbMatch = color.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/);
  if (rgbMatch) {
    const [, red, green, blue] = rgbMatch;
    return new Vec3(
      Number.parseInt(red || "0", 10) / 255,
      Number.parseInt(green || "0", 10) / 255,
      Number.parseInt(blue || "0", 10) / 255
    );
  }

  const hslMatch = color.match(/hsla?\((\d+),\s*(\d+)%,\s*(\d+)%/);
  if (hslMatch) {
    const [, hue, saturation, lightness] = hslMatch;
    return hslToRgb(
      Number.parseInt(hue || "0", 10) / 360,
      Number.parseInt(saturation || "0", 10) / 100,
      Number.parseInt(lightness || "0", 10) / 100
    );
  }

  return new Vec3(0, 0, 0);
}

window.NAVAI_VOICE_ORB = {
  create: createOrb,
  update: updateOrb,
  destroy: destroyOrb
};
