(function () {
  var root = document.querySelector('[data-hero-slider]');
  if (!root) return;

  var slides = Array.prototype.slice.call(root.querySelectorAll('[data-hero-slide]'));
  var bgs = Array.prototype.slice.call(root.querySelectorAll('[data-hero-bg]'));
  var dots = Array.prototype.slice.call(root.querySelectorAll('[data-hero-dot]'));
  if (slides.length < 2) return;

  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)');
  var index = 0;
  var timer = null;
  var parsed = parseInt(root.getAttribute('data-hero-autoplay'), 10);
  var DELAY = isNaN(parsed) || parsed < 0 ? 6000 : Math.min(parsed, 60000);

  function show(next) {
    index = ((next % slides.length) + slides.length) % slides.length;

    slides.forEach(function (el, i) {
      var on = i === index;
      el.classList.toggle('opacity-0', !on);
      el.classList.toggle('invisible', !on);
      el.classList.toggle('pointer-events-none', !on);
      el.setAttribute('aria-hidden', on ? 'false' : 'true');
    });

    bgs.forEach(function (el, i) {
      var on = i === index;
      el.classList.toggle('opacity-0', !on);
      el.classList.toggle('invisible', !on);
    });

    dots.forEach(function (el, i) {
      var on = i === index;
      el.setAttribute('aria-current', on ? 'true' : 'false');
      el.classList.toggle('w-7', on);
      el.classList.toggle('bg-secondary-fixed', on);
      el.classList.toggle('w-2', !on);
      el.classList.toggle('bg-surface/40', !on);
    });
  }

  function stop() {
    if (timer !== null) {
      clearInterval(timer);
      timer = null;
    }
  }

  function start() {
    stop();
    if (DELAY === 0 || reduce.matches || document.hidden) return;
    timer = setInterval(function () { show(index + 1); }, DELAY);
  }

  function step(delta) {
    show(index + delta);
    start();
  }

  dots.forEach(function (el) {
    el.addEventListener('click', function () {
      show(parseInt(el.getAttribute('data-hero-dot'), 10) || 0);
      start();
    });
  });

  var prev = root.querySelector('[data-hero-prev]');
  var next = root.querySelector('[data-hero-next]');
  if (prev) prev.addEventListener('click', function () { step(-1); });
  if (next) next.addEventListener('click', function () { step(1); });

  root.addEventListener('mouseenter', stop);
  root.addEventListener('mouseleave', start);
  root.addEventListener('focusin', stop);
  root.addEventListener('focusout', function (e) {
    if (!root.contains(e.relatedTarget)) start();
  });
  root.addEventListener('keydown', function (e) {
    if (e.key === 'ArrowLeft') step(-1);
    else if (e.key === 'ArrowRight') step(1);
  });

  document.addEventListener('visibilitychange', function () {
    if (document.hidden) stop();
    else start();
  });

  if (typeof reduce.addEventListener === 'function') {
    reduce.addEventListener('change', start);
  }

  show(0);
  start();
})();
