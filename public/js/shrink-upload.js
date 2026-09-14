/**
 * Shrinks a photo in the browser before it uploads.
 *
 * Quitano photographs the gym on a Pixel; those files are 3-12MB. The server has
 * neither GD nor Imagick, so it cannot resize anything — a big upload would be
 * stored and served at full size, and a gallery of twenty would be a
 * quarter-gigabyte page. Doing it here means he picks the photo he took and
 * never thinks about it again.
 *
 * Degrades honestly: if the browser lacks what this needs, the original file is
 * submitted unchanged and the server's own size check does its job.
 */
(function () {
  var MAX_EDGE = 2000;   // plenty for a full-width gallery image on a 2x screen
  var QUALITY = 0.85;

  function isImage(file) {
    return file && /^image\//.test(file.type) && !/svg/.test(file.type);
  }

  async function shrink(file) {
    if (!isImage(file) || typeof createImageBitmap !== 'function') return file;

    // imageOrientation honours the EXIF rotation, or every photo taken in
    // portrait arrives on its side.
    var bmp;
    try {
      bmp = await createImageBitmap(file, { imageOrientation: 'from-image' });
    } catch (e) {
      return file;
    }

    var scale = Math.min(1, MAX_EDGE / Math.max(bmp.width, bmp.height));
    if (scale === 1 && file.size < 1.5 * 1024 * 1024) { bmp.close(); return file; }

    var w = Math.round(bmp.width * scale);
    var h = Math.round(bmp.height * scale);
    var canvas = document.createElement('canvas');
    canvas.width = w; canvas.height = h;
    canvas.getContext('2d').drawImage(bmp, 0, 0, w, h);
    bmp.close();

    var blob = await new Promise(function (resolve) {
      canvas.toBlob(resolve, 'image/jpeg', QUALITY);
    });
    if (!blob || blob.size >= file.size) return file;   // never make it worse

    var name = file.name.replace(/\.[^.]+$/, '') + '.jpg';
    return new File([blob], name, { type: 'image/jpeg', lastModified: Date.now() });
  }

  function attach(input) {
    var form = input.form;
    if (!form || form.dataset.shrinkBound) return;
    form.dataset.shrinkBound = '1';

    form.addEventListener('submit', async function (e) {
      var inputs = Array.prototype.slice.call(form.querySelectorAll('input[type=file]'))
        .filter(function (i) { return i.files && i.files.length; });
      if (!inputs.length || form.dataset.shrunk === '1') return;

      e.preventDefault();
      var button = form.querySelector('button[type=submit], button:not([type])');
      var original = button ? button.textContent : '';
      if (button) { button.disabled = true; button.textContent = 'Preparing photo…'; }

      try {
        for (var i = 0; i < inputs.length; i++) {
          var field = inputs[i];
          var out = await shrink(field.files[0]);
          if (out !== field.files[0]) {
            var dt = new DataTransfer();
            dt.items.add(out);
            field.files = dt.files;
          }
        }
      } catch (err) {
        // Sending the original beats sending nothing — the server will say if
        // it is too big.
      }

      form.dataset.shrunk = '1';
      if (button) { button.disabled = false; button.textContent = original; }
      form.submit();
    });
  }

  document.querySelectorAll('input[type=file]').forEach(attach);
})();
