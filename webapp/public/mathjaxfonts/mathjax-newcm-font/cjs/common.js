"use strict";
var __extends = (this && this.__extends) || (function () {
    var extendStatics = function (d, b) {
        extendStatics = Object.setPrototypeOf ||
            ({ __proto__: [] } instanceof Array && function (d, b) { d.__proto__ = b; }) ||
            function (d, b) { for (var p in b) if (Object.prototype.hasOwnProperty.call(b, p)) d[p] = b[p]; };
        return extendStatics(d, b);
    };
    return function (d, b) {
        if (typeof b !== "function" && b !== null)
            throw new TypeError("Class extends value " + String(b) + " is not a constructor or null");
        extendStatics(d, b);
        function __() { this.constructor = d; }
        d.prototype = b === null ? Object.create(b) : (__.prototype = b.prototype, new __());
    };
})();
var __assign = (this && this.__assign) || function () {
    __assign = Object.assign || function(t) {
        for (var s, i = 1, n = arguments.length; i < n; i++) {
            s = arguments[i];
            for (var p in s) if (Object.prototype.hasOwnProperty.call(s, p))
                t[p] = s[p];
        }
        return t;
    };
    return __assign.apply(this, arguments);
};
var __read = (this && this.__read) || function (o, n) {
    var m = typeof Symbol === "function" && o[Symbol.iterator];
    if (!m) return o;
    var i = m.call(o), r, ar = [], e;
    try {
        while ((n === void 0 || n-- > 0) && !(r = i.next()).done) ar.push(r.value);
    }
    catch (error) { e = { error: error }; }
    finally {
        try {
            if (r && !r.done && (m = i["return"])) m.call(i);
        }
        finally { if (e) throw e.error; }
    }
    return ar;
};
var __spreadArray = (this && this.__spreadArray) || function (to, from, pack) {
    if (pack || arguments.length === 2) for (var i = 0, l = from.length, ar; i < l; i++) {
        if (ar || !(i in from)) {
            if (!ar) ar = Array.prototype.slice.call(from, 0, i);
            ar[i] = from[i];
        }
    }
    return to.concat(ar || Array.prototype.slice.call(from));
};
Object.defineProperty(exports, "__esModule", { value: true });
exports.CommonMathJaxNewcmFontMixin = CommonMathJaxNewcmFontMixin;
var FontData_js_1 = require("@mathjax/src/cjs/output/common/FontData.js");
function CommonMathJaxNewcmFontMixin(Base) {
    var _a;
    return _a = (function (_super) {
            __extends(class_1, _super);
            function class_1() {
                return _super !== null && _super.apply(this, arguments) || this;
            }
            return class_1;
        }(Base)),
        _a.defaultVariants = __spreadArray(__spreadArray([], __read(FontData_js_1.FontData.defaultVariants), false), [
            ['-size3', 'normal'],
            ['-size4', 'normal'],
            ['-size5', 'normal'],
            ['-size6', 'normal'],
            ['-size7', 'normal'],
            ['-lf-tp', 'normal'],
            ['-rt-bt', 'normal'],
            ['-ex-md', 'normal'],
            ['-bbold', 'normal'],
            ['-upsmall', 'normal'],
            ['-uplarge', 'normal']
        ], false),
        _a.VariantSmp = __assign(__assign({}, FontData_js_1.FontData.VariantSmp), { '-bbold': [120120, 120146, , , 120792] }),
        _a.defaultCssFonts = __assign(__assign({}, FontData_js_1.FontData.defaultCssFonts), { '-size3': ['serif', false, false], '-size4': ['serif', false, false], '-size5': ['serif', false, false], '-size6': ['serif', false, false], '-size7': ['serif', false, false], '-lf-tp': ['serif', false, false], '-rt-bt': ['serif', false, false], '-ex-md': ['serif', false, false], '-bbold': ['serif', false, false], '-upsmall': ['serif', false, false], '-uplarge': ['serif', false, false] }),
        _a.defaultAccentMap = {
            0x005E: '\u02C6',
            0x007E: '\u02DC',
            0x0300: '\u02CB',
            0x0301: '\u02CA',
            0x0302: '\u02C6',
            0x0303: '\u02DC',
            0x0304: '\u02C9',
            0x0306: '\u02D8',
            0x0307: '\u02D9',
            0x0308: '\u00A8',
            0x030A: '\u02DA',
            0x030C: '\u02C7',
            0x2192: '\u20D7'
        },
        _a.defaultParams = __assign(__assign({}, FontData_js_1.FontData.defaultParams), { x_height: .442 }),
        _a.defaultSizeVariants = [
            'normal', '-smallop', '-largeop', '-size3', '-size4', '-size5', '-size6', '-size7'
        ],
        _a.defaultStretchVariants = [
            'normal', '-ex-md', '-size3', '-lf-tp', '-rt-bt'
        ],
        _a;
}
//# sourceMappingURL=common.js.map