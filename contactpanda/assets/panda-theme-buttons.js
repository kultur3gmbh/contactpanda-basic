(function () {
 'use strict';
 // The preview and actual theme-styled form buttons use the same contrast guard.
 function rgb(value) { var values = String(value).match(/[\d.]+/g); return values && values.length >= 3 ? values.slice(0, 3).map(Number) : null; }
 function luminance(color) { return color.reduce(function (sum, value, i) { var c = value / 255; return sum + (c <= .04045 ? c / 12.92 : Math.pow((c + .055) / 1.055, 2.4)) * [.2126, .7152, .0722][i]; }, 0); }
 function enhance(root) {
  (root || document).querySelectorAll('.contactpanda-theme-button-preview.is-theme,.contactpanda-use-theme-styles .contactpanda-registration-submit').forEach(function (button) {
   var style = getComputedStyle(button), bg = rgb(style.backgroundColor), fg = rgb(style.color);
   if (!bg || !fg || style.backgroundImage !== 'none' || /rgba\([^)]*,\s*0\)$/.test(style.backgroundColor)) return;
   var a = luminance(bg), b = luminance(fg);
   if ((Math.max(a, b) + .05) / (Math.min(a, b) + .05) < 4.5) button.style.setProperty('--panda-theme-button-text', (a + .05) / .05 >= 1.05 / (a + .05) ? '#000000' : '#ffffff');
  });
 }
 if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { enhance(document); }); else enhance(document);
 window.addEventListener('load', function () { enhance(document); });
 window.PandaThemeButtons = {enhance: enhance};
})();
