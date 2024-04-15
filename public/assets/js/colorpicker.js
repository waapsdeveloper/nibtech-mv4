/******/ (() => { // webpackBootstrap
var __webpack_exports__ = {};
/*!********************************************!*\
  !*** ./resources/assets/js/colorpicker.js ***!
  \********************************************/
function _createForOfIteratorHelper(o, allowArrayLike) { var it = typeof Symbol !== "undefined" && o[Symbol.iterator] || o["@@iterator"]; if (!it) { if (Array.isArray(o) || (it = _unsupportedIterableToArray(o)) || allowArrayLike && o && typeof o.length === "number") { if (it) o = it; var i = 0; var F = function F() {}; return { s: F, n: function n() { if (i >= o.length) return { done: true }; return { done: false, value: o[i++] }; }, e: function e(_e2) { throw _e2; }, f: F }; } throw new TypeError("Invalid attempt to iterate non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method."); } var normalCompletion = true, didErr = false, err; return { s: function s() { it = it.call(o); }, n: function n() { var step = it.next(); normalCompletion = step.done; return step; }, e: function e(_e3) { didErr = true; err = _e3; }, f: function f() { try { if (!normalCompletion && it["return"] != null) it["return"](); } finally { if (didErr) throw err; } } }; }

function _slicedToArray(arr, i) { return _arrayWithHoles(arr) || _iterableToArrayLimit(arr, i) || _unsupportedIterableToArray(arr, i) || _nonIterableRest(); }

function _nonIterableRest() { throw new TypeError("Invalid attempt to destructure non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method."); }

function _unsupportedIterableToArray(o, minLen) { if (!o) return; if (typeof o === "string") return _arrayLikeToArray(o, minLen); var n = Object.prototype.toString.call(o).slice(8, -1); if (n === "Object" && o.constructor) n = o.constructor.name; if (n === "Map" || n === "Set") return Array.from(o); if (n === "Arguments" || /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(n)) return _arrayLikeToArray(o, minLen); }

function _arrayLikeToArray(arr, len) { if (len == null || len > arr.length) len = arr.length; for (var i = 0, arr2 = new Array(len); i < len; i++) { arr2[i] = arr[i]; } return arr2; }

function _iterableToArrayLimit(arr, i) { var _i = arr == null ? null : typeof Symbol !== "undefined" && arr[Symbol.iterator] || arr["@@iterator"]; if (_i == null) return; var _arr = []; var _n = true; var _d = false; var _s, _e; try { for (_i = _i.call(arr); !(_n = (_s = _i.next()).done); _n = true) { _arr.push(_s.value); if (i && _arr.length === i) break; } } catch (err) { _d = true; _e = err; } finally { try { if (!_n && _i["return"] != null) _i["return"](); } finally { if (_d) throw _e; } } return _arr; }

function _arrayWithHoles(arr) { if (Array.isArray(arr)) return arr; }

$(function () {
  'use strict';

  var pickrContainer = document.querySelector('.pickr-container');
  var themeContainer = document.querySelector('.theme-container');
  var pickrContainer1 = document.querySelector('.pickr-container1');
  var themeContainer1 = document.querySelector('.theme-container1');
  var pickrContainer2 = document.querySelector('.pickr-container2');
  var themeContainer2 = document.querySelector('.theme-container2'); // classic

  var themes = [['classic', {
    swatches: ['rgba(244, 67, 54, 1)', 'rgba(233, 30, 99, 0.95)', 'rgba(156, 39, 176, 0.9)', 'rgba(103, 58, 183, 0.85)', 'rgba(63, 81, 181, 0.8)', 'rgba(33, 150, 243, 0.75)', 'rgba(3, 169, 244, 0.7)', 'rgba(0, 188, 212, 0.7)', 'rgba(0, 150, 136, 0.75)', 'rgba(76, 175, 80, 0.8)', 'rgba(139, 195, 74, 0.85)', 'rgba(205, 220, 57, 0.9)', 'rgba(255, 235, 59, 0.95)', 'rgba(255, 193, 7, 1)'],
    components: {
      preview: true,
      opacity: true,
      hue: true,
      interaction: {
        hex: true,
        rgba: true,
        hsva: true,
        input: true,
        clear: true,
        save: true
      }
    }
  }]];
  var buttons = [];
  var pickr = null;

  var _loop = function _loop() {
    var _themes$_i = _slicedToArray(_themes[_i], 2),
        theme = _themes$_i[0],
        config = _themes$_i[1];

    var button = document.createElement('button');
    button.innerHTML = theme;
    buttons.push(button);
    button.addEventListener('click', function () {
      var el = document.createElement('p');
      pickrContainer.appendChild(el); // Delete previous instance

      if (pickr) {
        pickr.destroyAndRemove();
      } // Apply active class


      var _iterator = _createForOfIteratorHelper(buttons),
          _step;

      try {
        for (_iterator.s(); !(_step = _iterator.n()).done;) {
          var btn = _step.value;
          btn.classList[btn === button ? 'add' : 'remove']('active');
        } // Create fresh instance

      } catch (err) {
        _iterator.e(err);
      } finally {
        _iterator.f();
      }

      pickr = new Pickr(Object.assign({
        el: el,
        theme: theme,
        "default": '#6c5ffc'
      }, config)); // Set events

      pickr.on('init', function (instance) {
        console.log('Event: "init"', instance);
      }).on('hide', function (instance) {
        console.log('Event: "hide"', instance);
      }).on('show', function (color, instance) {
        console.log('Event: "show"', color, instance);
      }).on('save', function (color, instance) {
        console.log('Event: "save"', color, instance);
      }).on('clear', function (instance) {
        console.log('Event: "clear"', instance);
      }).on('change', function (color, source, instance) {
        console.log('Event: "change"', color, source, instance);
      }).on('changestop', function (source, instance) {
        console.log('Event: "changestop"', source, instance);
      }).on('cancel', function (instance) {
        console.log('cancel', pickr.getColor().toRGBA().toString(0));
      }).on('swatchselect', function (color, instance) {
        console.log('Event: "swatchselect"', color, instance);
      });
    });
    themeContainer.appendChild(button);
  };

  for (var _i = 0, _themes = themes; _i < _themes.length; _i++) {
    _loop();
  }

  buttons[0].click(); // monolith

  var monolithThemes = [['monolith', {
    swatches: ['rgba(244, 67, 54, 1)', 'rgba(233, 30, 99, 0.95)', 'rgba(156, 39, 176, 0.9)', 'rgba(103, 58, 183, 0.85)', 'rgba(63, 81, 181, 0.8)', 'rgba(33, 150, 243, 0.75)', 'rgba(3, 169, 244, 0.7)'],
    defaultRepresentation: 'HEXA',
    components: {
      preview: true,
      opacity: true,
      hue: true,
      interaction: {
        hex: false,
        rgba: false,
        hsva: false,
        input: true,
        clear: true,
        save: true
      }
    }
  }]];
  var monolithButtons = [];
  var monolithPickr = null;

  var _loop2 = function _loop2() {
    var _monolithThemes$_i = _slicedToArray(_monolithThemes[_i2], 2),
        theme = _monolithThemes$_i[0],
        config = _monolithThemes$_i[1];

    var button = document.createElement('button');
    button.innerHTML = theme;
    monolithButtons.push(button);
    button.addEventListener('click', function () {
      var el = document.createElement('p');
      pickrContainer1.appendChild(el); // Delete previous instance

      if (monolithPickr) {
        monolithPickr.destroyAndRemove();
      } // Apply active class


      var _iterator2 = _createForOfIteratorHelper(monolithButtons),
          _step2;

      try {
        for (_iterator2.s(); !(_step2 = _iterator2.n()).done;) {
          var btn = _step2.value;
          btn.classList[btn === button ? 'add' : 'remove']('active');
        } // Create fresh instance

      } catch (err) {
        _iterator2.e(err);
      } finally {
        _iterator2.f();
      }

      monolithPickr = new Pickr(Object.assign({
        el: el,
        theme: theme,
        "default": '#fc5296'
      }, config)); // Set events

      monolithPickr.on('init', function (instance) {
        console.log('Event: "init"', instance);
      }).on('hide', function (instance) {
        console.log('Event: "hide"', instance);
      }).on('show', function (color, instance) {
        console.log('Event: "show"', color, instance);
      }).on('save', function (color, instance) {
        console.log('Event: "save"', color, instance);
      }).on('clear', function (instance) {
        console.log('Event: "clear"', instance);
      }).on('change', function (color, source, instance) {
        console.log('Event: "change"', color, source, instance);
      }).on('changestop', function (source, instance) {
        console.log('Event: "changestop"', source, instance);
      }).on('cancel', function (instance) {
        console.log('cancel', monolithPickr.getColor().toRGBA().toString(0));
      }).on('swatchselect', function (color, instance) {
        console.log('Event: "swatchselect"', color, instance);
      });
    });
    themeContainer1.appendChild(button);
  };

  for (var _i2 = 0, _monolithThemes = monolithThemes; _i2 < _monolithThemes.length; _i2++) {
    _loop2();
  }

  monolithButtons[0].click(); //nano

  var nanoThemes = [['nano', {
    swatches: ['rgba(244, 67, 54, 1)', 'rgba(233, 30, 99, 0.95)', 'rgba(156, 39, 176, 0.9)', 'rgba(103, 58, 183, 0.85)', 'rgba(63, 81, 181, 0.8)', 'rgba(33, 150, 243, 0.75)', 'rgba(3, 169, 244, 0.7)'],
    defaultRepresentation: 'HEXA',
    components: {
      preview: true,
      opacity: true,
      hue: true,
      interaction: {
        hex: false,
        rgba: false,
        hsva: false,
        input: true,
        clear: true,
        save: true
      }
    }
  }]];
  var nanoButtons = [];
  var nanoPickr = null;

  var _loop3 = function _loop3() {
    var _nanoThemes$_i = _slicedToArray(_nanoThemes[_i3], 2),
        theme = _nanoThemes$_i[0],
        config = _nanoThemes$_i[1];

    var button = document.createElement('button');
    button.innerHTML = theme;
    nanoButtons.push(button);
    button.addEventListener('click', function () {
      var el = document.createElement('p');
      pickrContainer2.appendChild(el); // Delete previous instance

      if (nanoPickr) {
        nanoPickr.destroyAndRemove();
      } // Apply active class


      var _iterator3 = _createForOfIteratorHelper(nanoButtons),
          _step3;

      try {
        for (_iterator3.s(); !(_step3 = _iterator3.n()).done;) {
          var btn = _step3.value;
          btn.classList[btn === button ? 'add' : 'remove']('active');
        } // Create fresh instance

      } catch (err) {
        _iterator3.e(err);
      } finally {
        _iterator3.f();
      }

      nanoPickr = new Pickr(Object.assign({
        el: el,
        theme: theme,
        "default": '#05c3fb'
      }, config)); // Set events

      nanoPickr.on('init', function (instance) {
        console.log('Event: "init"', instance);
      }).on('hide', function (instance) {
        console.log('Event: "hide"', instance);
      }).on('show', function (color, instance) {
        console.log('Event: "show"', color, instance);
      }).on('save', function (color, instance) {
        console.log('Event: "save"', color, instance);
      }).on('clear', function (instance) {
        console.log('Event: "clear"', instance);
      }).on('change', function (color, source, instance) {
        console.log('Event: "change"', color, source, instance);
      }).on('changestop', function (source, instance) {
        console.log('Event: "changestop"', source, instance);
      }).on('cancel', function (instance) {
        console.log('cancel', nanoPickr.getColor().toRGBA().toString(0));
      }).on('swatchselect', function (color, instance) {
        console.log('Event: "swatchselect"', color, instance);
      });
    });
    themeContainer2.appendChild(button);
  };

  for (var _i3 = 0, _nanoThemes = nanoThemes; _i3 < _nanoThemes.length; _i3++) {
    _loop3();
  }

  nanoButtons[0].click();
});
/******/ })()
;