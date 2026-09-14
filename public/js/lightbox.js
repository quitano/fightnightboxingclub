/**
 * Click a gallery photo to see it full size.
 *
 * Vanilla, because a lightbox library is 30-80KB to do what fits here in a
 * screenful — and this site loads no other JavaScript.
 *
 * The image shown is the same file as the thumbnail. Thumbnails are cropped
 * square-ish by object-fit, so opening one is the first time you see the whole
 * photograph, not just a bigger copy of the crop.
 */
(function () {
  var figures = Array.prototype.slice.call(document.querySelectorAll('.gallery figure'));
  if (!figures.length) return;

  var items = figures.map(function (fig) {
    var img = fig.querySelector('img');
    var cap = fig.querySelector('figcaption');
    return { src: img ? img.getAttribute('src') : null,
             alt: img ? img.getAttribute('alt') : '',
             caption: cap ? cap.textContent.trim() : '' };
  }).filter(function (i) { return i.src; });

  var index = 0;
  var box = null;
  var lastFocused = null;

  function build() {
    box = document.createElement('div');
    box.className = 'lb';
    box.setAttribute('role', 'dialog');
    box.setAttribute('aria-modal', 'true');
    box.innerHTML =
      '<button class="lb-close" type="button" aria-label="Close">&times;</button>' +
      '<button class="lb-prev" type="button" aria-label="Previous">&#8249;</button>' +
      '<figure class="lb-stage"><img alt=""><figcaption></figcaption></figure>' +
      '<button class="lb-next" type="button" aria-label="Next">&#8250;</button>';
    document.body.appendChild(box);

    box.querySelector('.lb-close').addEventListener('click', close);
    box.querySelector('.lb-prev').addEventListener('click', function (e) { e.stopPropagation(); step(-1); });
    box.querySelector('.lb-next').addEventListener('click', function (e) { e.stopPropagation(); step(1); });
    // Clicking the backdrop closes; clicking the photo itself does not, or you
    // cannot look at it without dismissing it.
    box.addEventListener('click', function (e) { if (e.target === box) close(); });

    var sx = null;
    box.addEventListener('touchstart', function (e) { sx = e.changedTouches[0].clientX; }, { passive: true });
    box.addEventListener('touchend', function (e) {
      if (sx === null) return;
      var dx = e.changedTouches[0].clientX - sx;
      if (Math.abs(dx) > 50) step(dx < 0 ? 1 : -1);
      sx = null;
    }, { passive: true });
  }

  function show() {
    var item = items[index];
    var img = box.querySelector('.lb-stage img');
    img.setAttribute('src', item.src);
    img.setAttribute('alt', item.alt || '');
    var cap = box.querySelector('.lb-stage figcaption');
    cap.textContent = item.caption;
    cap.style.display = item.caption ? '' : 'none';
    // One photo needs no arrows.
    var many = items.length > 1;
    box.querySelector('.lb-prev').style.display = many ? '' : 'none';
    box.querySelector('.lb-next').style.display = many ? '' : 'none';
  }

  function step(by) {
    index = (index + by + items.length) % items.length;
    show();
  }

  function open(i) {
    lastFocused = document.activeElement;
    index = i;
    if (!box) build();
    show();
    box.classList.add('is-open');
    // Stops the page scrolling behind the overlay on a phone.
    document.body.style.overflow = 'hidden';
    box.querySelector('.lb-close').focus();
    document.addEventListener('keydown', onKey);
  }

  function close() {
    box.classList.remove('is-open');
    document.body.style.overflow = '';
    document.removeEventListener('keydown', onKey);
    if (lastFocused && lastFocused.focus) lastFocused.focus();
  }

  function onKey(e) {
    if (e.key === 'Escape') close();
    else if (e.key === 'ArrowLeft') step(-1);
    else if (e.key === 'ArrowRight') step(1);
  }

  figures.forEach(function (fig, i) {
    var img = fig.querySelector('img');
    if (!img) return;
    fig.tabIndex = 0;
    fig.setAttribute('role', 'button');
    fig.setAttribute('aria-label', 'View photo' + (items[i] && items[i].caption ? ': ' + items[i].caption : ''));
    fig.addEventListener('click', function () { open(i); });
    fig.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); open(i); }
    });
  });
})();
