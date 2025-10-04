import {CHARSET, CHARS} from '@mathjax/font-tools/js/CharMap.js';
import {Font, GlyphNames} from '@mathjax/font-tools/js/Font.js';
import {Variants} from '@mathjax/font-tools/js/Variant.js';
import {Delimiters} from '@mathjax/font-tools/js/Delimiters.js';
import {CommonFont, FontDef} from '@mathjax/font-tools/js/CommonFont.js';
import {RANGES} from '@mathjax/font-tools/js/Ranges.js';
import {SVGFont} from '@mathjax/font-tools/js/SVGFont.js';
import {CHTMLFont} from '@mathjax/font-tools/js/CHTMLFont.js';
import {Components} from '@mathjax/font-tools/js/Components.js';

/***********************************************************************************/

try {

  //
  // Name-to-Unicode mapping needed for extra characters
  //
  const charNames: GlyphNames = [
    ['uacute', 0xFA], ['ucircumflex', 0xFB], ['udieresis', 0xFC], ['udotbelow', 0x1EE5],
    ['space_uni0309', 0xEA35], ['space_uni030F', 0xEA26], ['space_uni0323', 0xEB19],
    ['space_uni0326', 0xEA1F], ['space_uni0331', 0xEA3E],
    ['copyleft', 0xEB0F], ['died', 0xEB16], ['threequartersemdash', 0xF6DE],
    ['leaf', 0xEB40], ['perthousandzero', 0xEB4D],
    ['star', 0x22C6], ['S_S', 0x1E9E], ['f_k', 0xE803],
  ];

  const charOptions = {
    charNames,
    ignore: /^\.notdef$|\.(?:sc|sts|dup)$/,
    autoPUA: 0xE780
  };

  Font.load({
    'NCM-M': ['fonts/NewCMMath-Book.otf', charOptions],
    'NCM-R': ['fonts/NewCM10-Regular.otf', charOptions],
    'NCM-B': ['fonts/NewCM10-Bold.otf', charOptions],
    'NCM-I': ['fonts/NewCM10-BookItalic.otf', charOptions],
    'NCM-BI': ['fonts/NewCM10-BoldItalic.otf', charOptions],
    'NCM-SS': ['fonts/NewCMSans10-Book.otf', charOptions],
    'NCM-SSB': ['fonts/NewCMSans10-Bold.otf', charOptions],
    'NCM-SSI': ['fonts/NewCMSans10-BookOblique.otf', charOptions],
    'NCM-SSBI': ['fonts/NewCMSans10-BoldOblique.otf', charOptions],
    'NCM-T': ['fonts/NewCMMono10-Book.otf', charOptions],
    'EXTRA': ['../subsets/MJX-Extra-Regular.otf', {}],
  });

  Font.get('NCM-M')
    .addGlyph(Font.buildV('NCM-M', [[0x21BE, 'tp']], 0x294C, 'uni294C.tp', 0, 175))
    .addGlyph(Font.buildV('NCM-M', [[0x21BF, 'tp']], 0x294D, 'uni294D.tp', 0, 0, 175))
    .addGlyph(Font.buildV('NCM-M', [[0x21C3, 'bt']], 0x294C, 'uni294C.bt', 0, 0, 175))
    .addGlyph(Font.buildV('NCM-M', [[0x21C2, 'bt']], 0x294D, 'uni294D.bt', 0, 175))
    .addGlyph(Font.buildV('NCM-M', [[0x2191, 'ex']], 0x294C, 'uni294C.ex', 0, 58, 58))
    .addGlyph(Font.buildV('NCM-M', [0x221A], 0x221A, 'uni221A.tv', 710));


  /***********************************************************************************/

  //
  //  Operators, arrows, and integrals that should be in largeop
  //
  CHARSET.Ops = CHARS.At(0x2140).feature('v1').plus(
    CHARS.InRange(0x2190, 0x21FF, 'NCM-M', 'v1'),  // arrows
    CHARS.Range(0x220F, 0x2211).feature('v1'),     // operators
    CHARS.Range(0x22C0, 0x22C3).feature('v1'),     // more operators
    CHARS.Range(0x27D5, 0x27D7).feature('v1'),     // more operators
    CHARS.Range(0x29F8, 0x29F9).feature('v1'),     // more operators
    CHARS.Range(0x2A00, 0x2A0A).feature('v1'),     // more operators
    CHARS.InRange(0x2A1D, 0x2A21, 'NCM-M', 'v1'),  // more operators
    CHARS.InRange(0x2AFB, 0x2AFF, 'NCM-M', 'v1'),  // more operators
    CHARS.InRange(0x2B00, 0x2B3F, 'NCM-M', 'v1'),  // more arrows
  );

  CHARSET.Arrows = CHARS.InRange(0x2190, 0x21FF, 'NCM-M', 'h1').plus(
    CHARS.At(0x27A1).feature('h1'),
    CHARS.InRange(0x2B00, 0x2B3F, 'NCM-M', 'h1')
  );

  CHARSET.Integrals = CHARS.Range(0x222B, 0x2233).plus(CHARS.Range(0x2A0B, 0x2A1C));

  //
  //  Accents for use in spacing modifier characters
  //
  CHARSET.SpacingAccents = CHARS.Map({0x2C9: 0xAF, 0x2CA: 0xB4, 0x2CB: 0x60});

  //
  // Braille pattersn
  //
  CHARSET.Braille = CHARS.Range(0x2800, 0x28FF);

  //
  // Characters not to take from the text fonts
  //
  CHARSET.NonNormal = CHARS.At().plus(
    CHARSET.Alpha, CHARSET.Greek, CHARSET.Dotless, CHARSET.SpacingAccents, CHARSET.Braille
  );

  //
  // Characters for -tex-variant
  //
  CHARSET.TeXVariant = CHARS.At(0x2190, 0x2192, 0x2212, 0x221D, 0x223C, 0x2248, 0x2322, 0x2323);

  //
  // The MathJaxNewcm variants
  //
  const MathJaxNewcmVariants = Variants.define({
    normal: [
      ['NCM-M', [
        CHARS.InRange(0x20, 0x4000, 'NCM-M').minus(
          CHARS.At(0xAD),           // replaced below
          CHARS.At(0x2061),         // there is a visible glyph at this location (should be variant form)
          CHARSET.MathScriptUC,     // these are calligraphic
          CHARSET.MathBoldScriptUC  // these are bold calligraphic
        ).minus(CHARS.At(0x275A)),
        CHARSET.SpacingAccents,
        CHARS.Map({
          0x00AD: 0x002D,         // soft hyphen is same as actual hyphen
          0x2758: 0x275A,         // move vertical bar
        }),
        CHARS.InRange(0xE000, 0xFEFF, 'NCM-M'),
        CHARS.InRange(0x1D400, 0x1D7FF, 'NCM-M').minus(
          CHARSET.MathScriptUC,     // these are calligraphic
          CHARSET.MathBoldScriptUC  // these are bold calligraphic
        ),
        CHARS.InRange(0x1EE00, 0x1F8FF, 'NCM-M'),
        CHARSET.MathScriptUC.feature('alt'),
        CHARSET.MathBoldScriptUC.feature('alt')
      ]],
      ['NCM-R', [
        CHARS.InRange(0x20, 0x2FFF, 'NCM-R').minus(CHARS.InRange(0x20, 0x2FFF, 'NCM-M'), CHARSET.SpacingAccents),
        CHARS.InRange(0xE000, 0xFEFF, 'NCM-R').minus(CHARS.InRange(0xE000, 0xFEFF, 'NCM-M'))
      ]]
    ],
    bold: [
      ['NCM-M', [
        CHARSET.Dotless.feature('mrmb')
      ]],
      ['NCM-B', [
        CHARS.InRange(0x20, 0x2FFF, 'NCM-B').minus(CHARSET.NonNormal, CHARSET.Numbers, CHARS.At(0x3DC, 0x3DD)),
        CHARS.InRange(0xE800, 0xFEFF, 'NCM-B'),
        CHARSET.SpacingAccents
      ]]
    ],
    italic: [
      ['NCM-M', [
        CHARS.Map({0x131: 0x1D6A4, 0x237: 0x1D6A5})
      ]],
      ['NCM-I', [
        CHARS.InRange(0x20, 0x2FFF, 'NCM-I').minus(CHARSET.NonNormal),
        CHARS.InRange(0xE800, 0xFEFF, 'NCM-I'),
        CHARSET.SpacingAccents
      ]]
    ],
    'bold-italic': [
      ['NCM-M', [
        CHARSET.Dotless.feature('mitb')
      ]],
      ['NCM-BI', [
        CHARS.InRange(0x20, 0x2FFF, 'NCM-BI').minus(CHARSET.NonNormal),
        CHARS.InRange(0xE800, 0xFEFF, 'NCM-BI'),
        CHARSET.SpacingAccents
      ]]
    ],
    'double-struck': ['NCM-M', [
      CHARSET.Dotless.feature('ds')
    ]],
    'fraktur': ['NCM-M', [
      CHARSET.Dotless.feature('fra')
    ]],
    'bold-fraktur': ['NCM-M', [
      CHARSET.Dotless.feature('frab')
    ]],
    'sans-serif': [
      ['NCM-M', [
        CHARSET.Dotless.feature('ss')
      ]],
      ['NCM-SS', [
        CHARS.InRange(0x20, 0x2FFF, 'NCM-SS').minus(CHARSET.NonNormal, CHARSET.Numbers).plus(CHARSET.Greek),
        CHARS.InRange(0xE800, 0xFEFF, 'NCM-SS'),
        CHARSET.SpacingAccents,
        CHARSET.Braille
      ]]
    ],
    'bold-sans-serif': [
      ['NCM-M', [
        CHARSET.Dotless.feature('ssb')
      ]],
      ['NCM-SSB', [
        CHARS.InRange(0x20, 0x2FFF, 'NCM-SSB').minus(CHARSET.NonNormal, CHARSET.Numbers),
        CHARS.InRange(0xE800, 0xFEFF, 'NCM-SSB'),
        CHARSET.SpacingAccents
      ]]
    ],
    'sans-serif-italic': [
      ['NCM-M', [
        CHARSET.Dotless.feature('sso')
      ]],
      ['NCM-SSI', [
        CHARS.InRange(0x20, 0x2FFF, 'NCM-SSI').minus(CHARSET.NonNormal).plus(CHARSET.Greek),
        CHARS.InRange(0xE800, 0xFEFF, 'NCM-SSI'),
        CHARSET.SpacingAccents
      ]]
    ],
    'sans-serif-bold-italic': [
      ['NCM-M', [
        CHARSET.Dotless.feature('ssbo')
      ]],
      ['NCM-SSBI', [
        CHARS.InRange(0x20, 0x2FFF, 'NCM-SSBI').minus(CHARSET.NonNormal),
        CHARS.InRange(0xE800, 0xFEFF, 'NCM-SSBI'),
        CHARSET.SpacingAccents
      ]]
    ],
    'monospace': [
      ['NCM-M', [
        CHARSET.Dotless.feature('tt')
      ]],
      ['NCM-T', [
        CHARS.InRange(0x20, 0x2FFF, 'NCM-T').minus(CHARSET.NonNormal).plus(CHARSET.Greek),
        CHARS.InRange(0xE800, 0xFEFF, 'NCM-T'),
      ]]
    ],
    '-smallop': ['NCM-M', [
      CHARS.ForFeature('v1', 'NCM-M').minus(CHARSET.Ops, CHARSET.Arrows, CHARSET.Integrals),
      CHARS.ForFeature('h1', 'NCM-M').minus(CHARSET.Arrows)
    ]],
    '-largeop': ['NCM-M', [
      CHARS.ForFeature('v2', 'NCM-M').minus(CHARSET.Ops, CHARSET.Arrows, CHARSET.Integrals),
      CHARS.ForFeature('h2', 'NCM-M').minus(CHARSET.Arrows),
      CHARSET.Integrals.feature('v1'),
      CHARSET.Ops, CHARSET.Arrows,
    ]],
    '-size3': ['NCM-M', [
      CHARS.ForFeature('v3', 'NCM-M'),
      CHARS.ForFeature('h3', 'NCM-M')
    ]],
    '-size4': ['NCM-M', [
      CHARS.ForFeature('v4', 'NCM-M'),
      CHARS.ForFeature('h4', 'NCM-M')
    ]],
    '-size5': ['NCM-M', [
      CHARS.ForFeature('v5', 'NCM-M'),
      CHARS.ForFeature('h5', 'NCM-M')
    ]],
    '-size6': ['NCM-M', [
      CHARS.ForFeature('v6', 'NCM-M'),
      CHARS.ForFeature('h6', 'NCM-M')
    ]],
    '-size7': ['NCM-M', [
      CHARS.ForFeature('v7', 'NCM-M'),
      CHARS.ForFeature('h7', 'NCM-M')
    ]],
    '-tex-mathit': ['NCM-I', [
      CHARSET.Alpha
    ]],
    '-tex-calligraphic': ['NCM-M', [
      CHARSET.ScriptToAlphaUC
    ]],
    '-tex-bold-calligraphic': ['NCM-M', [
      CHARSET.BoldScriptToAlphaUC
    ]],
    '-tex-oldstyle': ['NCM-R', [
      CHARS.MapFrom(0xF730, CHARSET.Numbers).feature('oldstyle')
    ]],
    '-tex-bold-oldstyle': ['NCM-B', [
      CHARS.MapFrom(0xF730, CHARSET.Numbers).feature('oldstyle')
    ]],
    '-tex-variant': [
      ['NCM-M', [
        CHARS.At(0x007E).feature('low'),
        CHARS.At(0x2061),
        CHARS.At(0x2014, 0x22C6).feature('alt'),
        CHARS.Range(0x2032, 0x2037).feature('st'),
        CHARS.At(0x2057).feature('st'),
        CHARSET.PseudoScriptsMain,
        CHARSET.PseudoScriptQuotes.feature('st'),
        CHARS.At(0x221A).feature('tv'),
        CHARS.Map({0x2205: 0x2300}),
      ]],
      ['NCM-I', [
        CHARS.Map({0x210F: 0x127})
      ]],
      ['EXTRA', [
        CHARSET.TeXVariant,
        CHARS.Map({
          0x2216: 0xE01C,
          0x2223: 0xE01A, 0x2224: 0xE010, 0x2225: 0xE01B, 0x2226: 0xE011,
          0x2268: 0xE000, 0x2269: 0xE001,
          0x2270: 0xE008, 0x2271: 0xE009,
          0x2288: 0xE00C, 0x2289: 0xE00D,
          0x228A: 0xE00A, 0x228B: 0xE00B,
          0x2A87: 0xE004, 0x2A88: 0xE005,
          0x2ACB: 0xE00E, 0x2ACC: 0xE00F,
        })
      ]]
    ],
    '-lf-tp': ['NCM-M', [
      CHARS.At(0x20D0, 0x20D6, 0x20ED, 0x20EE,
               0x2190, 0x219A, 0x219E, 0x21A3, 0x21A6, 0x21AA, 0x21AC,
               0x21BC, 0x21BD, 0x21C4, 0x21C6, 0x21C7, 0x21CB, 0x21CC, 0x21CD,
               0x21D0, 0x21DA, 0x21E6, 0x21E8,
               0x2907, 0x2B05, 0x2B31).feature('lft'),
      CHARS.InRange(0x23B4, 0x23E1, 'NCM-M', 'lft'),
      CHARS.At(0x2191, 0x219F, 0x21A7, 0x21BE, 0x21BF,
               0x21C5, 0x21C8, 0x21D1, 0x21E7, 0x21E9, 0x21F5,
               0x221A, 0x27E6, 0x27E7, 0x27EE, 0x27EF, 0x2B06,
               0x294C, 0x294D).feature('tp')
    ]],
    '-rt-bt': ['NCM-M', [
      CHARS.At(0x20D1, 0x20D7, 0x20EC, 0x20EF,
               0x2192, 0x219B, 0x21A0, 0x21A2, 0x21A4, 0x21A9, 0x21AB,
               0x21C0, 0x21C1, 0x21C4, 0x21C6, 0x21C9, 0x21CB, 0x21CC, 0x21CE,
               0x21D2, 0x21DB, 0x21E6, 0x21E8, 0x21F6,
               0x2906, 0x2B0C).feature('rt'),
      CHARS.InRange(0x23B4, 0x23E1, 'NCM-M', 'rt'),
      CHARS.At(0x2193, 0x21A1, 0x21A5, 0x21C2, 0x21C3,
               0x21C5, 0x21CA, 0x21D3, 0x21E7, 0x21E9, 0x21F5,
               0x27E6, 0x27E7, 0x27EE, 0x27EF, 0x2B07,
               0x294C, 0x294D).feature('bt')
    ]],
    '-ex-md': ['NCM-M', [
      CHARS.At(0x007B,
               0x0305, 0x0332, 0x0333, 0x033F, 0x034D, 0x20D0,
               0x2190, 0x2191, 0x21A9, 0x21BC, 0x21BE, 0x21BF,
               0x21C4, 0x21C5, 0x21C7, 0x21C8, 0x21CB,
               0x21D0, 0x21D1, 0x21DA, 0x21E6, 0x21E7, 0x21F6,
               0x221A, 0x21CE,
               0x23B4, 0x23B5, 0x23DC, 0x23DD, 0x23E0, 0x23E1,
               0x27E6, 0x27E7, 0x27EE, 0x27EF, 0x294C,
               0x2B05, 0x2B06).feature('ex'),
      CHARS.At(0x219A, 0x21CD, 0x23DE, 0x23DF).feature('md'),
      CHARS.Map({0x5F: 0x23DF, 0xAF: 0x23DE}).feature('ex')
    ]],
    '-bbold': ['NCM-M', [
      CHARS.ForFeature('sans', 'NCM-M'),
      CHARS.ForFeature('bb', 'NCM-M')
    ]],
    '-upsmall': ['NCM-M', [CHARS.ForFeature('up', 'NCM-M')]],
    '-uplarge': ['NCM-M', [CHARS.ForFeature('v1.up', 'NCM-M')]]
  }, {
    spaces: {
      normal: {0x2061: 0}
    },
    transferHD: [
      [0x2212, 0x002B]    // make minus the same height/depth as plus
    ],
    fixIC: [
      ['-largeop', .33, CHARSET.Integrals.minus(CHARS.At(0x2A0B, 0x2A19, 0x2A1A, 0x2A1B))],
      ['-largeop', .15, CHARS.At(0x2A19, 0x2A1A)],
      ['-largeop', .44, CHARS.At(0x2A1B)],
      ['normal', .12, CHARS.At(0x222B, 0x222C, 0x222D, 0x2A0C, 0x2A15, 0x2A16, 0x2A1B)],
      ['normal', .05, CHARS.At(0x222E, 0x222F, 0x2230, 0x2A0D, 0x2A0E, 0x2A0F, 0x2A10, 0x2A12, 0x2A13, 0x2A14)],
      ['normal', .02, CHARS.At(0x2231, 0x2232, 0x2233, 0x2A11, 0x2A1A, 0x2A1C)]
    ]
  });

  /***********************************************************************************/

  const MathJaxNewcmDelimiters = Delimiters.define({
    font: 'NCM-M',
    variants: MathJaxNewcmVariants,
    sizeVariants: ['normal'],
    stretchVariants: ['normal'],
    readMathTable: true,
    adjustMathTable: {
      0x003D: {parts: [0, [0x003D, ''], 0]},
      0x007C: {parts: [0, [0x2223, 'v3'], 0]},
      0x007D: {parts: [ , 0x007B]},
      0x0305: {parts: [0, , 0]},
      0x0332: {parts: [0, , 0]},
      0x0333: {parts: [0, , 0]},
      0x033F: {parts: [0, , 0]},
      0x034D: {parts: [0x20EE, , 0x20EF]},
      0x2016: {parts: [0, [0x2225, 'v3'], 0]},
      0x20D0: {parts: [ , , 0]},
      0x20D1: {parts: [0, 0x20D0]},
      0x20D6: {parts: [ , 0x20D0, 0]},
      0x20D7: {parts: [0, 0x20D0]},
      0x20E1: {parts: [0x20D6, 0x20D0, 0x20D7]},
      0x20EC: {parts: [0, 0x034D]},
      0x20ED: {parts: [ , 0x034D, 0]},
      0x20EE: {parts: [ , 0x034D, 0]},
      0x20EF: {parts: [0, 0x034D]},
      0x2190: {parts: [ , , 0]},
      0x2191: {parts: [ , , 0]},
      0x2192: {parts: [0, 0x2190]},
      0x2193: {parts: [0, 0x2191]},
      0x2194: {parts: [0x2190, 0x2190, 0x2192]},
      0x2195: {parts: [0x2191, 0x2191, 0x2193]},
      0x219A: {parts: [ , 0x2190, 0]},
      0x219B: {parts: [0, 0x2190, , 0x219A]},
      0x219E: {parts: [ , 0x2190, 0]},
      0x219F: {parts: [ , 0x2191, 0]},
      0x21A0: {parts: [0, 0x2190]},
      0x21A1: {parts: [0, 0x2191]},
      0x21A2: {parts: [0x2190, 0x2190]},
      0x21A3: {parts: [ , 0x2190, 0x2192]},
      0x21A4: {parts: [0x2190, 0x2190]},
      0x21A5: {parts: [0x2191, 0x2191]},
      0x21A6: {parts: [ , 0x2190, 0x2192]},
      0x21A7: {parts: [ , 0x2191, 0x2193]},
      0x21A9: {parts: [0x2190, 0x21A9]},
      0x21AA: {parts: [ , 0x21A9, 0x2192]},
      0x21AB: {parts: [0x2190, 0x21A9]},
      0x21AC: {parts: [ , 0x21A9, 0x2192]},
      0x21AE: {parts: [0x219A, 0x2190, 0x219B, 0x219A]},
      0x21BC: {parts: [ , 0x21BC, 0]},
      0x21BD: {parts: [ , 0x21BC, 0]},
      0x21BE: {parts: [ , , 0]},
      0x21BF: {parts: [ , , 0]},
      0x21C0: {parts: [0, 0x21BC]},
      0x21C1: {parts: [0, 0x21BC]},
      0x21C2: {parts: [0, 0x21BE]},
      0x21C3: {parts: [0, 0x21BF]},
      0x21C6: {parts: [ , 0x21C4]},
      0x21C7: {parts: [ , , 0]},
      0x21C8: {parts: [ , , 0]},
      0x21C9: {parts: [0, 0x21C7]},
      0x21CA: {parts: [0, 0x21C8]},
      0x21CC: {parts: [ , 0x21CB]},
      0x21CD: {parts: [ , 0x21CE, 0]},
      0x21CE: {parts: [0x21D0, , 0x21D2, 0x21CD]},
      0x21CF: {parts: [0, 0x21CE, 0x21D2, 0x21CD]},
      0x21D0: {parts: [ , , 0]},
      0x21D1: {parts: [ , , 0]},
      0x21D2: {parts: [0, 0x21D0]},
      0x21D3: {parts: [0, 0x21D1]},
      0x21D4: {parts: [0x21D0, 0x21D0, 0x21D2]},
      0x21D5: {parts: [0x21D1, 0x21D1, 0x21D3]},
      0x21DA: {parts: [ , , 0]},
      0x21DB: {parts: [0, 0x21DA]},
      0x21E8: {parts: [ , 0x21E6]},
      0x21E9: {parts: [ , 0x21E7]},
      0x21F3: {parts: [0x21E7, 0x21E7, 0x21E9]},
      0x21F5: {parts: [ , 0x21C5]},
      0x21F6: {parts: [0]},
      0x2212: {parts: [0, [0x2212, ''], 0]},
      0x2223: {parts: [0, [0x2223, 'v3'], 0]},
      0x2225: {parts: [0, [0x2225, 'v3'], 0]},
      0x2261: {parts: [0, [0x2261, ''], 0]},
      0x2263: {parts: [0, [0x2263, ''], 0]},
      0x27A1: {parts: [0, 0x2B05, 0x2B0C]},
      0x2906: {parts: [0x21D0, 0x21D0]},
      0x2907: {parts: [, 0x21D0, 0x21D2]},
      0x2B04: {parts: [0x21E6, 0x21E6, 0x21E8]},
      0x2B05: {parts: [ , , 0]},
      0x2B06: {parts: [ , , 0]},
      0x2B07: {parts: [0, 0x2B06]},
      0x2B0C: {parts: [0, 0x2B05]},
      0x2B0D: {parts: [0x2B06, 0x2B06, 0x2B07]},
      0x2B31: {parts: [ , 0x21F6, 0]}
    },
    add: {
      0x2013: {dir: 'H', parts: [0, 0x2013]},
      0x2014: {dir: 'H', parts: [0, 0x2014]},
      0x2015: {dir: 'H', parts: [0, 0x2015]},
      0x2017: {dir: 'H', parts: [0, 0x2017]},
      0x23AA: {dir: 'V', sizes: 1, parts: [0, 0x23AA]},
      0x23B0: {dir: 'V', sizes: 1, parts: [0x23A7, 0x23AA, 0x23AD]},
      0x23B1: {dir: 'V', sizes: 1, parts: [0x23AB, 0x23AA, 0x23A9]},
      0x23D0: {dir: 'V', sizes: 1, parts: [0, 0x23D0]},
      0x294A: {dir: 'H', sizes: 1, parts: [[0x21BC, '-lf-tp'], [0x21BC, '-ex-md'], [0x21C1, '-rt-bt']]},
      0x294B: {dir: 'H', sizes: 1, parts: [[0x21BD, '-lf-tp'], [0x21BC, '-ex-md'], [0x21C0, '-rt-bt']]},
      0x294C: {dir: 'V', sizes: 1, parts: [[0x294C, '-lf-tp'], [0x294C, '-ex-md'], [0x294C, '-rt-bt']]},
      0x294D: {dir: 'V', sizes: 1, parts: [[0x294D, '-lf-tp'], [0x294C, '-ex-md'], [0x294D, '-rt-bt']]},
      0x294E: {dir: 'H', sizes: 1, parts: [[0x21BC, '-lf-tp'], [0x21BC, '-ex-md'], [0x21C0, '-rt-bt']]},
      0x294F: {dir: 'V', sizes: 1, parts: [[0x294C, '-lf-tp'], [0x294C, '-ex-md'], [0x294D, '-rt-bt']]},
      0x2950: {dir: 'H', sizes: 1, parts: [[0x21BD, '-lf-tp'], [0x21BC, '-ex-md'], [0x21C1, '-rt-bt']]},
      0x2951: {dir: 'V', sizes: 1, parts: [[0x294D, '-lf-tp'], [0x294C, '-ex-md'], [0x294C, '-rt-bt']]},
      0x295A: {dir: 'H', sizes: 1, parts: [[0x21BC, '-lf-tp'], [0x21BC, '-ex-md'], [0x21A4, '-rt-bt']]},
      0x295B: {dir: 'H', sizes: 1, parts: [[0x21A6, '-lf-tp'], [0x21BC, '-ex-md'], [0x21C0, '-rt-bt']]},
      0x295C: {dir: 'V', sizes: 1, parts: [[0x294C, '-lf-tp'], [0x294C, '-ex-md'], [0x21A5, '-rt-bt']]},
      0x295D: {dir: 'V', sizes: 1, parts: [[0x21A7, '-lf-tp'], [0x294C, '-ex-md'], [0x294D, '-rt-bt']]},
      0x295E: {dir: 'H', sizes: 1, parts: [[0x21BD, '-lf-tp'], [0x21BC, '-ex-md'], [0x21A4, '-rt-bt']]},
      0x295F: {dir: 'H', sizes: 1, parts: [[0x21A6, '-lf-tp'], [0x21BC, '-ex-md'], [0x21C1, '-rt-bt']]},
      0x2960: {dir: 'V', sizes: 1, parts: [[0x294D, '-lf-tp'], [0x294C, '-ex-md'], [0x21A5, '-rt-bt']]},
      0x2961: {dir: 'V', sizes: 1, parts: [[0x21A7, '-lf-tp'], [0x294C, '-ex-md'], [0x294C, '-rt-bt']]},
    },
    alias: {
      0x002D: 0x2212,
      0x005E: 0x0302,
      0x005F: 0x2013,
      0x007E: 0x0303,
      0x00AF: 0x0305,
      0x02C6: 0x0302,
      0x02C7: 0x030C,
      0x02C9: 0x0305,
      0x02D8: 0x0306,
      0x02DC: 0x0303,
      0x203E: 0x00AF,
      0x2215: 0x002F,
      0x2312: 0x23DC,
      0x2322: 0x23DC,
      0x2323: 0x23DD,
      0x23AF: 0x2013,
      0x2500: 0x2013,
      0x2758: 0x2223,
      0x27F5: 0x2190,
      0x27F6: 0x2192,
      0x27F7: 0x2194,
      0x27F8: 0x21D0,
      0x27F9: 0x21D2,
      0x27FA: 0x21D4,
      0x27FB: 0x21A4,
      0x27FC: 0x21A6,
      0x27FD: 0x2906,
      0x27FE: 0x2907,
      0x3008: 0x27E8,
      0x3009: 0x27E9,
      0xFE37: 0x23DE,
      0xFE38: 0x23DF,
    },
    fullExtenders: {0x221A: [.64, .62 + 1.82]}
  });

  /***********************************************************************************/

  RANGES.SYMBOLS = RANGES.SYMBOLS.filter((n) => Array.isArray(n) || n < 0x258 || n > 0x259);
  RANGES.SYMBOLS.push(0x7F);

  //
  //  Characters in sans-serif to be in extra font
  //
  RANGES.EXTRA = [
      ...RANGES.SYMBOLS,
      ...RANGES.ACCENTS,
      ...(RANGES.SHAPES.slice(4)),  // first four are duplicate with SYMBOLS
      ...RANGES.PUA,
    [0x2190, 0x2193],
    0x221A, 0x2222, 0x2329, 0x232A, 0x25E6,
    0x27E6, 0x27E7
  ];

  //
  //  Characters to be in sans-serif and monospace main fonts
  //
  RANGES.MAIN = [
      ...RANGES.DOTLESS,
    [0x20, 0x7E],
    0xA0, 0xA3, 0xA5, 0xA7, 0xA8, 0xAC, [0xAF, 0xB7], 0xD7, 0xF0, 0xF7,
    0x2C6, 0x2C7, [0x2C9, 0x2CB], [0x2D8, 0x2DA], 0x2DC,
    [0x300, 0x308], 0x30A, 0x30C,
    [0x391, 0x3A9], [0x3B1, 0x3C9], 0x3D1, 0x3D2, 0x3D5, 0x3D6, 0x3F0, 0x3F1, [0x3F4, 0x3F6],
    [0x2010, 0x2016], 0x2018, 0x2019, 0x201C, 0x201D,
    0x2020, 0x2021, 0x2026, 0x2044, 0x20AC, 0x2126, 0x2127,
    [0x2190, 0x2193], 0x2212, 0x221E
  ];

  const MathJaxNewcmData: FontDef = {
    name: 'MathJaxNewcm',
    prefix: 'NCM',
    variants: MathJaxNewcmVariants,
    delimiters: MathJaxNewcmDelimiters,
    variantSmp: {'-bbold': [0x1D538, 0x1D552, , , 0x1D7D8]},
    ranges: [
      ['latin', {LR: {normal: RANGES.LATIN}}],
      ['latin-b', {LB: {bold: RANGES.LATIN}}],
      ['latin-i', {LI: {italic: RANGES.LATIN}}],
      ['latin-bi', {LIB: {'bold-italic': RANGES.LATIN}}],
      ['double-struck', {
        DS: {
          normal: RANGES.DOUBLESTRUCK,
          'double-struck': RANGES.DOTLESS
        }
      }],
      ['fraktur', {
        F: {
          normal: RANGES.FRAKTUR_NORMAL,
          fraktur: RANGES.DOTLESS,
        },
        FB: {
          normal: RANGES.FRAKTUR_BOLD,
          'bold-fraktur': RANGES.DOTLESS
        }
      }],
      ['script', {
        S: {
          normal: RANGES.SCRIPT_NORMAL,
          script: RANGES.DOTLESS,
        },
        SB: {
          normal: RANGES.SCRIPT_BOLD,
          'bold-script': RANGES.DOTLESS
        }
      }],
      ['sans-serif', {
        SS: {
          normal: RANGES.SANSSERIF_NORMAL,
          'sans-serif': RANGES.MAIN,
        },
        SSB: {
          normal: [...RANGES.SANSSERIF_BOLD, [0x1D756, 0x1D78F]],
          'bold-sans-serif': RANGES.MAIN
        },
        SSI: {
          normal: RANGES.SANSSERIF_ITALIC,
          'sans-serif-italic': RANGES.MAIN
        },
        SSBI: {
          normal: [...RANGES.SANSSERIF_BOLDITALIC, [0x1D790, 0x1D7C9]],
          'sans-serif-bold-italic': RANGES.MAIN
        }
      }],
      ['sans-serif-r', {SSLR: {'sans-serif': RANGES.LATIN}}],
      ['sans-serif-b', {SSLB: {'bold-sans-serif': RANGES.LATIN}}],
      ['sans-serif-i', {SSLI: {'sans-serif-italic': RANGES.LATIN}}],
      ['sans-serif-bi', {SSLIB: {'sans-serif-bold-italic': RANGES.LATIN}}],
      ['sans-serif-ex', {
        SSX: {'sans-serif': RANGES.EXTRA},
        SSBX: {'bold-sans-serif': RANGES.EXTRA},
        SSIX: {'sans-serif-italic': RANGES.EXTRA},
        SSBIX: {'sans-serif-bold-italic': RANGES.EXTRA}
      }],
      ['monospace', {M: {
        normal: RANGES.MONOSPACE,
        monospace: RANGES.MAIN
      }}],
      ['monospace-l', {ML: {monospace: RANGES.LATIN}}],
      ['monospace-ex', {
        MX: {monospace: [...RANGES.EXTRA, ...RANGES.PHONETICS, ...RANGES.GREEK, ...RANGES.CYRILLIC]}
      }],
      ['calligraphic', {
        C: {'-tex-calligraphic': RANGES.ALPHAUC},
        CB: {'-tex-bold-calligraphic': RANGES.ALPHAUC}
      }],
      ['math', {MM: {normal: RANGES.MATH}}],
      ['symbols', {SY: {normal: RANGES.SYMBOLS}}, [0x2017]],
      ['symbols-b-i', {
        SYB: {bold: RANGES.SYMBOLS},
        SYI: {italic: RANGES.MORE_SYMBOLS},
        SYBI: {'bold-italic': RANGES.MORE_SYMBOLS}
      }],
      ['greek', {
        GK: {normal: RANGES.GREEK},
        GKB: {bold: RANGES.GREEK},
        GKI: {italic: RANGES.GREEK},
        GKBI: {'bold-italic': RANGES.GREEK}
      }],
      ['greek-ss', {
        GKSS: {'sans-serif': RANGES.GREEK},
        GKSSB: {'bold-sans-serif': RANGES.GREEK},
        GKSSI: {'sans-serif-italic': RANGES.GREEK},
        GKSSBI: {'sans-serif-bold-italic': RANGES.GREEK}
      }],
      ['cyrillic', {
        CY: {normal: RANGES.CYRILLIC},
        CYB: {bold: RANGES.CYRILLIC},
        CYI: {italic: RANGES.CYRILLIC},
        CYBI: {'bold-italic': RANGES.CYRILLIC}
      }],
      ['cyrillic-ss', {
        CYSS: {'sans-serif': RANGES.CYRILLIC},
        CYSSB: {'bold-sans-serif': RANGES.CYRILLIC},
        CYSSI: {'sans-serif-italic': RANGES.CYRILLIC},
        CYSSBI: {'sans-serif-bold-italic': RANGES.CYRILLIC}
      }],
      ['phonetics', {
        PH: {normal: RANGES.PHONETICS},
        PHB: {bold: RANGES.PHONETICS},
        PHI: {italic: RANGES.PHONETICS},
        PHBI: {'bold-italic': RANGES.PHONETICS}
      }],
      ['phonetics-ss', {
        PHSS: {'sans-serif': RANGES.PHONETICS},
        PHSSB: {'bold-sans-serif': RANGES.PHONETICS},
        PHSSI: {'sans-serif-italic': RANGES.PHONETICS},
        PHSSBI: {'sans-serif-bold-italic': RANGES.PHONETICS}
      }],
      ['hebrew', {
        HE: {normal: RANGES.HEBREW},
        HEB: {bold: RANGES.HEBREW},
        HEI: {italic: RANGES.HEBREW},
        HEBI: {'bold-italic': RANGES.HEBREW}
      }],
      ['devanagari', {
        DV: {normal: RANGES.DEVANAGARI},
        DVB: {bold: RANGES.DEVANAGARI},
        DVI: {italic: RANGES.DEVANAGARI},
        DVBI: {'bold-italic': RANGES.DEVANAGARI}
      }],
      ['cherokee', {
        CH: {normal: RANGES.CHEROKEE},
        CHB: {bold: RANGES.CHEROKEE},
        CHI: {italic: RANGES.CHEROKEE},
        CHBI: {'bold-italic': RANGES.CHEROKEE}
      }],
      ['arabic', {
        AB: {normal: RANGES.ARABIC},
        ABB: {bold: RANGES.ARABIC},
        ABI: {italic: RANGES.ARABIC},
        ABBI: {'bold-italic': RANGES.ARABIC}
      }],
      ['braille-d', {brd: {normal: RANGES.BRAILLE}}],
      ['braille', {br: {'sans-serif': RANGES.BRAILLE}}],
      ['arrows', {
        AR: {normal: RANGES.ARROWS},
        ARL: {'-largeop': [[0x2190, 0x21FF], 0x27A3, [0x2B00, 0x2B31]]},
        '': {
          '-lf-tp': [[0x21E6, 0x21E9], 0x2907, 0x2B05, 0x2B06],
          '-rt-bt': [[0x21E6, 0x21E9], 0x2906, 0x2B07, 0x2B0C],
          '-ex-md': [0x21E6, 0x21E7, 0x2B05, 0x2B06]
        }
      }, [
        0x219F, 0x21A1, 0x21A5, 0x21A7, 0x21AD, 0x21AE,
        [0x21B0, 0x21B3], [0x21D6, 0x21D9], 0x21DC, 0x21DD,
        [0x21E6, 0x21E9], 0x21F3, 0x27A1, 0x27FD, 0x27FE,
        0x2906, 0x2907, 0x294C, 0x294D, 0x294F, 0x2951, 0x295C, 0x295D,
        0x2960, 0x2961, [0x2B04, 0x2B07], 0x2B0C, 0x2B0D, 0x2B31
      ]],
      ['marrows', {MAR: {normal: RANGES.MORE_ARROWS}}],
      ['accents', {
        '': {
          normal: RANGES.ACCENTS,
          '-smallop': RANGES.LARGE_ACCENTS,
          '-largeop': RANGES.LARGE_ACCENTS,
          '-size3':   RANGES.LARGE_ACCENTS,
          '-size4':   RANGES.LARGE_ACCENTS,
          '-size5':   RANGES.LARGE_ACCENTS,
          '-size6':   RANGES.LARGE_ACCENTS,
          '-size7':   RANGES.LARGE_ACCENTS,
          '-ex-md':   RANGES.LARGE_ACCENTS
        }
      }, [
        0x311, [0x32C, 0x330], 0x332, 0x333, 0x33F, 0x34D, 0x20E9
      ]],
      ['accents-b-i', {
        AB: {bold: RANGES.ACCENTS},
        AI: {italic: RANGES.MORE_ACCENTS},
        ABI: {'bold-italic': RANGES.MORE_ACCENTS}
      }],
      ['shapes', {
        SH: {normal: RANGES.SHAPES},
        SHB: {bold: RANGES.SHAPES},
        SHI: {italic: RANGES.SHAPES /*[0x266A, 0x26AD, 0x26AE]*/},
        SHBI: {'bold-italic': RANGES.SHAPES /*[0x266A, 0x26AD, 0x26AE]*/}
      }],
      ['mshapes', {MSH: {normal: RANGES.MORE_SHAPES}}],
      ['variants', {
        VX: {'-tex-variant': [[0x20, 0xFF], 0x12A, 0x12B, 0x2014, 0x2016, 0x2044, 0x2061, [0x2070, 0x209F], 0x2E40]}
      }],
      ['PUA', {
        PU: {normal: RANGES.PUA},
        PUB: {bold: RANGES.PUA},
        PUI: {italic: RANGES.PUA},
        PUBI: {'bold-italic': RANGES.PUA}
      }]
    ],
    legal: {
      addCopyright: 'Copyright (c) 2024 MathJax, Inc. (www.mathjax.org)'
    }
  };

  CommonFont.define(MathJaxNewcmData).writeFont();

  Components.define('svg', MathJaxNewcmData).writeFont().writeComponent();
  SVGFont.define(MathJaxNewcmData).writeFont();

  Components.define('chtml', MathJaxNewcmData).writeFont().writeComponent();
  CHTMLFont.define(MathJaxNewcmData).writeFont().makeWoffFonts('NCM-M');

} catch (err) {
  console.log(err);
  process.exit(1);
}

//Font.get('NCM-M').printUnused();
