const { CompositionStage, useComposition, Easing, clamp, useTweaks, TweaksPanel, TweakSection, TweakText, TweakToggle } = window;

const W = 1080, H = 1350;
const NAVY = '#17335E';
const LOGO_TDG_URL = window.BIRTHDAY_LOGO_URL || '/image/logoTDG.png';
const ICON = n => `https://unpkg.com/lucide-static@0.427.0/icons/${n}.svg`;
const lerp = (a, b, p) => a + (b - a) * p;
const prog = (T, s, d, e) => e(clamp((T - s) / d, 0, 1));
const MOTION = {
  enter: (T, s, d = 0.7) => prog(T, s, d, Easing.easeOutCubic),
  move: (T, s, d = 0.9) => prog(T, s, d, Easing.easeInOutCubic),
  fade: (T, s, d = 0.4) => prog(T, s, d, Easing.linear),
};

const COUPON_DEFAULTS = [
  {
    img: 'assets/cupon-viajes.jpg',
    fullImage: true,
    phone: '0424-222-0056',
    whatsappMessage: 'Hola, vengo del cupón de cumpleaños de Tu Doctor en Viajes (15% de descuento en mi próximo plan de asistencia en viajes). Quisiera activarlo o recibir más información.',
  },
  {
    img: 'assets/cupon-farmadoc.jpg',
    fullImage: true,
    phone: '0424-287-8918',
    whatsappMessage: 'Hola, vengo del cupón de cumpleaños Farmadoc (hasta 15% en medicamentos, vitaminas, suplementos y cuidado personal). Quisiera hacer mi pedido o recibir más información.',
  },
  {
    img: 'assets/cupon-kit.jpg',
    fullImage: true,
    phone: '0424-227-1498',
    whatsappMessage: 'Hola, vengo del cupón de cumpleaños de Tu Doctor Group (sorteo del kit corporativo del cumpleañero del mes). Quisiera inscribirme o saber cómo participar.',
  },
  {
    img: 'assets/cupon-casa.jpg',
    fullImage: true,
    phone: '0424-227-1498',
    whatsappMessage: 'Hola, vengo del cupón referidor de Tu Doctor en Casa (10% en planes para familia y amigos). Quisiera regalar salud o recibir más información.',
  },
];

function buildCouponsFromPayload() {
  const payload = window.BIRTHDAY_COUPON_PAYLOAD;
  if (!Array.isArray(payload) || payload.length === 0) {
    return COUPON_DEFAULTS;
  }

  return COUPON_DEFAULTS.map((defaults, index) => {
    const row = payload[index] || {};

    return {
      ...defaults,
      ...row,
      img: row.img || defaults.img,
      phone: row.phone || defaults.phone,
      whatsappMessage: row.whatsappMessage || defaults.whatsappMessage,
      fullImage: true,
    };
  });
}

const COUPONS = buildCouponsFromPayload();

function clearStoredCouponStatus(publicToken) {
  if (!publicToken || typeof window === 'undefined') {
    return;
  }

  try {
    window.localStorage.removeItem(birthdayCouponStorageKey(publicToken));
  } catch (_) {}
}

function reconcileCouponStatusFromServer(coupon) {
  if (!coupon?.publicToken) {
    return;
  }

  const serverStatus = coupon.status ?? null;
  const stored = readStoredCouponStatus(coupon.publicToken);

  if (!isCouponBlockedStatus(serverStatus)) {
    if (isCouponBlockedStatus(stored)) {
      clearStoredCouponStatus(coupon.publicToken);
    }

    return;
  }

  if (isCouponBlockedStatus(stored)) {
    coupon.status = stored;
  }
}

COUPONS.forEach(reconcileCouponStatusFromServer);

const COUPON_AR = 819 / 1024;
const LAYOUT_DESKTOP_MIN = 1024;
const COUPON_HOVER_SCALE = 1.4;
const MOBILE_CAROUSEL_RADIUS = 168;
const MOBILE_CAROUSEL_AUTO_DEG = 0.042;
const ENV = { x: 220, y: 830, w: 640, h: 420 };
const ReactDOM = window.ReactDOM;

const EDGE_STARS = (() => {
  const stars = [];
  let seed = 42091;
  const rnd = () => {
    seed = (seed * 16807) % 2147483647;

    return seed / 2147483647;
  };
  const edges = ['left', 'right', 'top', 'bottom'];

  for (let i = 0; i < 100; i++) {
    stars.push({
      id: i,
      edge: edges[Math.floor(rnd() * edges.length)],
      along: rnd() * 100,
      inset: 48 + rnd() * 96,
      size: 2 + rnd() * 3.5,
      dur: 2.4 + rnd() * 3.6,
      delay: rnd() * 5.5,
    });
  }

  return stars;
})();

function edgeStarStyle(star) {
  const base = {
    width: star.size,
    height: star.size,
    animationDuration: `${star.dur}s`,
    animationDelay: `${star.delay}s`,
  };

  if (star.edge === 'left') {
    return { ...base, left: `${star.inset}px`, top: `${star.along}%`, transform: 'translateY(-50%)' };
  }
  if (star.edge === 'right') {
    return { ...base, right: `${star.inset}px`, top: `${star.along}%`, transform: 'translateY(-50%)' };
  }
  if (star.edge === 'top') {
    return { ...base, top: `${star.inset}px`, left: `${star.along}%`, transform: 'translateX(-50%)' };
  }

  return { ...base, bottom: `${star.inset}px`, left: `${star.along}%`, transform: 'translateX(-50%)' };
}

function EdgeStars() {
  return (
    <div className="birthday-edge-stars" aria-hidden="true">
      {EDGE_STARS.map(star => (
        <span key={star.id} className="birthday-edge-star" style={edgeStarStyle(star)} />
      ))}
    </div>
  );
}

function mobileChromeEnterStyle(progress) {
  const p = clamp(progress, 0, 1);

  return {
    opacity: p,
    transform: `translateY(${lerp(16, 0, p)}px)`,
    visibility: p > 0.02 ? 'visible' : 'hidden',
  };
}

function celebrantDisplayName() {
  const raw = window.TWEAK_DEFAULTS?.nombre;
  if (typeof raw !== 'string') {
    return '';
  }

  return raw.trim();
}

function scriptBirthdayGreetingText() {
  const name = celebrantDisplayName();

  return name ? `Feliz cumpleaños, ${name}` : 'Feliz cumpleaños';
}

function MobileFixedFooter({ className = '', style = {} }) {
  return (
    <footer className={`birthday-mobile-footer ${className}`.trim()} style={style} aria-label="Firma Tu Doctor Group">
      <p className="birthday-mobile-footer-sign">
        <em>Con aprecio,</em>
        <br />
        <strong>Junta Directiva Tu Doctor Group</strong>
      </p>
      <p className="birthday-mobile-footer-url">Tu Doctor Group · tudrgroup.com</p>
    </footer>
  );
}

function MobileFixedHeader({ className = '', style = {} }) {
  return (
    <header className={`birthday-mobile-header ${className}`.trim()} style={style} aria-label="Saludo de cumpleaños">
      <div className="birthday-mobile-header-logo">
        <img src={LOGO_TDG_URL} alt="Tu Doctor Group" />
      </div>
      <div className="birthday-mobile-header-copy">
        <p className="birthday-mobile-header-script">{scriptBirthdayGreetingText()}</p>
        <p className="birthday-mobile-header-headline">¡Hoy es tu día!</p>
        <p className="birthday-mobile-header-subline">
          Hoy celebramos contigo.
          <br />
          Te preparamos cuatro regalos para tu mes.
        </p>
      </div>
    </header>
  );
}

function couponHoverBleed(cw) {
  return Math.ceil(cw * (COUPON_HOVER_SCALE - 1) / 2) + 12;
}

function buildMobileCouponLayout() {
  const cw = 280;
  const ch = Math.round(cw / COUPON_AR);

  return {
    mode: 'carousel',
    stageW: W,
    cw,
    ch,
    grid: [],
    rise: [[-48, -6], [-16, -2], [16, 2], [48, 6]],
  };
}

function buildDesktopCouponLayout() {
  const cw = 464;
  const ch = Math.round(cw / COUPON_AR);
  const gap = 20;
  const count = 4;
  const totalW = count * cw + (count - 1) * gap;
  const bleed = couponHoverBleed(cw);
  const stageW = totalW + bleed * 2;
  const startX = bleed;
  const rowY = 710;
  const grid = Array.from({ length: count }, (_, i) => [
    startX + cw / 2 + i * (cw + gap),
    rowY,
  ]);

  return {
    mode: 'row',
    stageW,
    cw,
    ch,
    grid,
    rise: [[-276, -5], [-92, -2], [92, 2], [276, 5]],
  };
}

function resolveCouponLayout() {
  if (typeof window !== 'undefined' && window.innerWidth >= LAYOUT_DESKTOP_MIN) {
    return buildDesktopCouponLayout();
  }

  return buildMobileCouponLayout();
}

function useCouponLayout() {
  const [layout, setLayout] = React.useState(() => resolveCouponLayout());

  React.useEffect(() => {
    const onResize = () => setLayout(resolveCouponLayout());
    window.addEventListener('resize', onResize);

    return () => window.removeEventListener('resize', onResize);
  }, []);

  return layout;
}

function whatsAppUrl(phone, text) {
  let digits = String(phone || '').replace(/\D/g, '');
  if (digits.startsWith('0')) {
    digits = `58${digits.slice(1)}`;
  } else if (!digits.startsWith('58')) {
    digits = `58${digits}`;
  }
  const query = text ? `?text=${encodeURIComponent(text)}` : '';
  return `https://wa.me/${digits}${query}`;
}

function openCouponWhatsApp(coupon) {
  if (!coupon?.phone) {
    return;
  }
  window.open(whatsAppUrl(coupon.phone, coupon.whatsappMessage || ''), '_blank', 'noopener,noreferrer');
}

function isCouponBlockedStatus(status) {
  return status === 'used' || status === 'expired';
}

function birthdayCouponStorageKey(publicToken) {
  return `birthday_coupon_status_${publicToken}`;
}

function readStoredCouponStatus(publicToken) {
  if (!publicToken || typeof window === 'undefined') {
    return null;
  }

  try {
    return window.localStorage.getItem(birthdayCouponStorageKey(publicToken));
  } catch (_) {
    return null;
  }
}

function effectiveCouponStatus(coupon) {
  if (!coupon?.publicToken) {
    return coupon?.status ?? null;
  }

  const serverStatus = coupon.status ?? null;
  const stored = readStoredCouponStatus(coupon.publicToken);

  if (isCouponBlockedStatus(serverStatus)) {
    return serverStatus;
  }

  if (isCouponBlockedStatus(stored)) {
    return stored;
  }

  return serverStatus;
}

function isCouponInteractionBlocked(coupon) {
  if (!coupon) {
    return true;
  }

  return isCouponBlockedStatus(effectiveCouponStatus(coupon));
}

const CouponClickOnceContext = React.createContext(null);

function CouponClickOnceProvider({ children }) {
  const [blockVersion, setBlockVersion] = React.useState(0);

  const bumpBlocked = React.useCallback(() => {
    setBlockVersion(v => v + 1);
  }, []);

  React.useEffect(() => {
    COUPONS.forEach((coupon) => reconcileCouponStatusFromServer(coupon));
    bumpBlocked();

    const onStorage = (event) => {
      if (!event.key || !event.key.startsWith('birthday_coupon_status_')) {
        return;
      }

      const token = event.key.slice('birthday_coupon_status_'.length);
      COUPONS.forEach((coupon) => {
        if (coupon?.publicToken !== token) {
          return;
        }

        if (isCouponBlockedStatus(event.newValue)) {
          coupon.status = event.newValue;
        } else if (!isCouponBlockedStatus(coupon.status)) {
          reconcileCouponStatusFromServer(coupon);
        }
      });
      bumpBlocked();
    };

    window.addEventListener('storage', onStorage);

    return () => window.removeEventListener('storage', onStorage);
  }, [bumpBlocked]);

  const isCouponUsed = React.useCallback(
    (coupon) => isCouponInteractionBlocked(coupon),
    [blockVersion],
  );

  const tryOpenCouponWhatsApp = React.useCallback((coupon) => {
    if (isCouponInteractionBlocked(coupon)) {
      return false;
    }

    if (!coupon?.phone) {
      return false;
    }

    openCouponWhatsApp(coupon);
    return true;
  }, []);

  const value = React.useMemo(
    () => ({ isCouponUsed, tryOpenCouponWhatsApp }),
    [isCouponUsed, tryOpenCouponWhatsApp],
  );

  return (
    <CouponClickOnceContext.Provider value={value}>
      {children}
    </CouponClickOnceContext.Provider>
  );
}

function useCouponClickOnce() {
  const ctx = React.useContext(CouponClickOnceContext);
  if (!ctx) {
    return {
      isCouponUsed: () => false,
      tryOpenCouponWhatsApp: (coupon) => {
        openCouponWhatsApp(coupon);
        return true;
      },
    };
  }
  return ctx;
}

function mobileCarouselCardSize() {
  if (typeof window === 'undefined') {
    return { cardW: 210, cardH: Math.round(210 / COUPON_AR) };
  }
  const cardW = Math.min(Math.round(window.innerWidth * 0.62), 228);
  return { cardW, cardH: Math.round(cardW / COUPON_AR) };
}

function MobileCouponCarousel3D({ formation }) {
  const { isCouponUsed, tryOpenCouponWhatsApp } = useCouponClickOnce();
  const [rotation, setRotation] = React.useState(0);
  const dragging = React.useRef(false);
  const lastPointerX = React.useRef(0);
  const [{ cardW, cardH }, setCardSize] = React.useState(() => mobileCarouselCardSize());

  React.useEffect(() => {
    const onResize = () => setCardSize(mobileCarouselCardSize());
    window.addEventListener('resize', onResize);
    return () => window.removeEventListener('resize', onResize);
  }, []);

  React.useEffect(() => {
    if (formation < 0.82) {
      return undefined;
    }
    let frameId = 0;
    const step = () => {
      if (!dragging.current) {
        setRotation(prev => prev + MOBILE_CAROUSEL_AUTO_DEG);
      }
      frameId = requestAnimationFrame(step);
    };
    frameId = requestAnimationFrame(step);
    return () => cancelAnimationFrame(frameId);
  }, [formation]);

  const spread = Easing.easeInOutCubic(clamp(formation, 0, 1));
  const count = COUPONS.length;
  const revealScale = lerp(0.86, 1, clamp((formation - 0.08) / 0.45, 0, 1));
  const angleStep = 360 / count;

  const bindDrag = (clientX) => {
    if (!dragging.current) {
      return;
    }
    const dx = clientX - lastPointerX.current;
    lastPointerX.current = clientX;
    setRotation(prev => prev + dx * 0.38);
  };

  return (
    <div
      className="birthday-carousel-host"
      style={{ visibility: formation > 0.08 ? 'visible' : 'hidden', pointerEvents: formation > 0.45 ? 'auto' : 'none' }}
      onPointerDown={(e) => {
        dragging.current = true;
        lastPointerX.current = e.clientX;
        e.currentTarget.setPointerCapture(e.pointerId);
      }}
      onPointerMove={(e) => bindDrag(e.clientX)}
      onPointerUp={() => { dragging.current = false; }}
      onPointerCancel={() => { dragging.current = false; }}
      onTouchStart={(e) => {
        dragging.current = true;
        lastPointerX.current = e.touches[0].clientX;
      }}
      onTouchMove={(e) => {
        e.preventDefault();
        bindDrag(e.touches[0].clientX);
      }}
      onTouchEnd={() => { dragging.current = false; }}
    >
      <div className="birthday-carousel-viewport">
        <div
          className="birthday-carousel-ring"
          style={{ transform: `rotateY(${rotation}deg) scale(${revealScale})` }}
        >
          {COUPONS.map((c, i) => {
            const angle = angleStep * i * spread;
            const depth = lerp(6, MOBILE_CAROUSEL_RADIUS, spread);
            const facing = Math.cos(((rotation + angle) * Math.PI) / 180);
            const depthScale = 0.9 + Math.max(facing, 0) * 0.12;
            const used = isCouponUsed(c);
            return (
              <div
                key={c.img}
                className={`birthday-carousel-item${used ? ' birthday-carousel-item--used' : ''}`}
                style={{
                  width: cardW,
                  height: cardH,
                  marginLeft: -cardW / 2,
                  marginTop: -cardH / 2,
                  transform: `rotateY(${angle}deg) translateZ(${depth}px) scale(${depthScale * spread})`,
                  zIndex: Math.round(100 + facing * 50),
                }}
                onClick={(e) => {
                  if (used) {
                    e.preventDefault();
                    e.stopPropagation();
                    return;
                  }
                  tryOpenCouponWhatsApp(c);
                }}
                onKeyDown={(e) => {
                  if (used) {
                    return;
                  }
                  if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    tryOpenCouponWhatsApp(c);
                  }
                }}
                role="button"
                tabIndex={used ? -1 : 0}
                aria-disabled={used}
                aria-label={
                  used
                    ? `Cupón ya utilizado: ${c.phone}`
                    : (c.whatsappMessage ? `Contactar por WhatsApp: ${c.phone}` : 'Cupón')
                }
              >
                <div className="birthday-carousel-item-inner" style={{ width: cardW, height: cardH }}>
                  <div className="birthday-carousel-face birthday-carousel-face--front">
                    <img src={c.img} alt="" />
                  </div>
                  <div className="birthday-carousel-face birthday-carousel-face--back">
                    <img src={c.img} alt="" />
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      </div>
    </div>
  );
}

function MobileCarouselLayer() {
  const { T, CUES: C } = useComposition();
  const formation = MOTION.move(T, C.Cupones + 0.12, 1.3);

  if (T < C.Abrir + 0.4 || typeof document === 'undefined' || !ReactDOM?.createPortal) {
    return null;
  }

  return ReactDOM.createPortal(
    <MobileCouponCarousel3D formation={formation} />,
    document.body,
  );
}

function MobileFixedChromeLayer() {
  const { T, CUES: C } = useComposition();
  const headerIn = MOTION.enter(T, C.Saludo + 0.05, 0.95);
  const footerIn = MOTION.enter(T, C.Cierre, 0.95);
  const headerShown = headerIn > 0.02;
  const footerShown = footerIn > 0.02;

  if (typeof document === 'undefined' || !ReactDOM?.createPortal || (!headerShown && !footerShown)) {
    return null;
  }

  return ReactDOM.createPortal(
    <>
      {headerShown ? (
        <MobileFixedHeader style={mobileChromeEnterStyle(headerIn)} />
      ) : null}
      {footerShown ? (
        <MobileFixedFooter style={mobileChromeEnterStyle(footerIn)} />
      ) : null}
    </>,
    document.body,
  );
}

function Coupon({ c, cardW, cardH, style }) {
  const { isCouponUsed, tryOpenCouponWhatsApp } = useCouponClickOnce();
  const [hover, setHover] = React.useState(false);
  const used = isCouponUsed(c);
  const { transform, left, top, opacity, zIndex, ...restStyle } = style;
  const hoverScale = !used && hover ? COUPON_HOVER_SCALE : 1;
  const shellStyle = {
    position: 'absolute',
    left,
    top,
    opacity: used ? opacity * 0.72 : opacity,
    zIndex: hover ? 30 : zIndex,
    width: cardW,
    height: cardH,
    cursor: used ? 'default' : 'pointer',
    ...restStyle,
  };
  const motionStyle = {
    width: '100%',
    height: '100%',
    transform: `${transform || ''} scale(${hoverScale})`.trim(),
    transformOrigin: 'center center',
    transition: 'transform 0.28s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.28s ease, opacity 0.25s ease',
    filter: used ? 'grayscale(0.35)' : 'none',
  };
  const hoverHandlers = {
    onMouseEnter: () => {
      if (!used) {
        setHover(true);
      }
    },
    onMouseLeave: () => setHover(false),
    onClick: () => {
      if (!used) {
        tryOpenCouponWhatsApp(c);
      }
    },
    onKeyDown: (e) => {
      if (used) {
        return;
      }
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        tryOpenCouponWhatsApp(c);
      }
    },
    role: 'button',
    tabIndex: !used && opacity > 0.05 ? 0 : -1,
    'aria-disabled': used,
    'aria-label': used
      ? `Cupón ya utilizado: ${c.phone}`
      : (c.whatsappMessage ? `Contactar por WhatsApp: ${c.phone}` : 'Cupón'),
  };

  if (c.fullImage) {
    return (
      <div {...hoverHandlers} style={shellStyle}>
        <div style={{ ...motionStyle, borderRadius: 20, overflow: 'hidden', boxShadow: hover ? '0 22px 48px rgba(15,34,51,0.38)' : '0 12px 32px rgba(15,34,51,0.22)', background: NAVY }}>
          <img src={c.img} alt="" style={{ width: '100%', height: '100%', objectFit: 'contain', objectPosition: 'center center', display: 'block' }} />
        </div>
      </div>
    );
  }

  const notch = { position: 'absolute', top: 186, width: 30, height: 30, borderRadius: '50%', background: NAVY };
  return (
    <div {...hoverHandlers} style={shellStyle}>
    <div style={{ ...motionStyle, display: 'flex', flexDirection: 'column', background: '#fff', borderRadius: 24, overflow: 'hidden', boxShadow: hover ? '0 22px 48px rgba(15,34,51,0.38)' : '0 12px 32px rgba(15,34,51,0.22)', fontFamily: "Helvetica,'Helvetica Neue',Arial,sans-serif" }}>
      <div style={{ position: 'relative', height: 200, flex: '0 0 auto' }}>
        <img src={c.img} alt="" style={{ position: 'absolute', inset: 0, width: '100%', height: '100%', objectFit: 'cover' }} />
        {c.overlay}
      </div>
      <div style={{ position: 'absolute', left: 28, right: 28, top: 199, borderTop: '3px dashed #D7DBDE' }} />
      <div style={{ ...notch, left: -15 }} /><div style={{ ...notch, right: -15 }} />
      <div style={{ flex: 1, display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 14, padding: '4px 24px 0' }}>
        <div style={{ minWidth: 0 }}>
          <div style={{ fontSize: 19, fontWeight: 700, lineHeight: 1.2, color: '#12293A' }}>{c.title}</div>
          <div style={{ fontFamily: 'Verdana,Tahoma,sans-serif', fontSize: 14, lineHeight: 1.4, color: '#64748B', marginTop: 4 }}>{c.detail}</div>
        </div>
        <div style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 19, fontWeight: 700, color: '#12293A', whiteSpace: 'nowrap' }}>
          <img src={ICON('phone')} style={{ width: 19 }} />{c.phone}
        </div>
      </div>
    </div>
    </div>
  );
}

function Piece({ layout }) {
  const { T, CUES: C, authoredTotal } = useComposition();
  const { cw, ch, grid, rise: riseOffsets, stageW } = layout;
  const env = { x: (stageW - ENV.w) / 2, y: ENV.y, w: ENV.w, h: ENV.h };
  const out = 1;

  // Envelope
  const envIn = MOTION.enter(T, 0.3, 1.2);
  const envDrop = MOTION.move(T, C.Cupones + 0.15, 0.9);
  const envY = lerp(560, 0, envIn) + lerp(0, 720, envDrop) + Math.sin(T * 1.6) * 4 * (1 - envDrop);
  const envOp = envIn * (1 - MOTION.fade(T, C.Cupones + 0.5, 0.5));
  const flap = MOTION.move(T, C.Abrir, 0.9) * 180;

  // Greeting
  const g = [0, 0.4, 0.9].map(d => MOTION.enter(T, C.Saludo + d, 0.8));
  const push = 1 + 0.02 * MOTION.move(T, C.Cierre, 3.5);

  // Footer
  const f = MOTION.enter(T, C.Cierre, 0.8);

  const cardsSettled = MOTION.move(T, C.Cupones + 0.85, 1.0);
  const carouselFormation = MOTION.move(T, C.Cupones + 0.12, 1.3);
  const isMobileLayout = layout.mode !== 'row';
  const headerRowTop = lerp(186, 142, cardsSettled);
  const titleHeadlineTop = lerp(290, 218, cardsSettled);
  const sublineTop = titleHeadlineTop + 118;
  const midTextHide = 1 - cardsSettled;

  const mobileStackY = 690;
  const envelopeBottomY = env.y + envY + env.h - 6;
  const desktopMaxLand = COUPONS.reduce(
    (max, _, i) => Math.max(max, MOTION.move(T, C.Cupones + 0.35 + i * 0.14, 0.95)),
    0,
  );
  const mobileMaxGather = COUPONS.reduce(
    (max, _, i) => Math.max(max, MOTION.move(T, C.Cupones + 0.02, 0.75)),
    0,
  );
  const desktopEnvelopeMaskActive = !isMobileLayout && envOp > 0.01 && desktopMaxLand < 0.42;
  const mobileEnvelopeMaskActive = isMobileLayout && envOp > 0.01 && mobileMaxGather < 0.2 && carouselFormation < 0.15;

  function couponClipInsideEnvelope(cardTop, cardHeight, stillInsideEnvelope) {
    if (!stillInsideEnvelope) {
      return undefined;
    }

    const clipBottomPx = Math.max(0, cardTop + cardHeight - envelopeBottomY);
    if (clipBottomPx <= 0) {
      return undefined;
    }

    return `inset(0px 0px ${clipBottomPx}px 0px)`;
  }

  const coupons = COUPONS.map((c, i) => {
    if (isMobileLayout) {
      const riseT = MOTION.move(T, C.Abrir + 0.65 + i * 0.11, 1.0);
      const gather = MOTION.move(T, C.Cupones + 0.02, 0.75);
      const cx0 = env.x + env.w / 2;
      const cyInside = env.y + env.h * 0.64;
      const cyExit = env.y + env.h * 0.24 - (i % 2) * 6;
      const cx = lerp(lerp(cx0, cx0 + riseOffsets[i][0] * 0.28, riseT), stageW / 2, gather);
      const cy = lerp(lerp(cyInside, cyExit, riseT), mobileStackY, gather);
      const rot = lerp(riseOffsets[i][1] * 0.6, 0, gather);
      const scale = lerp(0.9, 0.88, carouselFormation);
      const cardTop = cy - ch / 2 + envY * (1 - riseT);
      const scaledH = ch * scale;
      const stillInsideEnvelope = gather < 0.18 && carouselFormation < 0.12;
      return (
        <Coupon key={i} c={c} cardW={cw} cardH={ch} style={{
          left: cx - cw / 2,
          top: cardTop,
          transform: `rotate(${rot}deg) scale(${scale})`,
          opacity: out * (T < C.Abrir + 0.45 ? 0 : 1) * (1 - carouselFormation),
          zIndex: stillInsideEnvelope ? 2 : 4,
          clipPath: couponClipInsideEnvelope(cardTop, scaledH, stillInsideEnvelope),
          overflow: stillInsideEnvelope ? 'hidden' : undefined,
        }} />
      );
    }

    const riseT = MOTION.move(T, C.Abrir + 0.7 + i * 0.12, 1.0);
    const land = MOTION.move(T, C.Cupones + 0.35 + i * 0.14, 0.95);
    const cx0 = env.x + env.w / 2, cy0 = env.y + env.h / 2 + 30;
    const rx = cx0 + riseOffsets[i][0], ry = env.y - 130 + (i % 2) * 14;
    let cx = lerp(cx0, rx, riseT), cy = lerp(cy0, ry, riseT);
    cx = lerp(cx, grid[i][0], land); cy = lerp(cy, grid[i][1], land);
    const rot = lerp(lerp(0, riseOffsets[i][1], riseT), 0, land);
    const hl = C.Cupones + 1.7 + i * 0.55;
    const pulse = Math.sin(Math.PI * clamp((T - hl) / 0.6, 0, 1));
    const s = lerp(0.8, 1, land) * (1 + 0.035 * pulse);
    const cardTop = cy - ch / 2 + envY * (1 - land);
    const scaledH = ch * s;
    return (
      <Coupon key={i} c={c} cardW={cw} cardH={ch} style={{
        left: cx - cw / 2,
        top: cardTop,
        transform: `rotate(${rot}deg) scale(${s})`,
        opacity: out * (T < C.Abrir + 0.5 ? 0 : 1),
        zIndex: land > 0.42 ? 6 : 2,
        clipPath: couponClipInsideEnvelope(cardTop, scaledH, land <= 0.42),
        overflow: land <= 0.42 ? 'hidden' : undefined,
      }} />
    );
  });

  return (
    <div style={{ position: 'absolute', inset: 0, background: NAVY, overflow: 'visible', fontFamily: "Helvetica,'Helvetica Neue',Arial,sans-serif" }}>
      <div style={{ position: 'absolute', inset: 0, transform: `scale(${push})`, transformOrigin: '50% 40%' }}>
        {!isMobileLayout ? (
          <>
            <div
              style={{
                position: 'absolute',
                left: 80,
                right: 80,
                top: headerRowTop,
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'space-between',
                gap: 32,
                opacity: g[0] * out,
                transform: `translateY(${lerp(16, 0, g[0])}px)`,
                pointerEvents: 'none',
              }}
            >
              <div
                style={{
                  fontFamily: "'Dancing Script',cursive",
                  fontWeight: 700,
                  fontSize: 96,
                  lineHeight: 1,
                  color: '#fff',
                  flex: '0 1 auto',
                }}
              >
                {scriptBirthdayGreetingText()}
              </div>
              <img
                src={LOGO_TDG_URL}
                alt="Tu Doctor Group"
                style={{
                  height: 92,
                  width: 'auto',
                  maxWidth: 'min(42%, 420px)',
                  objectFit: 'contain',
                  objectPosition: 'right center',
                  flex: '0 0 auto',
                }}
              />
            </div>
            <div style={{ position: 'absolute', left: 80, right: 80, top: titleHeadlineTop, opacity: g[1] * out, transform: `translateY(${lerp(16, 0, g[1])}px)`, fontSize: 104, fontWeight: 700, letterSpacing: '-0.03em', lineHeight: 1.05, color: '#fff' }}>¡Hoy es tu día!</div>
          </>
        ) : null}
        {!isMobileLayout ? (
          <>
            <div style={{ position: 'absolute', left: 80, right: 80, top: sublineTop, opacity: g[2] * out * midTextHide * (1 - MOTION.fade(T, C.Cierre, 0.4)), transform: `translateY(${lerp(12, 0, g[2])}px)`, fontFamily: 'Verdana,Tahoma,sans-serif', fontSize: 28, lineHeight: 1.45, color: '#D7DBDE' }}>Hoy celebramos contigo.<br />Te preparamos cuatro regalos para tu mes.</div>
            <div style={{ position: 'absolute', left: 80, right: 80, top: sublineTop, opacity: MOTION.fade(T, C.Cierre + 0.3, 0.5) * out * midTextHide, fontFamily: 'Verdana,Tahoma,sans-serif', fontSize: 28, lineHeight: 1.45, color: '#FFFFFF', fontWeight: 700 }}>Tus cupones van adjuntos en este correo.</div>
          </>
        ) : null}

        {/* Envelope back */}
        <div style={{ position: 'absolute', left: env.x, top: env.y + envY, width: env.w, height: env.h, background: '#D7DBDE', borderRadius: 18, opacity: envOp, zIndex: 1 }} />
        {/* Flap */}
        <div style={{ position: 'absolute', left: env.x, top: env.y + envY, width: env.w, height: 260, perspective: 1400, opacity: envOp, zIndex: flap > 90 ? 0 : 5 }}>
          <div style={{ position: 'absolute', inset: 0, transformOrigin: '50% 0', transform: `rotateX(${flap}deg)`, background: flap > 90 ? '#C5CCD3' : '#F6F6F6', clipPath: 'polygon(0 0,100% 0,50% 100%)' }} />
        </div>
        {coupons}
        {/* Envelope front pocket */}
        <div style={{ position: 'absolute', left: env.x, top: env.y + envY, width: env.w, height: env.h, background: '#FFFFFF', borderRadius: 18, clipPath: 'polygon(0 0,50% 58%,100% 0,100% 100%,0 100%)', opacity: envOp, zIndex: 3, boxShadow: '0 20px 40px rgba(15,34,51,0.3)' }} />
        {desktopEnvelopeMaskActive || mobileEnvelopeMaskActive ? (
          <div
            aria-hidden
            style={{
              position: 'absolute',
              left: env.x - 6,
              top: env.y + envY + env.h - 10,
              width: env.w + 12,
              height: 72,
              background: NAVY,
              zIndex: 5,
              pointerEvents: 'none',
            }}
          />
        ) : null}
        <div style={{ position: 'absolute', left: env.x + env.w / 2 - 34, top: env.y + envY + env.h * 0.58 - 34, width: 68, height: 68, borderRadius: '50%', background: '#26B4E8', display: 'flex', alignItems: 'center', justifyContent: 'center', opacity: envOp * (1 - MOTION.fade(T, C.Abrir - 0.1, 0.3)), zIndex: 6 }}>
          <img src={ICON('gift')} style={{ width: 34, filter: 'brightness(0) invert(1)' }} />
        </div>

        {!isMobileLayout ? (
          <>
            <div style={{ position: 'absolute', left: 80, right: 80, top: 1196, display: 'flex', alignItems: 'flex-end', justifyContent: 'space-between', opacity: f * out, transform: `translateY(${lerp(14, 0, f)}px)` }}>
              <div style={{ fontSize: 22, lineHeight: 1.35, color: '#fff' }}>
                <span style={{ fontFamily: "'Dancing Script',cursive", fontSize: 34, fontWeight: 700 }}>Con aprecio,</span><br /><b>Junta Directiva Tu Doctor Group</b>
              </div>
              <div style={{ display: 'flex', alignItems: 'center', gap: 36 }}>
                <img src="assets/group-negativo.svg" alt="" style={{ height: 44, filter: 'brightness(0) invert(1)' }} />
                <img src="assets/group-negativo.svg" alt="" style={{ height: 44, filter: 'brightness(0) invert(1)', opacity: 0.85 }} />
              </div>
            </div>
            <div style={{ position: 'absolute', left: 80, right: 80, bottom: 26, opacity: f * out, fontFamily: 'Verdana,Tahoma,sans-serif', fontSize: 18, color: '#D7DBDE' }}>Tu Doctor Group · tudrgroup.com</div>
          </>
        ) : null}
      </div>
    </div>
  );
}

const FALLBACK_SCENES = '[{"name":"Intro","dur":2,"desc":"El sobre sube al centro"},{"name":"Saludo","dur":3,"desc":"Aparece el saludo"},{"name":"Abrir","dur":2.5,"desc":"Se abre el sobre y asoman los cupones"},{"name":"Cupones","dur":4.5,"desc":"Los cupones se ordenan en la cuadrícula"},{"name":"Cierre","dur":3.5,"desc":"Aviso de cupones adjuntos, firma y logos"}]';

function BirthdayAnimation() {
  const showTweaks = window.BIRTHDAY_SHOW_TWEAKS === true;
  const [t, setTweak] = useTweaks(window.TWEAK_DEFAULTS || { motionEditor: false, nombre: 'María' });
  const layout = useCouponLayout();

  return (
    <>
      <EdgeStars />
      <CouponClickOnceProvider>
        <CompositionStage width={layout.stageW} height={H} scenes={window.OM_SCENES || FALLBACK_SCENES} playback={window.OM_PLAYBACK || '{"mode":"times","count":1}'} bg={NAVY}>
          <>
            <Piece layout={layout} />
            {layout.mode !== 'row' ? (
              <>
                <MobileFixedChromeLayer />
                <MobileCarouselLayer />
              </>
            ) : null}
          </>
        </CompositionStage>
      </CouponClickOnceProvider>
      {showTweaks ? (
        <TweaksPanel>
          <TweakSection label="Editor" />
          <TweakToggle label="Motion editor" value={t.motionEditor} onChange={v => setTweak('motionEditor', v)} />
        </TweaksPanel>
      ) : null}
    </>
  );
}

window.BirthdayAnimation = BirthdayAnimation;
