/* CL!CK THE CIRCLE — Multi-device gameplay (online via WebSocket).
 *
 * Arsitektur:
 *  - Setiap user yang duduk di seat 1..4 berperan sebagai 1 lingkaran berwarna.
 *  - Owner room = authoritative simulator. Owner menjalankan loop fisika,
 *    mendeteksi tabrakan dengan kotak, mengelola turbo & skor, lalu
 *    broadcast snapshot lewat WebSocket (game_state).
 *  - Non-owner mengirim input lokal (game_input) ~20x/detik. Owner menerima
 *    input itu dan memetakan ke seat user pengirim.
 *  - Bot disimulasikan oleh owner dengan AI sederhana (kejar kotak terdekat).
 *
 * Kontrol seragam (1 device = 1 pemain):
 *   WASD untuk gerak, Spasi untuk turbo. Tidak ada Enter/IJKL/Numpad lagi.
 */
(() => {
  const canvas = document.getElementById('game-canvas');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');

  const ui = {
    diffSel:   document.getElementById('game-difficulty'),
    durSel:    document.getElementById('game-duration'),
    bgmSel:    document.getElementById('game-bgm'),
    sfxOn:     document.getElementById('game-sfx-on'),
    startBtn:  document.getElementById('game-start'),
    resetBtn:  document.getElementById('game-reset'),
    timer:     document.getElementById('game-timer'),
    scoreList: document.getElementById('game-scores'),
    status:    document.getElementById('game-status'),
    touchWrap: document.getElementById('touch-controls'),
  };

  const RC = window.__ROOM_CONFIG__ || {};
  const isOwner = !!RC.isOwner;

  const SEAT_COLORS = ['#d83a3a', '#2ecc40', '#ffd23f', '#3aa0ff'];

  // Box size disamakan & diperbesar untuk semua difficulty (lebih jelas dilihat).
  const BOX_SIZE = 32;

  // Per-difficulty config.
  //   playerSteal: kalau true, tabrakan antar pemain (turbo aktif vs tanpa turbo)
  //                membuat penyerang mencuri 1 poin dari korban (cooldown 800ms).
  const DIFF = {
    normal: {
      arenaW: 900, arenaH: 520, boxSize: BOX_SIZE,
      baseSpeed: 2.4, turboMul: 1.9, turboMax: 100, turboDrain: 55, turboRegen: 22,
      playerSteal: false,
    },
    hard: {
      arenaW: 700, arenaH: 420, boxSize: BOX_SIZE,
      baseSpeed: 2.8, turboMul: 2.1, turboMax: 100, turboDrain: 70, turboRegen: 16,
      playerSteal: true,
      // Bonus lebih langka tapi lebih besar (+5), trap (penalty/freeze/slow)
      // ~40% lebih sering. Total weight tetap ~1.0.
      boxOverrides: {
        normal:  { weight: 0.42 },
        bonus:   { weight: 0.06, score: 5, label: 'Bonus +5' },
        penalty: { weight: 0.14 },
        freeze:  { weight: 0.11 },
        slow:    { weight: 0.11 },
        boost:   { weight: 0.07 },
      },
    },
  };

  // ---------- Box types (power-ups & traps) ----------
  // Probabilitas spawn (harus total ~1.0). Tweak sesuai selera.
  const BOX_TYPES = [
    { kind: 'normal',  color: '#ffd23f', weight: 0.55, score:  1, label: 'Normal +1' },
    { kind: 'bonus',   color: '#9b59ff', weight: 0.12, score:  3, label: 'Bonus +3' },
    { kind: 'penalty', color: '#e74c3c', weight: 0.10, score: -2, label: 'Penalti -2' },
    { kind: 'freeze',  color: '#7fdbff', weight: 0.08, score:  0, label: 'Freeze 1.5s', freezeMs: 1500 },
    { kind: 'slow',    color: '#a0a0a0', weight: 0.08, score:  0, label: 'Slow 3s',     slowMs: 3000, slowMul: 0.55 },
    { kind: 'boost',   color: '#2ecc40', weight: 0.07, score:  0, label: 'Turbo Boost', turboFill: 100, boostMs: 2500 },
  ];
  // Gabungkan BOX_TYPES dengan override per-difficulty (bila ada).
  function effectiveBoxTypes(diffKey) {
    const ov = (DIFF[diffKey] && DIFF[diffKey].boxOverrides) || null;
    if (!ov) return BOX_TYPES;
    return BOX_TYPES.map(t => ov[t.kind] ? { ...t, ...ov[t.kind] } : t);
  }
  function pickBoxKind(diffKey) {
    const list = effectiveBoxTypes(diffKey || (state && state.diff) || 'normal');
    const total = list.reduce((a, t) => a + t.weight, 0);
    let r = Math.random() * total;
    for (const t of list) { r -= t.weight; if (r <= 0) return t; }
    return list[0];
  }
  function boxTypeOf(kind, diffKey) {
    const list = effectiveBoxTypes(diffKey || (state && state.diff) || 'normal');
    return list.find(t => t.kind === kind) || list[0];
  }

  const DURATION_DEFAULT = 90;
  let gameDuration = DURATION_DEFAULT;

  // ---------- State (shared shape antara owner & non-owner) ----------
  const state = {
    running: false,
    diff: 'normal',
    timeLeft: DURATION_DEFAULT,
    players: [],   // [{seat, name, color, isBot, x, y, r, score, turbo, turboActive}]
    boxes: [],     // [{x,y,size}]
  };

  // Input lokal (yang dikirim ke owner / dipakai owner sendiri)
  const localInput = { up: false, down: false, left: false, right: false, turbo: false };

  // Hanya dipakai owner: input dari user remote, key = userId
  const remoteInputs = new Map();

  // ---------- Helpers ----------
  function boxesNeeded(n) { var b = 3 + (n|0); if (b < 3) b = 3; if (b > 8) b = 8; return b; }
  function rand(a, b) { return Math.random() * (b - a) + a; }
  const BOX_LIFETIME_MS = 5000;
  const BOX_WARN_MS     = 1500;
  function spawnBox(cfg, opts) {
    const pad = cfg.boxSize + 10;
    let t;
    if (opts && opts.kind === 'normal') {
      t = boxTypeOf('normal', state.diff);
    } else {
      t = pickBoxKind(state.diff);
    }
    return {
      x: rand(pad, cfg.arenaW - pad),
      y: rand(pad, cfg.arenaH - pad),
      size: cfg.boxSize,
      kind: t.kind,
      color: t.color,
      bornAt: performance.now(),
    };
  }
  // Pastikan minimal selalu ada satu kotak '+1' (normal) di arena.
  function ensureNormalInvariant(cfg) {
    if (!state.boxes.some(b => b.kind === 'normal')) {
      // ganti box paling tua dengan normal supaya jumlah tetap.
      let oldestIdx = 0, oldestAge = -1;
      for (let i = 0; i < state.boxes.length; i++) {
        const age = performance.now() - (state.boxes[i].bornAt || 0);
        if (age > oldestAge) { oldestAge = age; oldestIdx = i; }
      }
      if (state.boxes.length === 0) state.boxes.push(spawnBox(cfg, { kind:'normal' }));
      else {
        // reset target AI yang mengarah ke slot ini
        for (const other of state.players) {
          if (other.ai && other.ai.targetIdx === oldestIdx) { other.ai.targetIdx = -1; other.ai.retargetAt = 0; }
        }
        state.boxes[oldestIdx] = spawnBox(cfg, { kind:'normal' });
      }
    }
  }
  // Hapus box yang sudah expired (>5 detik) dan respawn baru.
  function expireOldBoxes(cfg) {
    const now = performance.now();
    for (let i = 0; i < state.boxes.length; i++) {
      const b = state.boxes[i];
      const age = now - (b.bornAt || now);
      if (age >= BOX_LIFETIME_MS) {
        for (const other of state.players) {
          if (other.ai && other.ai.targetIdx === i) { other.ai.targetIdx = -1; other.ai.retargetAt = 0; }
        }
        state.boxes[i] = spawnBox(cfg);
      }
    }
    ensureNormalInvariant(cfg);
  }

  function sendWS(obj) {
    const ws = window.__ROOM_WS__;
    if (ws && ws.readyState === 1) ws.send(JSON.stringify(obj));
  }

  // ---------- Owner: bangun pemain dari seat state ----------
  function buildPlayersFromSeats() {
    const seats = (window.__SEAT_STATE__ && window.__SEAT_STATE__.seats) || [];
    const cfg = DIFF[state.diff];
    const positions = [
      { x: 60,                 y: 60 },
      { x: cfg.arenaW - 60,    y: cfg.arenaH - 60 },
      { x: cfg.arenaW - 60,    y: 60 },
      { x: 60,                 y: cfg.arenaH - 60 },
    ];
    const players = [];
    seats.forEach(s => {
      const occupied = !!(s.user_id || s.is_bot);
      if (!occupied) return;
      players.push({
        seat: s.seat_no,
        userId: s.user_id || 0,
        isBot: !!s.is_bot,
        name: s.user_id ? s.user_name : (s.bot_label || ('BOT' + s.seat_no)),
        color: SEAT_COLORS[s.seat_no - 1],
        x: positions[s.seat_no - 1].x,
        y: positions[s.seat_no - 1].y,
        r: 16,
        score: 0,
        turbo: cfg.turboMax,
        turboActive: false,
        // status efek
        frozenUntil: 0,
        slowUntil: 0,
        slowMul: 1,
        boostUntil: 0,
        // AI bot fields (diisi nanti khusus bot)
        ai: null,
      });
    });
    return players;
  }

  // ---------- Owner simulator ----------
  let lastTs = 0;
  let elapsedAcc = 0;
  let snapAcc = 0;
  const SNAP_INTERVAL = 1 / 30;  // 30 Hz snapshot
  const INTERP_DELAY  = 0.10;    // render 100 ms di belakang snapshot terbaru -> mulus

  // ---------- Render buffer (dipakai owner & non-owner SAMA) ----------
  // Tiap snapshot disimpan dengan timestamp lokal saat tiba.
  // Render loop menginterpolasi 2 snapshot yang mengapit (now - INTERP_DELAY).
  const snapBuf = [];                   // [{t, players:[{seat,x,y,...}], boxes, timeLeft, arenaW, arenaH, diff}]
  const renderState = {                 // hasil interpolasi yang DIGAMBAR
    players: [],
    boxes: [],
    timeLeft: 0,
    arenaW: 900,
    arenaH: 520,
    diff: 'normal',
  };
  function pushSnapshot(snap) {
    snap.t = performance.now() / 1000;
    snapBuf.push(snap);
    // Buang snapshot terlalu lama (> 1 detik di belakang)
    const cutoff = snap.t - 1.0;
    while (snapBuf.length > 2 && snapBuf[0].t < cutoff) snapBuf.shift();
  }
  function lerp(a, b, t) { return a + (b - a) * t; }
  function sampleRenderState() {
    if (snapBuf.length === 0) return;
    const renderTime = performance.now() / 1000 - INTERP_DELAY;
    let a = snapBuf[0], b = snapBuf[snapBuf.length - 1];
    // Cari pasangan a,b yang mengapit renderTime
    for (let i = 0; i < snapBuf.length - 1; i++) {
      if (snapBuf[i].t <= renderTime && snapBuf[i+1].t >= renderTime) {
        a = snapBuf[i]; b = snapBuf[i+1]; break;
      }
    }
    // Kalau renderTime > snapshot terakhir, extrapolasi ringan dari (prev, last)
    if (renderTime > b.t && snapBuf.length >= 2) {
      a = snapBuf[snapBuf.length - 2];
      b = snapBuf[snapBuf.length - 1];
    }
    const span = Math.max(0.001, b.t - a.t);
    let alpha = (renderTime - a.t) / span;
    if (alpha < 0) alpha = 0;
    if (alpha > 1.5) alpha = 1.5; // batas extrapolasi
    // Index by seat untuk match player
    const aMap = new Map(a.players.map(p => [p.seat, p]));
    const out = [];
    for (const pb of b.players) {
      const pa = aMap.get(pb.seat) || pb;
      out.push({
        seat: pb.seat,
        name: pb.name, color: pb.color,
        x: lerp(pa.x, pb.x, alpha),
        y: lerp(pa.y, pb.y, alpha),
        r: pb.r,
        score: pb.score,
        turbo: pb.turbo,
        turboActive: pb.turboActive,
      });
    }
    renderState.players = out;
    renderState.boxes   = b.boxes || [];
    renderState.timeLeft = b.timeLeft;
    renderState.arenaW = b.arenaW || renderState.arenaW;
    renderState.arenaH = b.arenaH || renderState.arenaH;
    renderState.diff   = b.diff   || renderState.diff;
    if (canvas.width !== renderState.arenaW || canvas.height !== renderState.arenaH) {
      canvas.width = renderState.arenaW; canvas.height = renderState.arenaH;
    }
  }

  // ---------- Client-side prediction (untuk semua pemain lokal: owner & non-owner) ----------
  // Disimpan terpisah supaya pemain SENDIRI terasa instan, lalu dikoreksi
  // halus saat snapshot otoritatif tiba.
  const localPred = { active: false, seat: 0, x: 0, y: 0, r: 16, lastTs: 0 };
  function initPredFromSnapshot() {
    if (snapBuf.length === 0) return;
    const last = snapBuf[snapBuf.length - 1];
    const me = last.players.find(p => p.seat === mySeatNo());
    if (!me) { localPred.active = false; return; }
    if (!localPred.active) {
      localPred.x = me.x; localPred.y = me.y;
    }
    localPred.r = me.r || 16;
    localPred.seat = me.seat;
    localPred.active = true;
  }
  function mySeatNo() {
    // Cari seat saya dari __SEAT_STATE__
    const seats = (window.__SEAT_STATE__ && window.__SEAT_STATE__.seats) || [];
    const mine = seats.find(s => Number(s.user_id) === Number(RC.myId));
    return mine ? mine.seat_no : 0;
  }
  function predictTick(dt) {
    if (!localPred.active) return;
    const cfg = DIFF[renderState.diff] || DIFF.normal;
    let dx = 0, dy = 0;
    if (localInput.up)    dy -= 1;
    if (localInput.down)  dy += 1;
    if (localInput.left)  dx -= 1;
    if (localInput.right) dx += 1;
    if (dx && dy) { dx *= 0.7071; dy *= 0.7071; }
    const turboMul = localInput.turbo ? cfg.turboMul : 1;
    const speed = cfg.baseSpeed * turboMul * 60;
    localPred.x += dx * speed * dt;
    localPred.y += dy * speed * dt;
    if (localPred.x < localPred.r) localPred.x = localPred.r;
    if (localPred.y < localPred.r) localPred.y = localPred.r;
    if (localPred.x > canvas.width - localPred.r)  localPred.x = canvas.width - localPred.r;
    if (localPred.y > canvas.height - localPred.r) localPred.y = canvas.height - localPred.r;

    // Reconcile: tarik halus ke posisi otoritatif terbaru (smoothing 20%/frame)
    const last = snapBuf[snapBuf.length - 1];
    if (last) {
      const auth = last.players.find(p => p.seat === localPred.seat);
      if (auth) {
        localPred.x = lerp(localPred.x, auth.x, 0.18);
        localPred.y = lerp(localPred.y, auth.y, 0.18);
      }
    }
  }

  // ---------- Render loop (jalan SAMA di owner & non-owner) ----------
  let renderLastTs = 0;
  function renderLoop(ts) {
    const dt = Math.min(0.05, (ts - renderLastTs) / 1000 || 0);
    renderLastTs = ts;
    sampleRenderState();
    initPredFromSnapshot();
    predictTick(dt);
    drawFromRenderState();
    updateTimerUIFromRender();
    renderScoresFromRender();
    requestAnimationFrame(renderLoop);
  }
  function updateTimerUIFromRender() {
    ui.timer.textContent = ((renderState.timeLeft || state.timeLeft || 0) | 0) + 's';
  }

  function ownerStart() {
    state.diff = ui.diffSel.value;
    gameDuration = parseInt(ui.durSel ? ui.durSel.value : DURATION_DEFAULT, 10) || DURATION_DEFAULT;
    const cfg = DIFF[state.diff];
    canvas.width = cfg.arenaW;
    canvas.height = cfg.arenaH;
    state.players = buildPlayersFromSeats();
    initBotAI(state.players);
    if (state.players.length === 0) {
      ui.status.textContent = 'Belum ada seat terisi.';
      return;
    }
    state.boxes = [];
    const need = boxesNeeded(state.players.length);
    for (let i = 0; i < need; i++) state.boxes.push(spawnBox(cfg));
    state.timeLeft = gameDuration;
    state.running = true;
    elapsedAcc = 0;
    snapAcc = 0;
    lastTs = performance.now();
    ui.status.textContent = 'Bermain...';
    setRoomControlsLocked(true);
    sendWS({ type: 'game_started', room_id: RC.roomId, diff: state.diff, duration: gameDuration });
    startBGM();
    // Owner juga ikut render lewat snapshot+interpolasi (adil dengan non-owner).
    // Suntik snapshot pertama lokal supaya render-state langsung punya data.
    pushLocalSnapshotForOwner();
    requestAnimationFrame(ownerLoop);
  }

  function pushLocalSnapshotForOwner() {
    pushSnapshot({
      diff: state.diff,
      timeLeft: state.timeLeft,
      arenaW: canvas.width, arenaH: canvas.height,
      players: state.players.map(p => ({
        seat: p.seat, name: p.name, color: p.color,
        x: p.x, y: p.y, r: p.r,
        score: p.score, turbo: p.turbo, turboActive: p.turboActive,
      })),
      boxes: state.boxes.map(b => ({ x:b.x, y:b.y, size:b.size, kind:b.kind, color:b.color, age: Math.max(0, performance.now() - (b.bornAt||0)) })),
    });
  }

  function ownerLoop(ts) {
    if (!state.running) return;
    const dt = Math.min(0.05, (ts - lastTs) / 1000);
    lastTs = ts;
    elapsedAcc += dt;
    if (elapsedAcc >= 1) {
      state.timeLeft -= Math.floor(elapsedAcc);
      elapsedAcc -= Math.floor(elapsedAcc);
      if (state.timeLeft <= 0) {
        state.timeLeft = 0;
        ownerEnd();
        return;
      }
    }
    ownerUpdate(dt);
    expireOldBoxes(DIFF[state.diff]);
    snapAcc += dt;
    if (snapAcc >= SNAP_INTERVAL) {
      snapAcc = 0;
      broadcastSnapshot();
      // Owner juga ikut buffer snapshot lokalnya (sumber render-state owner).
      pushLocalSnapshotForOwner();
    }
    // CATATAN: owner TIDAK lagi menggambar dari `state` langsung.
    // Render dilakukan oleh renderLoop() dari snapshot+interpolasi (sama dgn non-owner).
    requestAnimationFrame(ownerLoop);
  }

  function inputForPlayer(p) {
    if (p.isBot) return botInputFor(p);
    if (p.userId === RC.myId) return localInput;
    return remoteInputs.get(p.userId) || { up:false, down:false, left:false, right:false, turbo:false };
  }

  // ---------- Bot AI dengan personality + target reservation ----------
  // Personality menentukan gaya bermain bot:
  //   greedy     -> agresif kejar skor, sering pakai turbo, tidak peduli risiko
  //   cautious   -> hindari penalty/freeze/slow walau skor besar
  //   opportunist-> kombinasi; ambil bonus tinggi, hindari trap parah
  //   wanderer   -> kadang random patrol, target ulang lebih lambat
  const PERSONALITIES = ['greedy', 'cautious', 'opportunist', 'wanderer'];
  function initBotAI(players) {
    const bots = players.filter(p => p.isBot);
    bots.forEach((p, i) => {
      p.ai = {
        personality: PERSONALITIES[i % PERSONALITIES.length],
        targetIdx: -1,
        retargetAt: 0,                  // ms timestamp untuk evaluasi target ulang
        reactionDelay: rand(120, 380),  // delay reaksi (ms) supaya tiap bot beda timing
        jitterX: rand(-8, 8),           // offset arah supaya path tidak identik
        jitterY: rand(-8, 8),
        wanderTarget: null,
      };
    });
  }

  // Skor "nilai" sebuah box untuk bot, berdasarkan personality.
  function botBoxValue(p, box, distSq) {
    const t = boxTypeOf(box.kind, state.diff);
    const per = p.ai.personality;
    let v = 0;
    // base value dari skor
    v += t.score * 30;
    // bonus berdasarkan tipe
    if (t.kind === 'boost')   v += 40;
    if (t.kind === 'bonus')   v += 30;
    if (t.kind === 'penalty') v -= 60;
    if (t.kind === 'freeze')  v -= 50;
    if (t.kind === 'slow')    v -= 35;

    // modifier per personality
    if (per === 'greedy')      { if (t.score > 0) v += 25; v += 10; }
    if (per === 'cautious')    { if (t.score < 0 || t.kind === 'freeze' || t.kind === 'slow') v -= 80; }
    if (per === 'opportunist') { if (t.kind === 'bonus' || t.kind === 'boost') v += 25; }
    if (per === 'wanderer')    { v += rand(-15, 15); }

    // jarak: makin jauh makin tidak menarik (skala kasar)
    const dist = Math.sqrt(distSq);
    v -= dist * 0.05;
    return v;
  }

  function botInputFor(p) {
    const ai = p.ai || (p.ai = { personality: 'greedy', targetIdx: -1, retargetAt: 0, reactionDelay: 200, jitterX: 0, jitterY: 0 });
    const now = performance.now();

    // Pilih ulang target setiap interval acak (dipengaruhi personality)
    const retargetEvery = ai.personality === 'wanderer' ? 1400 : (ai.personality === 'cautious' ? 700 : 500);
    if (now >= ai.retargetAt) {
      ai.retargetAt = now + retargetEvery + rand(-150, 150);

      // Reservasi target: hindari box yang sudah diklaim bot lain
      const claimed = new Set();
      for (const other of state.players) {
        if (other.isBot && other !== p && other.ai && other.ai.targetIdx >= 0) {
          claimed.add(other.ai.targetIdx);
        }
      }

      let bestIdx = -1, bestVal = -Infinity;
      for (let i = 0; i < state.boxes.length; i++) {
        const b = state.boxes[i];
        const dsq = (b.x - p.x) ** 2 + (b.y - p.y) ** 2;
        let val = botBoxValue(p, b, dsq);
        // Penalti jika sudah diincar bot lain (kecuali greedy yang cuek)
        if (claimed.has(i) && ai.personality !== 'greedy') val -= 40;
        if (val > bestVal) { bestVal = val; bestIdx = i; }
      }

      // Wanderer: kadang abaikan target dan jalan random
      if (ai.personality === 'wanderer' && Math.random() < 0.35) {
        ai.targetIdx = -1;
        ai.wanderTarget = {
          x: rand(40, canvas.width - 40),
          y: rand(40, canvas.height - 40),
        };
      } else {
        ai.targetIdx = bestIdx;
        ai.wanderTarget = null;
      }
    }

    // Resolve target jadi titik koordinat
    let tx = null, ty = null, distSqToTarget = Infinity;
    if (ai.targetIdx >= 0 && !state.boxes[ai.targetIdx]) { ai.targetIdx = -1; }
    if (ai.targetIdx >= 0 && state.boxes[ai.targetIdx]) {
      const b = state.boxes[ai.targetIdx];
      tx = b.x + ai.jitterX;
      ty = b.y + ai.jitterY;
      distSqToTarget = (b.x - p.x) ** 2 + (b.y - p.y) ** 2;
    } else if (ai.wanderTarget) {
      tx = ai.wanderTarget.x;
      ty = ai.wanderTarget.y;
      const ddx = tx - p.x, ddy = ty - p.y;
      distSqToTarget = ddx * ddx + ddy * ddy;
      if (distSqToTarget < 30 * 30) ai.wanderTarget = null;
    }

    if (tx === null) {
      return { up:false, down:false, left:false, right:false, turbo:false };
    }

    const dx = tx - p.x, dy = ty - p.y;
    // Threshold gerak: lebih besar -> bot kurang gemetar
    const TH = 4;
    // Personality menentukan kapan pakai turbo
    let useTurbo = false;
    if (ai.personality === 'greedy')      useTurbo = distSqToTarget > 140 * 140 && p.turbo > 25;
    else if (ai.personality === 'cautious') useTurbo = distSqToTarget > 260 * 260 && p.turbo > 60;
    else if (ai.personality === 'opportunist') useTurbo = distSqToTarget > 200 * 200 && p.turbo > 40;
    else                                  useTurbo = Math.random() < 0.05 && p.turbo > 30;

    return {
      up:    dy < -TH,
      down:  dy >  TH,
      left:  dx < -TH,
      right: dx >  TH,
      turbo: useTurbo,
    };
  }

  function ownerUpdate(dt) {
    const cfg = DIFF[state.diff];
    const now = performance.now();
    for (const p of state.players) {
      // Frozen: tidak bisa bergerak sama sekali
      if (now < (p.frozenUntil || 0)) {
        p.turboActive = false;
        continue;
      }
      const inp = inputForPlayer(p);
      let dx = 0, dy = 0;
      if (inp.up) dy -= 1;
      if (inp.down) dy += 1;
      if (inp.left) dx -= 1;
      if (inp.right) dx += 1;
      if (dx && dy) { dx *= 0.7071; dy *= 0.7071; }

      // Boost otomatis dari power-up: turbo aktif tanpa drain selama durasi
      const autoBoost = now < (p.boostUntil || 0);
      if (autoBoost) {
        p.turboActive = true;
      } else if (inp.turbo && p.turbo > 0) {
        p.turbo -= cfg.turboDrain * dt;
        if (p.turbo < 0) p.turbo = 0;
        p.turboActive = p.turbo > 0;
      } else {
        p.turbo += cfg.turboRegen * dt;
        if (p.turbo > cfg.turboMax) p.turbo = cfg.turboMax;
        p.turboActive = false;
      }

      // Slow dari power-up: kalikan kecepatan
      const slowMul = (now < (p.slowUntil || 0)) ? (p.slowMul || 0.55) : 1;
      const speed = cfg.baseSpeed * (p.turboActive ? cfg.turboMul : 1) * 60 * slowMul;
      p.x += dx * speed * dt;
      p.y += dy * speed * dt;
      if (p.x < p.r) p.x = p.r;
      if (p.y < p.r) p.y = p.r;
      if (p.x > canvas.width - p.r)  p.x = canvas.width - p.r;
      if (p.y > canvas.height - p.r) p.y = canvas.height - p.r;
    }

    // ---- Player↔player steal (Hard mode) ----
    // Jika DIFF aktif punya playerSteal=true, dan dua pemain bertabrakan,
    // pemain dengan turbo aktif mencuri 1 poin dari pemain tanpa turbo.
    // Cooldown 800ms per-pasangan, korban di-stun 250ms, skor tidak boleh < 0.
    if (cfg.playerSteal) {
      const nowMs = performance.now();
      const STEAL_CD = 800;
      const players = state.players;
      for (let i = 0; i < players.length; i++) {
        const a = players[i];
        for (let j = i + 1; j < players.length; j++) {
          const b = players[j];
          if (nowMs < (a.frozenUntil || 0) || nowMs < (b.frozenUntil || 0)) continue;
          const dxp = a.x - b.x, dyp = a.y - b.y;
          const rr  = (a.r + b.r);
          if (dxp*dxp + dyp*dyp > rr*rr) continue;

          let attacker = null, victim = null;
          if (a.turboActive && !b.turboActive)      { attacker = a; victim = b; }
          else if (b.turboActive && !a.turboActive) { attacker = b; victim = a; }
          else continue;

          attacker.stealCdUntil = attacker.stealCdUntil || {};
          if (nowMs < (attacker.stealCdUntil[victim.seat] || 0)) continue;
          if ((victim.score | 0) <= 0) continue;

          victim.score = Math.max(0, (victim.score | 0) - 1);
          attacker.score = (attacker.score | 0) + 1;
          attacker.stealCdUntil[victim.seat] = nowMs + STEAL_CD;
          victim.frozenUntil = nowMs + 250;
          playBoxSfx();
          sendWS({ type: 'sfx_box', room_id: RC.roomId, seat: attacker.seat, kind: 'steal' });
        }
      }
    }

    for (let i = 0; i < state.boxes.length; i++) {
      const b = state.boxes[i];
      for (const p of state.players) {
        const cx = Math.max(b.x - b.size/2, Math.min(p.x, b.x + b.size/2));
        const cy = Math.max(b.y - b.size/2, Math.min(p.y, b.y + b.size/2));
        const ddx = p.x - cx, ddy = p.y - cy;
        if (ddx*ddx + ddy*ddy <= p.r*p.r) {
          const t = boxTypeOf(b.kind, state.diff);
          // Skor (boleh negatif untuk penalty); jangan biarkan turun di bawah 0.
          p.score = Math.max(0, (p.score | 0) + (t.score | 0));
          // Efek tambahan
          const nowMs = performance.now();
          if (t.freezeMs)  p.frozenUntil = nowMs + t.freezeMs;
          if (t.slowMs)   { p.slowUntil = nowMs + t.slowMs; p.slowMul = t.slowMul || 0.55; }
          if (t.turboFill) { p.turbo = Math.min(cfg.turboMax, (p.turbo | 0) + t.turboFill); }
          if (t.boostMs)   p.boostUntil = nowMs + t.boostMs;

          // Reset klaim AI utk box yang baru saja diambil
          for (const other of state.players) {
            if (other.ai && other.ai.targetIdx === i) { other.ai.targetIdx = -1; other.ai.retargetAt = 0; }
          }
          state.boxes[i] = spawnBox(cfg);
          playBoxSfx();
          sendWS({ type: 'sfx_box', room_id: RC.roomId, seat: p.seat, kind: t.kind });
          break;
        }
      }
    }
  }

  function broadcastSnapshot() {
    sendWS({
      type: 'game_state',
      room_id: RC.roomId,
      diff: state.diff,
      timeLeft: state.timeLeft,
      arenaW: canvas.width,
      arenaH: canvas.height,
      players: state.players.map(p => ({
        seat: p.seat, name: p.name, color: p.color,
        x: Math.round(p.x), y: Math.round(p.y), r: p.r,
        score: p.score, turbo: Math.round(p.turbo), turboActive: p.turboActive,
      })),
      boxes: state.boxes.map(b => ({ x: Math.round(b.x), y: Math.round(b.y), size: b.size, kind: b.kind, color: b.color, age: Math.round(Math.max(0, performance.now() - (b.bornAt||0))) })),
    });
  }

  function ownerEnd() {
    state.running = false;
    stopBGM();
    const sorted = [...state.players].sort((a,b) => b.score - a.score);
    const top = sorted[0];
    const tie = sorted.filter(p => p.score === top.score);
    const msg = tie.length > 1
      ? 'Seri: ' + tie.map(p => p.name).join(', ')
      : 'Pemenang: ' + top.name + ' (' + top.score + ' poin)';
    ui.status.textContent = msg;
    drawAll();
    renderScores();
    updateTimerUI();
    const playersPayload = state.players.map(p => ({ seat:p.seat, name:p.name, score:p.score }));
    sendWS({ type: 'game_ended', room_id: RC.roomId, message: msg, players: playersPayload });
    saveSessionToServer();
    // Tampilkan modal custom (TIDAK auto-reset). Reset dilakukan lewat tombol Reset di room.
    setRoomControlsLocked(true);
    openEndModal(playersPayload, msg);
  }

  function saveSessionToServer() {
    if (!RC.saveUrl || !RC.roomId) return;
    fetch(RC.saveUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify({
        room_id: RC.roomId,
        difficulty: state.diff,
        num_players: state.players.length,
        duration_sec: gameDuration,
        scores: state.players.map(p => ({ slot: p.seat, label: p.name, score: p.score })),
      }),
    }).catch(() => {});
  }

  // ---------- Non-owner: kirim input + render dari snapshot ----------
  let inputSendTimer = null;
  function startInputSender() {
    if (inputSendTimer) return;
    inputSendTimer = setInterval(() => {
      sendWS({
        type: 'game_input',
        room_id: RC.roomId,
        user_id: RC.myId,
        input: { ...localInput },
      });
    }, 50);
  }
  function stopInputSender() {
    if (inputSendTimer) { clearInterval(inputSendTimer); inputSendTimer = null; }
  }

  function applyRemoteSnapshot(d) {
    // Non-owner: simpan ke buffer; renderLoop akan interpolasi.
    state.diff = d.diff || state.diff;
    pushSnapshot({
      diff: d.diff || state.diff,
      timeLeft: d.timeLeft,
      arenaW: d.arenaW, arenaH: d.arenaH,
      players: d.players || [],
      boxes: d.boxes || [],
    });
  }

  // ---------- Drawing dari renderState (dipakai owner & non-owner) ----------
  function drawFromRenderState() {
    const players = renderState.players.length ? renderState.players : state.players;
    const boxes   = renderState.boxes.length   ? renderState.boxes   : state.boxes;
    // Override posisi pemain lokal dengan hasil prediksi (smoother & instan)
    let drawPlayers = players;
    if (localPred.active) {
      drawPlayers = players.map(p =>
        p.seat === localPred.seat ? { ...p, x: localPred.x, y: localPred.y } : p);
    }
    // Background arena (image opsional + overlay gelap utk kontras)
    ctx.fillStyle = '#1a1a1a';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    const arenaImg = window.__ARENA_IMG__;
    if (arenaImg && arenaImg.complete && arenaImg.naturalWidth > 0) {
      ctx.drawImage(arenaImg, 0, 0, canvas.width, canvas.height);
      ctx.fillStyle = 'rgba(0,0,0,0.28)';
      ctx.fillRect(0, 0, canvas.width, canvas.height);
    }
    ctx.strokeStyle = 'rgba(255,255,255,0.05)'; ctx.lineWidth = 1;
    for (let x = 0; x < canvas.width; x += 40) { ctx.beginPath(); ctx.moveTo(x,0); ctx.lineTo(x,canvas.height); ctx.stroke(); }
    for (let y = 0; y < canvas.height; y += 40) { ctx.beginPath(); ctx.moveTo(0,y); ctx.lineTo(canvas.width,y); ctx.stroke(); }

    const nowMs = performance.now();
    for (const b of boxes) {
      const age = b.age || 0;
      const remaining = Math.max(0, BOX_LIFETIME_MS - age);
      const warning = remaining <= BOX_WARN_MS;
      // Pulse: 4Hz -> 10Hz mendekati expiry
      const pulseHz = warning ? (4 + 6 * (1 - remaining / BOX_WARN_MS)) : 0;
      const pulse = warning ? (0.5 + 0.5 * Math.sin(nowMs * 0.001 * Math.PI * 2 * pulseHz)) : 0;
      const prevAlpha = ctx.globalAlpha;
      ctx.globalAlpha = warning ? (0.55 + 0.45 * (remaining / BOX_WARN_MS)) : 1;

      // Box skin: pakai gambar kalau pack di-set, fallback ke warna solid
      const _skin = (window.__BOX_SKINS__ && window.__BOX_SKINS__[b.kind]) || null;
      if (_skin && _skin.complete && _skin.naturalWidth > 0) {
        ctx.drawImage(_skin, b.x - b.size/2, b.y - b.size/2, b.size, b.size);
      } else {
        ctx.fillStyle = b.color || '#ffd23f';
        ctx.strokeStyle = '#1a1a1a'; ctx.lineWidth = 2;
        ctx.fillRect(b.x - b.size/2, b.y - b.size/2, b.size, b.size);
        ctx.strokeRect(b.x - b.size/2, b.y - b.size/2, b.size, b.size);
      }

      if (warning) {
        ctx.save();
        ctx.strokeStyle = '#ff3b3b';
        ctx.lineWidth = 2 + 2 * pulse;
        ctx.shadowColor = '#ff3b3b';
        ctx.shadowBlur = 6 + 10 * pulse;
        ctx.strokeRect(b.x - b.size/2 - 1, b.y - b.size/2 - 1, b.size + 2, b.size + 2);
        ctx.restore();
      }

      // Glyph kecil sebagai petunjuk tipe.
      // CATATAN: hanya digambar saat pack box = default (tanpa skin gambar).
      // Kalau pack 'nature'/'recycle' aktif, gambar PNG menutupi kotak
      // sehingga teks (dan perubahan font-size apapun) TIDAK akan terlihat.
      // Ganti pack ke "Default (warna)" di toolbar untuk melihatnya.
      const glyph = ({ normal:'+1', bonus:'+3', penalty:'-2', freeze:'❄', slow:'≈', boost:'»' })[b.kind] || '';
      if (glyph && !(_skin && _skin.complete && _skin.naturalWidth > 0)) {
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.font = 'bold 20px Inter, sans-serif';
        // Outline tipis agar terbaca di atas warna kotak terang/gelap
        ctx.lineWidth = 3;
        ctx.strokeStyle = 'rgba(255,255,255,0.85)';
        ctx.strokeText(glyph, b.x, b.y + 1);
        ctx.fillStyle = '#1a1a1a';
        ctx.fillText(glyph, b.x, b.y + 1);
        ctx.textBaseline = 'alphabetic';
      }
      ctx.globalAlpha = prevAlpha;
    }
    for (const p of drawPlayers) {
      const r = p.r || 16;
      if (p.turboActive) {
        ctx.beginPath(); ctx.arc(p.x, p.y, r + 6, 0, Math.PI*2);
        ctx.fillStyle = 'rgba(255,255,255,0.18)'; ctx.fill();
      }
      // Avatar image (per-seat) bila tersedia
      const av = (window.__AVATAR_IMGS__ && window.__AVATAR_IMGS__[p.seat]) || null;
      if (av && av.complete && av.naturalWidth > 0) {
        ctx.save();
        ctx.beginPath(); ctx.arc(p.x, p.y, r, 0, Math.PI*2); ctx.closePath();
        ctx.clip();
        ctx.drawImage(av, p.x - r, p.y - r, r*2, r*2);
        ctx.restore();
      } else {
        // Fallback: lingkaran berwarna + huruf inisial
        ctx.beginPath(); ctx.arc(p.x, p.y, r, 0, Math.PI*2);
        ctx.fillStyle = p.color; ctx.fill();
        const initial = (p.name || '?').trim().charAt(0).toUpperCase();
        ctx.fillStyle = '#fff';
        ctx.font = 'bold ' + Math.round(r*1.1) + 'px Inter, sans-serif';
        ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        ctx.fillText(initial, p.x, p.y + 1);
        ctx.textBaseline = 'alphabetic';
      }
      // Border putih tebal — pembeda visual dari box (kotak)
      ctx.beginPath(); ctx.arc(p.x, p.y, r, 0, Math.PI*2);
      ctx.lineWidth = 3; ctx.strokeStyle = '#ffffff'; ctx.stroke();
      ctx.lineWidth = 1; ctx.strokeStyle = '#1a1a1a'; ctx.stroke();
      // Nama label
      ctx.fillStyle = '#fff'; ctx.font = 'bold 11px Inter, sans-serif'; ctx.textAlign = 'center';
      ctx.fillText(p.name, p.x, p.y - r - 6);
    }
  }
  // Backward compat (dipakai oleh ownerEnd untuk paint terakhir)
  function drawAll() { drawFromRenderState(); }

  function renderScoresFromRender() {
    const list = renderState.players.length ? renderState.players : state.players;
    const cfg = DIFF[state.diff] || DIFF.normal;
    ui.scoreList.innerHTML = '';
    for (const p of list) {
      const pct = Math.max(0, Math.min(100, ((p.turbo || 0) / cfg.turboMax) * 100));
      const row = document.createElement('div');
      row.className = 'flex items-center gap-2 bg-control border border-ink px-2 py-1 rounded-sm';
      row.innerHTML = `
        <span class="inline-block w-3 h-3 rounded-full border border-ink" style="background:${p.color}"></span>
        <span class="font-bold">${escapeHtml(p.name)}</span>
        <span class="ml-1">Skor: <strong>${p.score|0}</strong></span>
        <span class="ml-2 text-xs text-inkMuted">Turbo</span>
        <span class="inline-block w-20 h-2 bg-ink/20 border border-ink relative overflow-hidden">
          <span class="absolute inset-y-0 left-0 bg-accentGreen" style="width:${pct}%"></span>
        </span>`;
      ui.scoreList.appendChild(row);
    }
  }
  function renderScores() { renderScoresFromRender(); }
  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
  }
  function updateTimerUI() { ui.timer.textContent = (state.timeLeft|0) + 's'; }

  // ---------- Final scores: custom modal (NOT native alert) ----------
  const endModal       = document.getElementById('end-modal');
  const endScoresEl    = document.getElementById('end-modal-scores');
  const endHeadlineEl  = document.getElementById('end-modal-headline');
  const endHintEl      = document.getElementById('end-modal-hint');
  const endOkBtn       = document.getElementById('end-modal-ok');

  const roomLockControls = [
    ui.diffSel, ui.durSel, ui.bgmSel, ui.startBtn,
    document.getElementById('game-arena'),
    document.getElementById('game-boxskin'),
    document.getElementById('float-chat'),
    document.getElementById('room-leave-btn'),
    document.getElementById('msgRoom'),
    document.getElementById('room-chat-send'),
    ui.sfxOn
  ].filter(Boolean);

  function setRoomControlsLocked(locked) {
    window.__ROOM_CONTROLS_LOCKED__ = !!locked;
    document.body.classList.toggle('game-controls-locked', !!locked);
    for (const el of roomLockControls) {
      el.disabled = !!locked || ((!isOwner) && (el === ui.diffSel || el === ui.durSel || el === ui.startBtn || el === ui.bgmSel));
    }
    if (ui.resetBtn) ui.resetBtn.disabled = !isOwner;
    window.dispatchEvent(new CustomEvent('room-controls-lock', { detail: { locked: !!locked } }));
  }

  function openEndModal(players, headline) {
    if (!endModal) return;
    const sorted = [...players].sort((a,b) => b.score - a.score);
    endHeadlineEl.textContent = headline || 'Waktu habis!';
    endScoresEl.innerHTML = sorted.map((p, i) => `
      <li class="${i === 0 ? 'is-winner' : ''}">
        <span>${i+1}. ${escapeHtml(p.name)}</span>
        <strong>${p.score|0} poin</strong>
      </li>`).join('');
    endHintEl.textContent = isOwner
      ? 'Gunakan tombol Reset di room untuk mengatur ulang game di semua pemain. "OK" hanya menutup dialog.'
      : 'Menunggu owner melakukan reset. "OK" hanya menutup dialog di perangkatmu.';
    endModal.classList.remove('hidden');
    endModal.setAttribute('aria-hidden', 'false');
  }
  function closeEndModal() {
    if (!endModal) return;
    endModal.classList.add('hidden');
    endModal.setAttribute('aria-hidden', 'true');
  }

  if (endOkBtn) {
    // OK = close lokal saja (owner & non-owner). Tidak ada broadcast.
    endOkBtn.addEventListener('click', closeEndModal);
  }
  function triggerOwnerReset() {
    // Sama dengan handler tombol Reset.
    state.running = false;
    stopBGM();
    const cfg = DIFF[state.diff] || DIFF.normal;
    canvas.width = cfg.arenaW;
    canvas.height = cfg.arenaH;
    state.players = buildPlayersFromSeats();
    state.boxes = [];
    const need = boxesNeeded(state.players.length);
    for (let i = 0; i < need; i++) state.boxes.push(spawnBox(cfg));
    state.timeLeft = gameDuration;          // FIX: dulu GAME_DURATION (undefined)
    remoteInputs.clear();
    drawAll(); renderScores(); updateTimerUI();
    ui.status.textContent = 'Direset. Tekan Mulai.';
    setRoomControlsLocked(false);
    pushLocalSnapshotForOwner();
    broadcastSnapshot();
    sendWS({ type: 'game_reset', room_id: RC.roomId });
  }

  // ---------- Keyboard (uniform: WASD + Space) ----------
  const KEYS = { up:'KeyW', down:'KeyS', left:'KeyA', right:'KeyD', turbo:'Space' };
  function setKey(code, val) {
    let changed = false;
    for (const k in KEYS) {
      if (KEYS[k] === code && localInput[k] !== val) { localInput[k] = val; changed = true; }
    }
    return changed;
  }
  window.addEventListener('keydown', (e) => {
    const t = e.target;
    if (t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA')) return;
    if (e.code === 'Space' || e.code.startsWith('Arrow')) e.preventDefault();
    setKey(e.code, true);
  });
  window.addEventListener('keyup', (e) => { setKey(e.code, false); });
  window.addEventListener('blur', () => { for (const k in localInput) localInput[k] = false; });

  // ---------- WS message handling ----------
  window.addEventListener('ws-game-msg', (ev) => {
    const d = ev.detail;
    if (!d) return;

    if (isOwner) {
      // Owner menerima input dari remote players
      if (d.type === 'game_input' && d.user_id && d.user_id !== RC.myId) {
        remoteInputs.set(Number(d.user_id), d.input || {});
      }
      // Difficulty diubah lewat owner sendiri (tidak perlu listen game_diff dari luar)
    } else {
      // Non-owner: terima snapshot, started, ended
      if (d.type === 'game_state') applyRemoteSnapshot(d);
      else if (d.type === 'game_started') {
        state.running = true; startInputSender(); ui.status.textContent = 'Bermain...';
        if (d.diff) { state.diff = d.diff; ui.diffSel.value = d.diff; }
        if (d.duration) { gameDuration = d.duration; if (ui.durSel) ui.durSel.value = String(d.duration); state.timeLeft = d.duration; updateTimerUI(); }
        closeEndModal();   // jika modal masih terbuka dari ronde sebelumnya, tutup
        startBGM();
      }
      else if (d.type === 'sfx_box') { playBoxSfx(); }
      else if (d.type === 'game_ended')   {
        state.running = false; stopInputSender();
        stopBGM();
        ui.status.textContent = d.message || 'Game selesai.';
        // Modal custom (non-blocking) — non-owner hanya lihat tombol OK.
        const players = Array.isArray(d.players) ? d.players : state.players.map(p => ({ seat:p.seat, name:p.name, score:p.score }));
        openEndModal(players, d.message || 'Waktu habis!');
      }
      else if (d.type === 'game_reset')    {
        state.running = false; stopInputSender();
        stopBGM();
        state.timeLeft = gameDuration;
        // players & boxes akan ter-overwrite oleh snapshot fresh dari owner.
        ui.status.textContent = 'Direset oleh owner. Menunggu mulai...';
        updateTimerUI();
        closeEndModal();   // sinkronisasi: non-owner ikut tutup modal tanpa refresh
      }
      else if (d.type === 'game_diff')    { if (d.diff) { ui.diffSel.value = d.diff; state.diff = d.diff; } }
      else if (d.type === 'game_duration'){ if (d.duration) { gameDuration = d.duration; if (ui.durSel) ui.durSel.value = String(d.duration); state.timeLeft = d.duration; updateTimerUI(); } }
    }
  });

  // ---------- UI hooks (owner only) ----------
  if (isOwner) {
    ui.startBtn.addEventListener('click', ownerStart);
    ui.resetBtn.addEventListener('click', () => {
      // Stop loop, lalu rebuild pemain ke posisi awal sesuai seat saat ini.
      state.running = false;
      stopBGM();
      const cfg = DIFF[state.diff] || DIFF.normal;
      canvas.width = cfg.arenaW;
      canvas.height = cfg.arenaH;
      state.players = buildPlayersFromSeats(); // posisi & skor di-reset ulang
      state.boxes = [];
      const need = boxesNeeded(state.players.length);
      for (let i = 0; i < need; i++) state.boxes.push(spawnBox(cfg));
      gameDuration = parseInt(ui.durSel ? ui.durSel.value : gameDuration, 10) || gameDuration;
      state.timeLeft = gameDuration;
      // Reset input remote supaya tidak ada gerak sisa.
      remoteInputs.clear();
      drawAll(); renderScores(); updateTimerUI();
      ui.status.textContent = 'Direset. Tekan Mulai.';
      setRoomControlsLocked(false);
      closeEndModal();
      // Broadcast: snapshot fresh + sinyal ended supaya non-owner ikut reset tampilan.
      broadcastSnapshot();
      sendWS({ type: 'game_reset', room_id: RC.roomId });
    });
    ui.diffSel.addEventListener('change', () => {
      state.diff = ui.diffSel.value;
      // simpan ke server dan beritahu peers
      const fd = new FormData();
      fd.append('room_id', RC.roomId);
      fd.append('difficulty', ui.diffSel.value);
      fetch(RC.diffUrl, { method:'POST', body: fd, credentials:'same-origin' }).catch(() => {});
      sendWS({ type: 'game_diff', room_id: RC.roomId, diff: ui.diffSel.value });
    });
    if (ui.durSel) {
      ui.durSel.addEventListener('change', () => {
        gameDuration = parseInt(ui.durSel.value, 10) || DURATION_DEFAULT;
        state.timeLeft = gameDuration;
        updateTimerUI();
        sendWS({ type: 'game_duration', room_id: RC.roomId, duration: gameDuration });
      });
    }
  } else {
    // Non-owner mulai kirim input begitu di-load
    startInputSender();
  }

  // Initial paint
  state.diff = ui.diffSel.value;
  const cfg0 = DIFF[state.diff];
  canvas.width = cfg0.arenaW; canvas.height = cfg0.arenaH;
  setRoomControlsLocked(false);
  drawAll(); updateTimerUI();

  // Start render loop SEKALI (jalan untuk owner & non-owner sama).
  requestAnimationFrame(renderLoop);

  // ---------- Audio: SFX & BGM ----------
  // BGM disinkronkan: hanya OWNER yang menentukan file dan kapan start/stop.
  // Owner mem-broadcast 'bgm_start'/'bgm_stop'; SEMUA klien (termasuk owner)
  // benar-benar memutar audio sebagai respons atas event tersebut, supaya
  // perilakunya identik di setiap perangkat.
  let bgmAudio = null;
  let bgmCurrentFile = '';
  const sfxBox = new Audio('assets/sfx/box.wav');
  sfxBox.preload = 'auto';

  function sfxEnabled() { return !ui.sfxOn || ui.sfxOn.checked; }
  function playBoxSfx() {
    if (!sfxEnabled()) return;
    try {
      const a = sfxBox.cloneNode(true);
      a.volume = 0.7;
      a.play().catch(() => {});
    } catch (_) {}
  }

  // Putar BGM lokal dengan file tertentu (dipanggil oleh listener bgm_start).
  function playBGMFile(file) {
    if (!file) return;
    stopBGMLocal();
    bgmCurrentFile = file;
    bgmAudio = new Audio('assets/bgm/' + file);
    bgmAudio.loop = true;
    bgmAudio.volume = 0.4;
    bgmAudio.play().catch(() => {
      // Browser butuh interaksi user. Owner sudah klik Mulai; non-owner
      // umumnya juga sudah berinteraksi (klik Duduk dsb). Jika gagal,
      // diam-diam saja — jangan crash.
    });
  }
  function stopBGMLocal() {
    if (bgmAudio) { try { bgmAudio.pause(); bgmAudio.currentTime = 0; } catch(_) {} bgmAudio = null; }
    bgmCurrentFile = '';
  }

  // Owner-only: broadcast perintah start/stop BGM ke semua klien.
  function ownerBroadcastBgmStart() {
    if (!isOwner) return;
    const sel = ui.bgmSel ? ui.bgmSel.value : '';
    if (!sel) return; // "— Tanpa musik —"
    sendWS({ type: 'bgm_start', room_id: RC.roomId, file: sel });
  }
  function ownerBroadcastBgmStop() {
    if (!isOwner) return;
    sendWS({ type: 'bgm_stop', room_id: RC.roomId });
  }

  // Listener bgm_start / bgm_stop berlaku untuk SEMUA klien (owner & non-owner).
  window.addEventListener('ws-game-msg', (ev) => {
    const d = ev.detail;
    if (!d || typeof d.type !== 'string') return;
    if (d.type === 'bgm_start' && d.file) {
      // Sinkronkan tampilan select bgm di non-owner juga (informatif).
      if (ui.bgmSel && !isOwner) {
        // Hanya set kalau opsi tersebut sudah dimuat.
        const opt = Array.from(ui.bgmSel.options).find(o => o.value === d.file);
        if (opt) ui.bgmSel.value = d.file;
      }
      playBGMFile(String(d.file));
    } else if (d.type === 'bgm_stop') {
      stopBGMLocal();
    }
  });

  // Kompatibilitas: helper lama. Sekarang hanya OWNER yang boleh memicu BGM,
  // dan ia memicu via broadcast (semua klien lalu memutar lewat bgm_start).
  function startBGM() {
    if (isOwner) ownerBroadcastBgmStart();
  }
  function stopBGM() {
    // Owner: stop semua orang. Non-owner: tidak punya wewenang — tetap
    // hentikan lokal saja sebagai fallback (mis. saat keluar room).
    if (isOwner) {
      ownerBroadcastBgmStop();
      stopBGMLocal();
    } else {
      stopBGMLocal();
    }
  }

  // Non-owner: select BGM dimatikan permanen (hanya owner yang memilih).
  if (!isOwner && ui.bgmSel) {
    ui.bgmSel.disabled = true;
    ui.bgmSel.title = 'Hanya owner yang dapat memilih BGM.';
  }

  // Owner: saat ganti pilihan BGM dan sedang memutar, ganti track langsung.
  if (isOwner && ui.bgmSel) {
    ui.bgmSel.addEventListener('change', () => {
      // Sinkronkan dropdown ke peers (informatif saja, tidak auto-play
      // sampai owner memulai game atau memang sedang memutar).
      sendWS({ type: 'bgm_select', room_id: RC.roomId, file: ui.bgmSel.value || '' });
      if (bgmAudio || bgmCurrentFile) {
        // Sedang memutar -> ganti track untuk semua orang.
        if (ui.bgmSel.value) ownerBroadcastBgmStart();
        else ownerBroadcastBgmStop();
      }
    });
  }

  // Sinkron dropdown saat owner memilih (tanpa start) — hanya non-owner.
  window.addEventListener('ws-game-msg', (ev) => {
    const d = ev.detail;
    if (!d || d.type !== 'bgm_select' || isOwner || !ui.bgmSel) return;
    const val = String(d.file || '');
    const opt = Array.from(ui.bgmSel.options).find(o => o.value === val);
    if (opt) ui.bgmSel.value = val;
  });


  // Muat daftar BGM dari assets/bgm/manifest.json (user yang isi list nama file)
  (function loadBgmList() {
    if (!ui.bgmSel) return;
    fetch('assets/bgm/manifest.json', { cache: 'no-store' })
      .then(r => r.ok ? r.json() : [])
      .then(list => {
        if (!Array.isArray(list)) return;
        for (const item of list) {
          // Item bisa string "nama.mp3" atau {file, label}
          const opt = document.createElement('option');
          if (typeof item === 'string') { opt.value = item; opt.textContent = item.replace(/\.[^.]+$/, ''); }
          else { opt.value = item.file; opt.textContent = item.label || item.file; }
          ui.bgmSel.appendChild(opt);
        }
      })
      .catch(() => {});
  })();

  // ---------- Mobile: Joystick (fixed MOBA) + floating Turbo & Reset Game ----------
  (function setupMobileControls() {
    const joyWrap = document.getElementById('mobile-joystick');
    const joyBase = document.getElementById('joy-base');
    const joyKnob = document.getElementById('joy-knob');
    const turboBtn = document.getElementById('float-turbo');

    // ===== Joystick (fixed): base selalu di posisi tetap, knob digeser dari sana =====
    if (joyWrap && joyBase && joyKnob) {
      const DEADZONE = 0.18;
      let activeId = null;
      let baseRect = null;
      let baseRadius = 50; // jarak max knob dari pusat (px)

      const resetKnob = () => {
        joyKnob.style.transform = 'translate(0,0)';
        localInput.up = localInput.down = localInput.left = localInput.right = false;
      };

      const updateFromPoint = (clientX, clientY) => {
        if (!baseRect) baseRect = joyBase.getBoundingClientRect();
        const cx = baseRect.left + baseRect.width / 2;
        const cy = baseRect.top  + baseRect.height / 2;
        let dx = clientX - cx;
        let dy = clientY - cy;
        const dist = Math.hypot(dx, dy);
        const max = baseRect.width / 2;
        baseRadius = max;
        if (dist > max) { dx = dx / dist * max; dy = dy / dist * max; }
        joyKnob.style.transform = `translate(${dx}px, ${dy}px)`;
        const nx = dx / max, ny = dy / max;
        const mag = Math.hypot(nx, ny);
        if (mag < DEADZONE) {
          localInput.up = localInput.down = localInput.left = localInput.right = false;
          return;
        }
        // 8-direction mapping dari sudut
        const ang = Math.atan2(ny, nx); // -PI..PI
        const deg = ang * 180 / Math.PI;
        localInput.right = deg > -67.5  && deg <  67.5;
        localInput.left  = deg >  112.5 || deg < -112.5;
        localInput.down  = deg >   22.5 && deg <  157.5;
        localInput.up    = deg < -22.5  && deg > -157.5;
      };

      joyWrap.addEventListener('pointerdown', (e) => {
        if (activeId !== null) return;
        activeId = e.pointerId;
        baseRect = joyBase.getBoundingClientRect();
        joyWrap.setPointerCapture(activeId);
        updateFromPoint(e.clientX, e.clientY);
        e.preventDefault();
      });
      joyWrap.addEventListener('pointermove', (e) => {
        if (e.pointerId !== activeId) return;
        updateFromPoint(e.clientX, e.clientY);
        e.preventDefault();
      });
      const endPointer = (e) => {
        if (e.pointerId !== activeId) return;
        try { joyWrap.releasePointerCapture(activeId); } catch (_) {}
        activeId = null;
        resetKnob();
      };
      joyWrap.addEventListener('pointerup', endPointer);
      joyWrap.addEventListener('pointercancel', endPointer);
      joyWrap.addEventListener('pointerleave', endPointer);
      window.addEventListener('resize', () => { baseRect = null; });
    }

    // ===== Turbo: hold-to-activate (pointerdown/up) =====
    if (turboBtn) {
      const turboOn  = (e) => { e.preventDefault(); localInput.turbo = true;  turboBtn.classList.add('is-active'); };
      const turboOff = (e) => { e.preventDefault(); localInput.turbo = false; turboBtn.classList.remove('is-active'); };
      turboBtn.addEventListener('pointerdown',   turboOn);
      turboBtn.addEventListener('pointerup',     turboOff);
      turboBtn.addEventListener('pointercancel', turboOff);
      turboBtn.addEventListener('pointerleave',  turboOff);
    }

    // Reset Game melayang dihapus dari mobile sesuai permintaan user.
    // Reset tetap bisa dilakukan owner via tombol "Reset" di toolbar desktop
    // atau via tombol Reset di room saat game selesai.
  })();
})();
