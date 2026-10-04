// Interactive tour map (Fig 5). Trips and their stops are pins; selecting one fills the panel below the map.
(function () {
  const trips = window.MAP_TRIPS || [];
  const T = window.I18N || {};
  const el = document.getElementById('map');
  if (!el) return;
  if (typeof L === 'undefined') {
    el.innerHTML = '<p style="padding:20px">' + (T.map_offline || 'The map needs an internet connection.') + '</p>';
    return;
  }

  const map = L.map(el, { scrollWheelZoom: false }).setView([33.9, 35.8], 8);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 18,
    attribution: '&copy; OpenStreetMap contributors',
  }).addTo(map);

  const ICONS = { trip: '⛰', place: '🏛', hotel: '🏨', restaurant: '🍽', meeting: '👤' };
  const esc = s => String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const icon = (kind, selected) => L.divIcon({
    className: '',
    html: '<div class="pin ' + (kind === 'place' ? 'trip' : kind) + (selected ? ' selected' : '') + '">' + (ICONS[kind] || '📍') + '</div>',
    iconSize: [34, 34], iconAnchor: [17, 40], popupAnchor: [0, -38],
  });

  const markers = {};
  const bounds = [];
  trips.forEach(t => {
    const m = L.marker([t.lat, t.lng], { icon: icon('trip'), title: t.title }).addTo(map);
    m.bindPopup('<b>' + esc(t.title) + '</b><br>' + esc(t.destination) + '<br>' + esc(t.dates) +
      '<br><a href="trip.php?id=' + t.id + '">' + esc(T.info_about_tour || 'Info about tour') + ' ›</a>');
    m.on('click', () => select(t.id));
    markers[t.id] = m;
    bounds.push([t.lat, t.lng]);
    t.stops.forEach(s => {
      const sm = L.marker([s.lat, s.lng], { icon: icon(s.kind), title: s.name }).addTo(map);
      sm.bindPopup('<b>' + esc(s.name) + '</b><br><small>' + esc(t.title) + '</small>');
      sm.on('click', () => select(t.id));
      bounds.push([s.lat, s.lng]);
    });
  });
  if (bounds.length > 1) map.fitBounds(bounds, { padding: [30, 30], maxZoom: 10 });

  // Re-enable wheel zoom once the user interacts with the map
  map.on('focus click', () => map.scrollWheelZoom.enable());

  const $ = id => document.getElementById(id);
  function starsHtml(avg) {
    let h = '<span class="stars outline">';
    for (let i = 1; i <= 5; i++) h += '<i class="' + (avg >= i ? 'on' : '') + '" style="' + (avg >= i ? 'color:var(--amber);-webkit-text-stroke:0' : '') + '">★</i>';
    return h + '</span>';
  }
  let current = null;
  function select(id) {
    const t = trips.find(x => x.id === id);
    if (!t) return;
    if (current && markers[current]) markers[current].setIcon(icon('trip'));
    markers[id].setIcon(icon('trip', true));
    current = id;
    $('pTitle').textContent = t.title;
    $('pDates').textContent = '· ' + t.dates;
    $('pPrice').textContent = t.price + (t.discount ? '  (-' + t.discount + '%)' : '');
    $('pStars').innerHTML = starsHtml(t.rating);
    $('pReviews').textContent = t.reviews ? t.rating.toFixed(1) + ' (' + t.reviews + ')' : (T.no_reviews_yet || 'No reviews yet');
    $('pInfo').href = 'trip.php?id=' + t.id;
    const join = $('pJoin');
    join.href = 'book.php?trip=' + t.id;
    join.toggleAttribute('disabled', t.seats <= 0);
    if (t.seats <= 0) join.removeAttribute('href');
    $('pSeats').textContent = t.seats > 0 ? (T.seats_left || 'Seats left') + ': ' + t.seats : (T.trip_full || 'Trip full');
    $('tTitle').textContent = t.title;
    $('tBody').textContent = t.transport;
  }

  if (trips.length) {
    const focus = trips.find(t => t.id === window.MAP_FOCUS) || trips[0];
    select(focus.id);
    if (window.MAP_FOCUS && markers[focus.id]) {
      map.setView([focus.lat, focus.lng], 11);
      markers[focus.id].openPopup();
    }
  }
})();
