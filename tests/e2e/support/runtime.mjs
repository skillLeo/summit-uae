/**
 * In-page QA runtime. `installRuntime` is serialised by Playwright and evaluated inside the page
 * (CDP evaluation is not subject to the page's CSP), so it must stay self-contained: no imports,
 * no closures over module scope. It defines `window.__qa`, which the specs call through
 * `page.evaluate(() => window.__qa.…())`.
 */
export function installRuntime() {
  if (window.__qa) return;

  // ------------------------------------------------------------------ DOM helpers
  const textOf = (el) =>
    String(
      el.getAttribute?.('aria-label') ||
        el.innerText ||
        el.textContent ||
        el.getAttribute?.('title') ||
        el.getAttribute?.('alt') ||
        el.value ||
        '',
    )
      .replace(/\s+/g, ' ')
      .trim()
      .slice(0, 60);

  /** Short, human-readable selector-ish label: tag#id.class "text". */
  const describe = (el) => {
    if (!el || el.nodeType !== 1) return String(el);
    const tag = el.tagName.toLowerCase();
    const id = el.id ? `#${el.id}` : '';
    const cls = (el.getAttribute('class') || '').trim().split(/\s+/).filter(Boolean).slice(0, 3);
    const label = textOf(el);
    return `${tag}${id}${cls.length ? `.${cls.join('.')}` : ''}${label ? ` "${label}"` : ''}`;
  };

  const snippet = (el, max = 220) => {
    const html = (el.outerHTML || '').replace(/\s+/g, ' ');
    return html.length > max ? `${html.slice(0, max)}…` : html;
  };

  /** Ancestor test that crosses shadow roots. */
  const composedContains = (ancestor, node) => {
    for (let n = node; n; n = n.parentNode || n.host || null) {
      if (n === ancestor) return true;
    }
    return false;
  };

  const deepActiveElement = () => {
    let a = document.activeElement;
    while (a && a.shadowRoot && a.shadowRoot.activeElement) a = a.shadowRoot.activeElement;
    return a;
  };

  const deepElementFromPoint = (x, y) => {
    let el = document.elementFromPoint(x, y);
    while (el && el.shadowRoot) {
      const inner = el.shadowRoot.elementFromPoint(x, y);
      if (!inner || inner === el) break;
      el = inner;
    }
    return el;
  };

  const rendered = (el) =>
    typeof el.checkVisibility === 'function'
      ? el.checkVisibility({ visibilityProperty: true, contentVisibilityAuto: true })
      : el.getClientRects().length > 0;

  /**
   * What the user actually sees, in client (layout-viewport) coordinates. On mobile the layout
   * viewport can be wider and taller than the screen (content wider than the device widens it),
   * so innerWidth / innerHeight are not the fold: the visual viewport is.
   */
  const visibleArea = () => {
    const vv = window.visualViewport;
    if (!vv) return { left: 0, top: 0, right: innerWidth, bottom: innerHeight };
    return { left: vv.offsetLeft, top: vv.offsetTop, right: vv.offsetLeft + vv.width, bottom: vv.offsetTop + vv.height };
  };

  const nextFrame = () => new Promise((resolve) => requestAnimationFrame(() => resolve()));
  const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

  // ------------------------------------------------------------------ settling
  /** Resolves once window scrolling (smooth scroll after focus) has been still for 3 frames. */
  const settleScroll = async (maxMs = 1500) => {
    const start = performance.now();
    let last = `${scrollX},${scrollY}`;
    let still = 0;
    while (performance.now() - start < maxMs) {
      await nextFrame();
      const now = `${scrollX},${scrollY}`;
      if (now === last) {
        if (++still >= 3) return;
      } else {
        still = 0;
        last = now;
      }
    }
  };

  const isFiniteDocumentAnimation = (a) => {
    const timing = a.effect && a.effect.getComputedTiming ? a.effect.getComputedTiming() : null;
    return (
      Boolean(timing) &&
      a.timeline === document.timeline &&
      Number.isFinite(timing.endTime) &&
      (a.playState === 'running' || a.pending)
    );
  };

  /** Waits for running, finite, time-based animations (optionally only those around `el`). */
  const settleAnimations = async (el = null, maxMs = 4000) => {
    const pending = document.getAnimations().filter((a) => {
      if (!isFiniteDocumentAnimation(a)) return false;
      if (!el) return true;
      const target = a.effect.target;
      return Boolean(target) && (composedContains(target, el) || composedContains(el, target));
    });
    if (!pending.length) return 0;
    await Promise.race([Promise.all(pending.map((a) => a.finished.catch(() => null))), sleep(maxMs)]);
    return pending.length;
  };

  // ------------------------------------------------------------------ colour / ring parsing
  const alphaOf = (color) => {
    if (!color || color === 'transparent') return 0;
    let m = color.match(/^rgba?\(([^)]+)\)$/i);
    if (m) {
      const parts = m[1].split(/[\s,/]+/).filter(Boolean);
      if (parts.length < 4) return 1;
      const a = parts[3];
      return a.endsWith('%') ? parseFloat(a) / 100 : parseFloat(a);
    }
    m = color.match(/\/\s*([\d.]+)(%?)\s*\)$/);
    if (m) return m[2] ? parseFloat(m[1]) / 100 : parseFloat(m[1]);
    return 1;
  };

  const COLOR_RE = /(rgba?\([^)]*\)|hsla?\([^)]*\)|color\([^)]*\)|ok(?:lch|lab)\([^)]*\)|l(?:ch|ab)\([^)]*\)|#[0-9a-f]{3,8}\b|transparent)/i;

  const shadowIsVisible = (boxShadow) => {
    if (!boxShadow || boxShadow === 'none') return false;
    const layers = [];
    let depth = 0;
    let current = '';
    for (const ch of boxShadow) {
      if (ch === '(') depth += 1;
      if (ch === ')') depth -= 1;
      if (ch === ',' && depth === 0) {
        layers.push(current);
        current = '';
      } else {
        current += ch;
      }
    }
    layers.push(current);
    return layers.some((layer) => {
      const color = (layer.match(COLOR_RE) || [])[0];
      const lengths = layer.replace(color || '', '').match(/-?[\d.]+px/g) || [];
      return (color ? alphaOf(color) > 0 : true) && lengths.some((v) => parseFloat(v) !== 0);
    });
  };

  const ringOf = (el, pseudo = null) => {
    const cs = getComputedStyle(el, pseudo);
    if (pseudo && (cs.content === 'none' || cs.content === 'normal')) return null;
    const outlineWidth = parseFloat(cs.outlineWidth) || 0;
    return {
      on: `${describe(el)}${pseudo || ''}`,
      outline: cs.outlineStyle !== 'none' && outlineWidth > 0 && alphaOf(cs.outlineColor) > 0,
      shadow: shadowIsVisible(cs.boxShadow),
      outlineStyle: cs.outlineStyle,
      outlineWidth,
      outlineColor: cs.outlineColor,
      boxShadow: cs.boxShadow,
    };
  };

  // ------------------------------------------------------------------ visibility
  /**
   * A native input that is visually hidden behind a custom control (radio, checkbox, file …).
   * Its label or sibling carries the visible box and the focus ring instead.
   */
  const isHiddenNativeControl = (el) => {
    if (!/^(INPUT|SELECT|TEXTAREA)$/.test(el.tagName)) return false;
    const r = el.getBoundingClientRect();
    const cs = getComputedStyle(el);
    return (
      r.width <= 2 ||
      r.height <= 2 ||
      parseFloat(cs.opacity) === 0 ||
      cs.clipPath.includes('inset(50%)') ||
      /^rect\(0(px)?,? 0(px)?/.test(cs.clip)
    );
  };

  const proxiesFor = (el) => {
    const list = [];
    if (el.labels) list.push(...[...el.labels].filter(rendered));
    if (el.nextElementSibling && rendered(el.nextElementSibling)) list.push(el.nextElementSibling);
    if (el.parentElement && rendered(el.parentElement)) list.push(el.parentElement);
    return list;
  };

  /**
   * On screen = rendered, opaque, intersecting the viewport and not *entirely* covered by other
   * content at five sample points (WCAG 2.2 SC 2.4.11 Focus Not Obscured, minimum).
   */
  const onScreen = (el, owner = el) => {
    const r = el.getBoundingClientRect();
    if (r.width <= 2 || r.height <= 2) {
      return { ok: false, reason: `visually hidden (${Math.round(r.width)}×${Math.round(r.height)} px box)` };
    }
    const cs = getComputedStyle(el);
    if (cs.clipPath.includes('inset(50%)') || /^rect\(0(px)?,? 0(px)?/.test(cs.clip)) {
      return { ok: false, reason: 'visually hidden (clipped by clip / clip-path)' };
    }
    const opaque =
      typeof el.checkVisibility === 'function'
        ? el.checkVisibility({ opacityProperty: true, visibilityProperty: true })
        : true;
    if (!opaque) return { ok: false, reason: 'hidden (display, visibility or opacity 0)' };
    const view = visibleArea();
    const x1 = Math.max(view.left, r.left);
    const y1 = Math.max(view.top, r.top);
    const x2 = Math.min(view.right, r.right);
    const y2 = Math.min(view.bottom, r.bottom);
    if (x2 - x1 < 1 || y2 - y1 < 1) {
      return { ok: false, reason: `outside the viewport (${Math.round(r.left)},${Math.round(r.top)})` };
    }
    const points = [
      [0.5, 0.5],
      [0.2, 0.2],
      [0.8, 0.2],
      [0.2, 0.8],
      [0.8, 0.8],
    ];
    let coveredBy = null;
    for (const [fx, fy] of points) {
      const hit = deepElementFromPoint(x1 + (x2 - x1) * fx, y1 + (y2 - y1) * fy);
      if (!hit) continue;
      if (composedContains(el, hit) || composedContains(owner, hit)) return { ok: true };
      if (owner.labels && [...owner.labels].some((l) => composedContains(l, hit))) return { ok: true };
      coveredBy = coveredBy || hit;
    }
    return { ok: false, reason: `covered by ${coveredBy ? describe(coveredBy) : 'nothing hit-testable'}` };
  };

  // ------------------------------------------------------------------ focus inspection
  const focusIndicator = (el, proxies) => {
    const rings = [ringOf(el), ringOf(el, '::before'), ringOf(el, '::after')];
    for (const p of proxies) rings.push(ringOf(p), ringOf(p, '::before'), ringOf(p, '::after'));
    const shown = rings.filter(Boolean).filter((r) => r.outline || r.shadow);
    const own = rings[0];
    if (!shown.length) {
      return {
        ok: false,
        thin: false,
        detail: `outline ${own.outlineStyle} ${own.outlineWidth}px ${own.outlineColor}; box-shadow ${own.boxShadow}`,
      };
    }
    const best = shown[0];
    // Spec: 2px focus ring. Only a drawn outline has a measurable width; "auto" is the UA ring.
    const thin = !best.shadow && best.outlineStyle !== 'auto' && best.outlineWidth < 2;
    return {
      ok: true,
      thin,
      detail: best.shadow ? `box-shadow ${best.boxShadow} on ${best.on}` : `outline ${best.outlineStyle} ${best.outlineWidth}px ${best.outlineColor} on ${best.on}`,
    };
  };

  const inspectFocus = async () => {
    const el = deepActiveElement();
    if (!el || el === document.body || el === document.documentElement) return { body: true };
    if (el.tagName === 'IFRAME') {
      return { body: false, iframe: true, label: describe(el), src: el.getAttribute('src') || '' };
    }
    // Focus inside the browser's own shadow tree (the calendar button of a date input): the
    // host does not match :focus and the browser draws its own ring, which pages cannot inspect.
    if (!el.matches(':focus') && el.matches(':focus-within')) {
      return { body: false, iframe: false, internal: true, label: describe(el) };
    }
    await settleScroll();
    await settleAnimations(el, 1500);
    const hiddenControl = isHiddenNativeControl(el);
    const proxies = hiddenControl ? proxiesFor(el) : [];
    const box = hiddenControl && proxies.length ? proxies[0] : el;
    const visible = onScreen(box, el);
    const ring = focusIndicator(el, proxies);
    return {
      body: false,
      iframe: false,
      label: describe(el),
      tag: el.tagName.toLowerCase(),
      id: el.id || null,
      href: el.getAttribute('href'),
      visible,
      ring,
    };
  };

  // ------------------------------------------------------------------ button reachability
  const BUTTONS =
    'button, [role="button"], input[type="button"], input[type="submit"], input[type="reset"], input[type="image"], summary';
  const COMPOSITE =
    '[role="toolbar"], [role="tablist"], [role="radiogroup"], [role="menubar"], [role="menu"], [role="listbox"], [role="grid"], [role="treegrid"], [role="tree"]';

  const focusLog = { order: [], seen: new Set(), buttons: [] };

  const startFocusLog = () => {
    focusLog.buttons = [...document.querySelectorAll(BUTTONS)].filter(
      (b) => rendered(b) && !b.closest('[inert], [aria-hidden="true"], template') && !b.matches(':disabled'),
    );
    document.addEventListener(
      'focusin',
      (event) => {
        const target = event.composedPath()[0];
        if (target && target.nodeType === 1) {
          focusLog.order.push(target);
          focusLog.seen.add(target);
        }
      },
      true,
    );
    return focusLog.buttons.length;
  };

  const focusLogState = () => {
    const { order } = focusLog;
    const first = order[0];
    const active = deepActiveElement();
    const wrapped =
      order.length > 1 && (order.indexOf(first, 1) !== -1 || active === document.body || active === null);
    return { stops: new Set(order).size, presses: order.length, wrapped };
  };

  const unreachedButtons = () => {
    const seen = [...focusLog.seen];
    return focusLog.buttons
      .filter((b) => b.isConnected && !focusLog.seen.has(b))
      .filter((b) => {
        // Roving tabindex: one Tab stop per composite widget, arrow keys inside it.
        const group = b.closest(COMPOSITE);
        return !(group && seen.some((s) => group.contains(s)));
      })
      .map((b) => {
        const tabindex = b.getAttribute('tabindex');
        const nativelyFocusable = /^(BUTTON|INPUT|SUMMARY)$/.test(b.tagName);
        let reason = 'never received focus while tabbing through the page';
        if (tabindex !== null && Number(tabindex) < 0) reason = `tabindex="${tabindex}"`;
        else if (!nativelyFocusable && tabindex === null) reason = 'role="button" without tabindex (not focusable)';
        return { label: describe(b), reason, html: snippet(b, 160) };
      });
  };

  // ------------------------------------------------------------------ mobile bottom bar
  const bottomNav = () => {
    const vw = innerWidth;
    const vh = innerHeight;
    const pinned = (el) => {
      for (let n = el; n && n !== document.documentElement; n = n.parentElement) {
        const { position } = getComputedStyle(n);
        if (position === 'fixed' || position === 'sticky') return true;
      }
      return false;
    };
    const fits = (el) => {
      if (!rendered(el)) return false;
      const r = el.getBoundingClientRect();
      return pinned(el) && r.bottom >= vh - 4 && r.top >= vh * 0.5 && r.width >= vw * 0.9;
    };
    const explicit = [...document.querySelectorAll('[data-qa="bottom-nav"], .bottombar')];
    const nav =
      explicit.find(fits) || [...document.querySelectorAll('nav, [role="navigation"]')].find(fits) || null;
    if (!nav) return { found: false, candidates: explicit.map(describe) };
    const targets = [
      ...nav.querySelectorAll(
        'a[href], button, [role="button"], [role="link"], [role="tab"], input, select, summary, [tabindex]:not([tabindex="-1"])',
      ),
    ]
      .filter(rendered)
      .map((t) => {
        const r = t.getBoundingClientRect();
        return { label: describe(t), width: Math.round(r.width * 100) / 100, height: Math.round(r.height * 100) / 100 };
      });
    return { found: true, nav: describe(nav), targets };
  };

  // ------------------------------------------------------------------ page checks
  const insideFixedOrSelf = (el) => {
    for (let n = el; n && n !== document.body; n = n.parentElement) {
      if (getComputedStyle(n).position === 'fixed') return true;
    }
    return false;
  };

  /** Content inside an overflow container (scroller, clip) cannot widen the document. */
  const clippedByAncestor = (el) => {
    for (let n = el.parentElement; n && n !== document.body && n !== document.documentElement; n = n.parentElement) {
      if (getComputedStyle(n).overflowX !== 'visible') return true;
    }
    return false;
  };

  /**
   * Elements reaching past the root box (the device-wide initial containing block). Measured
   * against <html>'s box rather than the viewport: once mobile Chrome widens the layout viewport,
   * an RTL page's root box no longer starts at x = 0. Fixed content and content inside overflow
   * containers never widen the document, so they are skipped.
   */
  const scanOverflow = (deviceWidth) => {
    const root = document.documentElement.getBoundingClientRect();
    const left = root.left;
    const right = root.left + Math.min(root.width, deviceWidth);
    const found = [];
    for (const el of document.body ? document.body.querySelectorAll('*') : []) {
      const r = el.getBoundingClientRect();
      if (r.width === 0 && r.height === 0) continue;
      const past = Math.max(r.right - right, left - r.left);
      if (past > 0.5) found.push({ node: el, past: Math.round(past), width: Math.round(r.width) });
    }
    return found
      .filter((c) => !insideFixedOrSelf(c.node) && !clippedByAncestor(c.node))
      .sort((a, b) => b.past - a.past)
      .slice(0, 12)
      .map(({ node, past, width }) => ({ el: describe(node), past, width }));
  };

  // Transient overflow (an intro animation moving content past the edge) is caught per frame:
  // on mobile it widens the layout viewport for good, long after the animation has ended.
  const transient = new Map();
  const watchOverflow = (deviceWidth, ms = 3000) => {
    const start = performance.now();
    let scans = 0;
    const tick = () => {
      const root = document.documentElement;
      if (root && document.body && root.scrollWidth > deviceWidth && scans < 6) {
        scans += 1;
        for (const c of scanOverflow(deviceWidth)) if (!transient.has(c.el)) transient.set(c.el, c);
      }
      if (performance.now() - start < ms) requestAnimationFrame(tick);
    };
    requestAnimationFrame(tick);
  };

  /**
   * Horizontal overflow. `deviceWidth` matters on mobile emulation: content wider than the
   * device makes Chrome widen the layout viewport, so scrollWidth === innerWidth and only the
   * comparison with the device width reveals the problem.
   */
  const overflow = (deviceWidth = innerWidth) => {
    const scrollWidth = document.documentElement.scrollWidth;
    const culprits = scrollWidth > Math.min(innerWidth, deviceWidth) || innerWidth > deviceWidth ? scanOverflow(deviceWidth) : [];
    const now = new Set(culprits.map((c) => c.el));
    return { innerWidth, deviceWidth, scrollWidth, culprits, transient: [...transient.values()].filter((t) => !now.has(t.el)) };
  };

  const ATTRS = ['placeholder', 'aria-label', 'alt', 'title', 'value', 'content'];
  const ATTR_SELECTOR =
    '[placeholder], [aria-label], img[alt], [title], input[type="submit"][value], input[type="button"][value], meta[name="description"], meta[property^="og:"], meta[name^="twitter:"]';

  const missingMarkers = (marker) => {
    const hits = [];
    const visible = document.body ? document.body.innerText : '';
    for (let i = visible.indexOf(marker); i !== -1 && hits.length < 25; i = visible.indexOf(marker, i + 1)) {
      hits.push({ where: 'visible text', text: visible.slice(Math.max(0, i - 50), i + 70).replace(/\s+/g, ' ') });
    }
    if (document.title.includes(marker)) hits.push({ where: '<title>', text: document.title });
    for (const el of document.querySelectorAll(ATTR_SELECTOR)) {
      for (const attr of ATTRS) {
        const v = el.getAttribute(attr);
        if (v && v.includes(marker)) hits.push({ where: `${el.tagName.toLowerCase()}[${attr}]`, text: v.slice(0, 120) });
      }
    }
    const count = (s) => s.split(marker).length - 1;
    // Present in the DOM but not rendered right now (closed accordions, sheets, JSON data).
    const hidden = Math.max(0, count(document.body ? document.body.textContent : '') - count(visible));
    return { hits, hidden };
  };

  const runningAnimations = () =>
    document
      .getAnimations()
      .filter((a) => {
        if (a.playState !== 'running' && !a.pending) return false;
        const timing = a.effect && a.effect.getComputedTiming ? a.effect.getComputedTiming() : null;
        return Boolean(timing) && timing.activeDuration !== 0;
      })
      .map((a) => ({
        target: `${a.effect.target ? describe(a.effect.target) : '?'}${a.effect.pseudoElement || ''}`,
        animation: a.animationName || (a.transitionProperty ? `transition: ${a.transitionProperty}` : a.id || 'WAAPI'),
        timeline: a.timeline === document.timeline ? 'time' : a.timeline ? a.timeline.constructor.name : 'none',
      }));

  const animationsOn = (selector) => {
    const el = document.querySelector(selector);
    if (!el) return [];
    return document
      .getAnimations()
      .filter((a) => a.effect && a.effect.target && composedContains(el, a.effect.target))
      .map((a) => `${describe(a.effect.target)}: ${a.animationName || a.transitionProperty || a.id || 'WAAPI'}`);
  };

  /** First element matching any selector: fully inside the viewport and not covered anywhere. */
  const aboveFold = (selectors) => {
    const el = selectors.map((s) => document.querySelector(s)).find(Boolean);
    if (!el) return { found: false };
    const r = el.getBoundingClientRect();
    const view = visibleArea();
    const inside =
      r.width > 0 && r.top >= view.top && r.left >= view.left && r.bottom <= view.bottom && r.right <= view.right;
    const covered = new Set();
    for (const [fx, fy] of [
      [0.5, 0.5],
      [0.1, 0.1],
      [0.9, 0.1],
      [0.1, 0.9],
      [0.9, 0.9],
    ]) {
      const x = r.left + r.width * fx;
      const y = r.top + r.height * fy;
      if (x < view.left || y < view.top || x > view.right || y > view.bottom) continue;
      const hit = deepElementFromPoint(x, y);
      if (hit && !composedContains(el, hit)) covered.add(describe(hit));
    }
    return {
      found: true,
      label: describe(el),
      top: Math.round(r.top - view.top),
      bottom: Math.round(r.bottom - view.top),
      left: Math.round(r.left - view.left),
      right: Math.round(r.right - view.left),
      viewportWidth: Math.round(view.right - view.left),
      viewportHeight: Math.round(view.bottom - view.top),
      inside,
      covered: [...covered],
    };
  };

  window.__qa = {
    describe,
    aboveFold,
    settleAnimations,
    inspectFocus,
    startFocusLog,
    focusLogState,
    unreachedButtons,
    bottomNav,
    overflow,
    watchOverflow,
    missingMarkers,
    runningAnimations,
    animationsOn,
  };
}
