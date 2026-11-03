/* Shared daily companion. No account or scheduling data is sent to the weather provider. */
document.addEventListener('DOMContentLoaded', () => {
  const source = document.getElementById('care-day-data');
  if (!source) return;
  const data = JSON.parse(source.textContent);
  const zone = 'Europe/Lisbon';
  const clock = new Intl.DateTimeFormat('pt-PT', { timeZone: zone, hour: '2-digit', minute: '2-digit', hourCycle: 'h23' });
  const dayFormat = new Intl.DateTimeFormat('en-CA', { timeZone: zone, year: 'numeric', month: '2-digit', day: '2-digit' });
  const dateFormat = new Intl.DateTimeFormat('pt-PT', { timeZone: zone, day: 'numeric', month: 'short' });
  const dayKey = (date) => { const parts = Object.fromEntries(dayFormat.formatToParts(date).map(p => [p.type, p.value])); return `${parts.year}-${parts.month}-${parts.day}`; };
  let previousDate = dayKey(new Date());
  let skyState;
  let messageIndex = data.index;
  const showMessage = () => { document.querySelector('[data-care-message]').textContent = data.messages[messageIndex]; };
  document.querySelector('[data-care-next]').addEventListener('click', () => { messageIndex = (messageIndex + 1) % data.messages.length; showMessage(); });
  function tick() {
    const now = new Date();
    const formattedTime = clock.format(now);
    const hour = Number(formattedTime.slice(0, 2));
    const currentDate = dayKey(now);
    const time = document.querySelector('[data-care-clock]');
    time.textContent = formattedTime;
    time.dateTime = now.toISOString();
    document.querySelector('[data-care-date]').textContent = dateFormat.format(now) + ' · Lisboa';
    document.querySelectorAll('[data-care-greeting]').forEach(e => { e.textContent = hour < 12 ? 'Bom dia' : hour < 19 ? 'Boa tarde' : 'Boa noite'; });
    const sun = data.sun[currentDate];
    const daylight = sun ? now.getTime() >= sun[0] && now.getTime() < sun[1] : hour >= 7 && hour < 19;
    if (skyState !== daylight) {
      skyState = daylight;
      const sky = document.querySelector('[data-care-sky]');
      sky.dataset.day = String(daylight);
      sky.setAttribute('aria-label', (daylight ? 'Dia' : 'Noite') + ' em Portimão');
      const icon = document.createElement('i');
      icon.dataset.lucide = daylight ? 'sun' : 'moon';
      icon.setAttribute('aria-hidden', 'true');
      sky.replaceChildren(icon);
      window.lucide?.createIcons();
    }
    if (currentDate !== previousDate) {
      previousDate = currentDate;
      messageIndex = (messageIndex + 1) % data.messages.length;
      showMessage();
      loadWeather();
    }
  }
  const temperature = document.querySelector('[data-care-temperature]');
  const forecast = document.querySelector('[data-care-forecast]');
  const retry = document.querySelector('[data-weather-retry]');
  const cacheKey = 'care4all.ipma.portimao.v1';
  let busy = false;
  function present(payload) {
    const today = payload?.data?.find(row => row.forecastDate === dayKey(new Date()));
    if (!today || today.tMin == null || today.tMax == null || today.tMin === '' || today.tMax === '') return false;
    const min = Number(today.tMin), max = Number(today.tMax);
    if (!Number.isFinite(min) || !Number.isFinite(max) || min < -30 || max > 60 || min > max) return false;
    temperature.textContent = `${Math.round(min)}° / ${Math.round(max)}°`;
    forecast.textContent = `Mínima ${min.toLocaleString('pt-PT')} °C · Máxima ${max.toLocaleString('pt-PT')} °C`;
    return true;
  }
  async function loadWeather(force = false) {
    if (busy) return;
    busy = true;
    retry.disabled = true;
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 8000);
    try {
      let cached;
      try { cached = JSON.parse(localStorage.getItem(cacheKey)); } catch { /* Storage may be unavailable in private browsing. */ }
      if (!force && cached && Date.now() - cached.savedAt >= 0 && Date.now() - cached.savedAt < 3600000 && present(cached.payload)) return;
      temperature.textContent = 'A consultar…';
      const response = await fetch('https://api.ipma.pt/open-data/forecast/meteorology/cities/daily/1081100.json', { signal: controller.signal, credentials: 'omit', referrerPolicy: 'no-referrer' });
      if (!response.ok) throw new Error('Weather unavailable');
      const payload = await response.json();
      if (!present(payload)) throw new Error('No forecast for today');
      try { localStorage.setItem(cacheKey, JSON.stringify({ savedAt: Date.now(), payload })); } catch { /* The forecast still works without persistence. */ }
    } catch {
      temperature.textContent = 'Indisponível';
      forecast.textContent = 'Não foi possível obter a previsão de hoje. Podes tentar novamente ou consultar o IPMA.';
    } finally { clearTimeout(timeout); retry.disabled = false; busy = false; }
  }
  retry.addEventListener('click', () => loadWeather(true));
  document.addEventListener('click', event => { const details = document.querySelector('.care-weather'); if (!details.contains(event.target)) details.open = false; });
  document.addEventListener('keydown', event => { if (event.key === 'Escape') document.querySelector('.care-weather').open = false; });
  tick();
  setInterval(() => { if (!document.hidden) tick(); }, 1000);
  setInterval(() => { if (!document.hidden) loadWeather(); }, 3600000);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) { tick(); loadWeather(); } });
  loadWeather();
});
