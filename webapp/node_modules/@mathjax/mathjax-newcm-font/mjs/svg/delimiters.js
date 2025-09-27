import { V, H } from '@mathjax/src/mjs/output/common/Direction.js';
export const delimiters = {
    0x28: {
        dir: V,
        sizes: [.997, 1.095, 1.195, 1.445, 1.793, 2.093, 2.393, 2.991],
        stretch: [0x239B, 0x239C, 0x239D],
        HDW: [.748, .248, .875]
    },
    0x29: {
        dir: V,
        sizes: [.997, 1.095, 1.195, 1.445, 1.793, 2.093, 2.393, 2.991],
        stretch: [0x239E, 0x239F, 0x23A0],
        HDW: [.748, .248, .875]
    },
    0x2D: {
        c: 0x2212,
        dir: H,
        stretch: [0, 0x2212],
        HDW: [0.583, 0.083, .778],
        hd: [.583, .083]
    },
    0x2F: {
        dir: V,
        sizes: [1.001, 1.311, 1.717, 2.249, 2.945, 3.859, 5.055, 6.621]
    },
    0x3D: {
        dir: H,
        stretch: [0, 0x3D],
        HDW: [0.367, -0.133, .778],
        hd: [.367, -.133]
    },
    0x5B: {
        dir: V,
        sizes: [1.001, 1.101, 1.201, 1.451, 1.801, 2.101, 2.401, 3.001],
        stretch: [0x23A1, 0x23A2, 0x23A3],
        HDW: [.75, .25, .667]
    },
    0x5C: {
        dir: V,
        sizes: [1.001, 1.311, 1.717, 2.249, 2.945, 3.859, 5.055, 6.621]
    },
    0x5D: {
        dir: V,
        sizes: [1.001, 1.101, 1.201, 1.451, 1.801, 2.101, 2.401, 3.001],
        stretch: [0x23A4, 0x23A5, 0x23A6],
        HDW: [.75, .25, .667]
    },
    0x5E: {
        c: 0x302,
        dir: H,
        sizes: [.5, .644, .768, .919, 1.1, 1.32, 1.581, 1.896]
    },
    0x5F: {
        c: 0x2013,
        dir: H,
        stretch: [0, 0x2013],
        HDW: [0.277, -0.255, .5],
        hd: [.277, -.255]
    },
    0x7B: {
        dir: V,
        sizes: [1.001, 1.101, 1.201, 1.451, 1.801, 2.101, 2.401, 3.001],
        stretch: [0x23A7, 0x7B, 0x23A9, 0x23A8],
        stretchv: [0, 1, 0, 0],
        HDW: [.75, .25, .902]
    },
    0x7C: {
        dir: V,
        sizes: [1.001, 1.203, 1.443, 1.735, 2.085, 2.505, 3.005, 3.605],
        schar: [0x7C, 0x2223],
        stretch: [0, 0x2223],
        stretchv: [0, 2],
        HDW: [.75, .25, .333]
    },
    0x7D: {
        dir: V,
        sizes: [1.001, 1.101, 1.201, 1.451, 1.801, 2.101, 2.401, 3.001],
        stretch: [0x23AB, 0x7B, 0x23AD, 0x23AC],
        stretchv: [0, 1, 0, 0],
        HDW: [.75, .25, .902]
    },
    0x7E: {
        c: 0x303,
        dir: H,
        sizes: [.5, .652, .778, .931, 1.115, 1.335, 1.599, 1.915]
    },
    0xAF: {
        c: 0x305,
        dir: H,
        sizes: [.392, .568],
        stretch: [0, 0x305],
        stretchv: [0, 1],
        HDW: [0.67, -0.63, 0],
        hd: [.67, -.63]
    },
    0x2C6: {
        c: 0x302,
        dir: H,
        sizes: [.5, .644, .768, .919, 1.1, 1.32, 1.581, 1.896]
    },
    0x2C7: {
        c: 0x30C,
        dir: H,
        sizes: [.366, .644, .768, .919, 1.1, 1.32, 1.581, 1.896]
    },
    0x2C9: {
        c: 0x305,
        dir: H,
        sizes: [.392, .568],
        stretch: [0, 0x305],
        stretchv: [0, 1],
        HDW: [0.67, -0.63, 0],
        hd: [.67, -.63]
    },
    0x2D8: {
        c: 0x306,
        dir: H,
        sizes: [.376, .658, .784, .937, 1.12, 1.341, 1.604, 1.92]
    },
    0x2DC: {
        c: 0x303,
        dir: H,
        sizes: [.5, .652, .778, .931, 1.115, 1.335, 1.599, 1.915]
    },
    0x302: {
        dir: H,
        sizes: [.5, .644, .768, .919, 1.1, 1.32, 1.581, 1.896]
    },
    0x303: {
        dir: H,
        sizes: [.5, .652, .778, .931, 1.115, 1.335, 1.599, 1.915]
    },
    0x305: {
        dir: H,
        sizes: [.392, .568],
        stretch: [0, 0x305],
        stretchv: [0, 1],
        HDW: [0.67, -0.63, 0],
        hd: [.67, -.63]
    },
    0x306: {
        dir: H,
        sizes: [.376, .658, .784, .937, 1.12, 1.341, 1.604, 1.92]
    },
    0x30C: {
        dir: H,
        sizes: [.366, .644, .768, .919, 1.1, 1.32, 1.581, 1.896]
    },
    0x2013: {
        dir: H,
        stretch: [0, 0x2013],
        HDW: [0.277, -0.255, .5],
        hd: [.277, -.255]
    },
    0x2014: {
        dir: H,
        stretch: [0, 0x2014],
        HDW: [0.277, -0.255, 1],
        hd: [.277, -.255]
    },
    0x2015: {
        dir: H,
        stretch: [0, 0x2015],
        HDW: [0.27, -0.23, 1.152],
        hd: [.27, -.23]
    },
    0x2016: {
        dir: V,
        sizes: [1.001, 1.203, 1.443, 1.735, 2.085, 2.503, 3.004, 3.607],
        schar: [0x2016, 0x2225],
        stretch: [0, 0x2225],
        stretchv: [0, 2],
        HDW: [.75, .25, .555]
    },
    0x203E: {
        c: 0xAF,
        dir: H,
        sizes: [.392, .568],
        stretch: [0, 0x305],
        stretchv: [0, 1],
        HDW: [0.67, -0.63, 0],
        hd: [.67, -.63]
    },
    0x2044: {
        dir: V,
        sizes: [1.001, 1.311, 1.717, 2.249, 2.945, 3.859, 5.055, 6.621]
    },
    0x20D0: {
        dir: H,
        sizes: [.422, .667],
        stretch: [0x20D0, 0x20D0],
        stretchv: [3, 1],
        HDW: [0.711, -0.601, 0],
        hd: [.631, -.601]
    },
    0x20D1: {
        dir: H,
        sizes: [.422, .667],
        stretch: [0, 0x20D0, 0x20D1],
        stretchv: [0, 1, 4],
        HDW: [0.711, -0.601, 0],
        hd: [.631, -.601]
    },
    0x20D6: {
        dir: H,
        sizes: [.416, .659],
        stretch: [0x20D6, 0x20D0],
        stretchv: [3, 1],
        HDW: [0.711, -0.521, 0],
        hd: [.631, -.601]
    },
    0x20D7: {
        dir: H,
        sizes: [.416, .659],
        stretch: [0, 0x20D0, 0x20D7],
        stretchv: [0, 1, 4],
        HDW: [0.711, -0.521, 0],
        hd: [.631, -.601]
    },
    0x20E1: {
        dir: H,
        sizes: [.47, .715],
        stretch: [0x20D6, 0x20D0, 0x20D7],
        stretchv: [3, 1, 4],
        HDW: [0.711, -0.521, 0],
        hd: [.631, -.601]
    },
    0x20EC: {
        dir: H,
        sizes: [.422, .667],
        stretch: [0, 0x34D, 0x20EC],
        stretchv: [0, 1, 4],
        HDW: [-0.171, 0.281, 0],
        hd: [-.171, .201]
    },
    0x20ED: {
        dir: H,
        sizes: [.422, .667],
        stretch: [0x20ED, 0x34D],
        stretchv: [3, 1],
        HDW: [-0.171, 0.281, 0],
        hd: [-.171, .201]
    },
    0x20EE: {
        dir: H,
        sizes: [.416, .659],
        stretch: [0x20EE, 0x34D],
        stretchv: [3, 1],
        HDW: [-0.091, 0.281, 0],
        hd: [-.171, .201]
    },
    0x20EF: {
        dir: H,
        sizes: [.416, .659],
        stretch: [0, 0x34D, 0x20EF],
        stretchv: [0, 1, 4],
        HDW: [-0.091, 0.281, 0],
        hd: [-.171, .201]
    },
    0x2140: {
        dir: V,
        sizes: [.684, 1.401],
        variants: [0, 2]
    },
    0x2190: {
        dir: H,
        sizes: [1, 1.463],
        variants: [0, 0],
        schar: [0x2190, 0x27F5],
        stretch: [0x2190, 0x2190],
        stretchv: [3, 1],
        HDW: [0.51, 0.01, 1],
        hd: [.274, -.226]
    },
    0x2191: {
        dir: V,
        sizes: [.883, 1.349],
        variants: [0, 2],
        stretch: [0x2191, 0x2191],
        stretchv: [3, 1],
        HDW: [.679, .203, .5]
    },
    0x2192: {
        dir: H,
        sizes: [1, 1.463],
        variants: [0, 0],
        schar: [0x2192, 0x27F6],
        stretch: [0, 0x2190, 0x2192],
        stretchv: [0, 1, 4],
        HDW: [0.51, 0.01, 1],
        hd: [.274, -.226]
    },
    0x2193: {
        dir: V,
        sizes: [.883, 1.349],
        variants: [0, 2],
        stretch: [0, 0x2191, 0x2193],
        stretchv: [0, 1, 4],
        HDW: [.703, .179, .5]
    },
    0x2194: {
        dir: H,
        sizes: [1, 1.442],
        variants: [0, 0],
        schar: [0x2194, 0x27F7],
        stretch: [0x2190, 0x2190, 0x2192],
        stretchv: [3, 1, 4],
        HDW: [0.51, 0.01, 1],
        hd: [.274, -.226]
    },
    0x2195: {
        dir: V,
        sizes: [1.015, 1.015],
        variants: [0, 2],
        stretch: [0x2191, 0x2191, 0x2193],
        stretchv: [3, 1, 4],
        HDW: [.757, .257, .5]
    },
    0x2196: {
        dir: V,
        sizes: [.918, 1.384],
        variants: [0, 2]
    },
    0x2197: {
        dir: V,
        sizes: [.918, 1.384],
        variants: [0, 2]
    },
    0x2198: {
        dir: V,
        sizes: [.918, 1.384],
        variants: [0, 2]
    },
    0x2199: {
        dir: V,
        sizes: [.918, 1.384],
        variants: [0, 2]
    },
    0x219A: {
        dir: H,
        sizes: [.997, 1.463],
        variants: [0, 2],
        stretch: [0x219A, 0x2190, 0, 0x219A],
        stretchv: [3, 1, 0, 1],
        HDW: [0.51, 0.01, .997],
        hd: [.274, -.226]
    },
    0x219B: {
        dir: H,
        sizes: [.997, 1.463],
        variants: [0, 2],
        stretch: [0, 0x2190, 0x219B, 0x219A],
        stretchv: [0, 1, 4, 1],
        HDW: [0.51, 0.01, .997],
        hd: [.274, -.226]
    },
    0x219E: {
        dir: H,
        sizes: [1.017, 1.463],
        variants: [0, 2],
        stretch: [0x219E, 0x2190],
        stretchv: [3, 1],
        HDW: [0.51, 0.01, 1.017],
        hd: [.274, -.226]
    },
    0x21A0: {
        dir: H,
        sizes: [1.017, 1.463],
        variants: [0, 2],
        stretch: [0, 0x2190, 0x21A0],
        stretchv: [0, 1, 4],
        HDW: [0.51, 0.01, 1.017],
        hd: [.274, -.226]
    },
    0x21A2: {
        dir: H,
        sizes: [1.192, 1.658],
        variants: [0, 2],
        stretch: [0x2190, 0x2190, 0x21A2],
        stretchv: [3, 1, 4],
        HDW: [0.51, 0.01, 1.192],
        hd: [.274, -.226]
    },
    0x21A3: {
        dir: H,
        sizes: [1.192, 1.658],
        variants: [0, 2],
        stretch: [0x21A3, 0x2190, 0x2192],
        stretchv: [3, 1, 4],
        HDW: [0.51, 0.01, 1.192],
        hd: [.274, -.226]
    },
    0x21A4: {
        dir: H,
        sizes: [.977, 1.443],
        variants: [0, 0],
        schar: [0x21A4, 0x27FB],
        stretch: [0x2190, 0x2190, 0x21A4],
        stretchv: [3, 1, 4],
        HDW: [0.51, 0.011, .977],
        hd: [.274, -.226]
    },
    0x21A6: {
        dir: H,
        sizes: [.977, 1.443],
        variants: [0, 0],
        schar: [0x21A6, 0x27FC],
        stretch: [0x21A6, 0x2190, 0x2192],
        stretchv: [3, 1, 4],
        HDW: [0.51, 0.011, .977],
        hd: [.274, -.226]
    },
    0x21A9: {
        dir: H,
        sizes: [.997, 1.463],
        variants: [0, 2],
        stretch: [0x2190, 0x21A9, 0x21A9],
        stretchv: [3, 1, 4],
        HDW: [0.546, 0.01, .997],
        hd: [.274, -.226]
    },
    0x21AA: {
        dir: H,
        sizes: [.997, 1.463],
        variants: [0, 2],
        stretch: [0x21AA, 0x21A9, 0x2192],
        stretchv: [3, 1, 4],
        HDW: [0.546, 0.01, .997],
        hd: [.274, -.226]
    },
    0x21AB: {
        dir: H,
        sizes: [.997, 1.463],
        variants: [0, 2],
        stretch: [0x2190, 0x21A9, 0x21AB],
        stretchv: [3, 1, 4],
        HDW: [0.55, 0.05, .997],
        hd: [.274, -.226]
    },
    0x21AC: {
        dir: H,
        sizes: [.997, 1.463],
        variants: [0, 2],
        stretch: [0x21AC, 0x21A9, 0x2192],
        stretchv: [3, 1, 4],
        HDW: [0.55, 0.05, .997],
        hd: [.274, -.226]
    },
    0x21B6: {
        dir: H,
        sizes: [.98, 1.33],
        variants: [0, 2]
    },
    0x21B7: {
        dir: H,
        sizes: [.98, 1.33],
        variants: [0, 2]
    },
    0x21BC: {
        dir: H,
        sizes: [1, 1.478],
        variants: [0, 2],
        stretch: [0x21BC, 0x21BC],
        stretchv: [3, 1],
        HDW: [0.499, -0.226, 1],
        hd: [.273, -.226]
    },
    0x21BD: {
        dir: H,
        sizes: [1.012, 1.478],
        variants: [0, 2],
        stretch: [0x21BD, 0x21BC],
        stretchv: [3, 1],
        HDW: [0.273, 0, 1.012],
        hd: [.273, -.226]
    },
    0x21BE: {
        dir: V,
        sizes: [.901, 1.367],
        variants: [0, 2],
        stretch: [0x21BE, 0x21BE],
        stretchv: [3, 1],
        HDW: [.697, .203, .441]
    },
    0x21BF: {
        dir: V,
        sizes: [.901, 1.367],
        variants: [0, 2],
        stretch: [0x21BF, 0x21BF],
        stretchv: [3, 1],
        HDW: [.697, .203, .441]
    },
    0x21C0: {
        dir: H,
        sizes: [1, 1.478],
        variants: [0, 2],
        stretch: [0, 0x21BC, 0x21C0],
        stretchv: [0, 1, 4],
        HDW: [0.499, -0.226, 1],
        hd: [.273, -.226]
    },
    0x21C1: {
        dir: H,
        sizes: [1.012, 1.478],
        variants: [0, 2],
        stretch: [0, 0x21BC, 0x21C1],
        stretchv: [0, 1, 4],
        HDW: [0.273, 0, 1.012],
        hd: [.273, -.226]
    },
    0x21C2: {
        dir: V,
        sizes: [.901, 1.367],
        variants: [0, 2],
        stretch: [0, 0x21BE, 0x21C2],
        stretchv: [0, 1, 4],
        HDW: [.703, .197, .441]
    },
    0x21C3: {
        dir: V,
        sizes: [.901, 1.367],
        variants: [0, 2],
        stretch: [0, 0x21BF, 0x21C3],
        stretchv: [0, 1, 4],
        HDW: [.703, .197, .441]
    },
    0x21C4: {
        dir: H,
        sizes: [1.018, 1.484],
        variants: [0, 2],
        stretch: [0x21C4, 0x21C4, 0x21C4],
        stretchv: [3, 1, 4],
        HDW: [0.669, 0.172, 1.018],
        hd: [.432, -.065]
    },
    0x21C5: {
        dir: V,
        sizes: [.907, 1.373],
        variants: [0, 2],
        stretch: [0x21C5, 0x21C5, 0x21C5],
        stretchv: [3, 1, 4],
        HDW: [.703, .203, .896]
    },
    0x21C6: {
        dir: H,
        sizes: [1.018, 1.484],
        variants: [0, 2],
        stretch: [0x21C6, 0x21C4, 0x21C6],
        stretchv: [3, 1, 4],
        HDW: [0.669, 0.172, 1.018],
        hd: [.432, -.065]
    },
    0x21C7: {
        dir: H,
        sizes: [.997, 1.463],
        variants: [0, 2],
        stretch: [0x21C7, 0x21C7],
        stretchv: [3, 1],
        HDW: [0.75, 0.25, .997],
        hd: [.512, .012]
    },
    0x21C8: {
        dir: V,
        sizes: [.883, 1.349],
        variants: [0, 2],
        stretch: [0x21C8, 0x21C8],
        stretchv: [3, 1],
        HDW: [.679, .203, .992]
    },
    0x21C9: {
        dir: H,
        sizes: [.997, 1.463],
        variants: [0, 2],
        stretch: [0, 0x21C7, 0x21C9],
        stretchv: [0, 1, 4],
        HDW: [0.75, 0.25, .997],
        hd: [.512, .012]
    },
    0x21CA: {
        dir: V,
        sizes: [.883, 1.349],
        variants: [0, 2],
        stretch: [0, 0x21C8, 0x21CA],
        stretchv: [0, 1, 4],
        HDW: [.703, .179, .992]
    },
    0x21CB: {
        dir: H,
        sizes: [1.018, 1.484],
        variants: [0, 2],
        stretch: [0x21CB, 0x21CB, 0x21CB],
        stretchv: [3, 1, 4],
        HDW: [0.598, 0.098, 1.018],
        hd: [.369, -.131]
    },
    0x21CC: {
        dir: H,
        sizes: [1.018, 1.484],
        variants: [0, 2],
        stretch: [0x21CC, 0x21CB, 0x21CC],
        stretchv: [3, 1, 4],
        HDW: [0.598, 0.098, 1.018],
        hd: [.369, -.131]
    },
    0x21CD: {
        dir: H,
        sizes: [.991, 1.457],
        variants: [0, 2],
        stretch: [0x21CD, 0x21CE, 0, 0x21CD],
        stretchv: [3, 1, 0, 1],
        HDW: [0.52, 0.02, .991],
        hd: [.369, -.131]
    },
    0x21CE: {
        dir: H,
        sizes: [1.068, 1.534],
        variants: [0, 2],
        stretch: [0x21D0, 0x21CE, 0x21D2, 0x21CD],
        stretchv: [3, 1, 4, 1],
        HDW: [0.52, 0.02, 1.068],
        hd: [.369, -.131]
    },
    0x21CF: {
        dir: H,
        sizes: [.991, 1.457],
        variants: [0, 2],
        stretch: [0, 0x21CE, 0x21D2, 0x21CD],
        stretchv: [0, 1, 4, 1],
        HDW: [0.52, 0.02, .991],
        hd: [.369, -.131]
    },
    0x21D0: {
        dir: H,
        sizes: [1, 1.457],
        variants: [0, 0],
        schar: [0x21D0, 0x27F8],
        stretch: [0x21D0, 0x21D0],
        stretchv: [3, 1],
        HDW: [0.52, 0.02, 1],
        hd: [.369, -.131]
    },
    0x21D1: {
        dir: V,
        sizes: [.88, 1.346],
        variants: [0, 2],
        stretch: [0x21D1, 0x21D1],
        stretchv: [3, 1],
        HDW: [.676, .203, .652]
    },
    0x21D2: {
        dir: H,
        sizes: [1, 1.457],
        variants: [0, 0],
        schar: [0x21D2, 0x27F9],
        stretch: [0, 0x21D0, 0x21D2],
        stretchv: [0, 1, 4],
        HDW: [0.52, 0.02, 1],
        hd: [.369, -.131]
    },
    0x21D3: {
        dir: V,
        sizes: [.88, 1.346],
        variants: [0, 2],
        stretch: [0, 0x21D1, 0x21D3],
        stretchv: [0, 1, 4],
        HDW: [.703, .176, .652]
    },
    0x21D4: {
        dir: H,
        sizes: [1, 1.534],
        variants: [0, 0],
        schar: [0x21D4, 0x27FA],
        stretch: [0x21D0, 0x21D0, 0x21D2],
        stretchv: [3, 1, 4],
        HDW: [0.52, 0.02, 1],
        hd: [.369, -.131]
    },
    0x21D5: {
        dir: V,
        sizes: [.957, 1.423],
        variants: [0, 2],
        stretch: [0x21D1, 0x21D1, 0x21D3],
        stretchv: [3, 1, 4],
        HDW: [.728, .228, .652]
    },
    0x21DA: {
        dir: H,
        sizes: [1.015, 1.461],
        variants: [0, 2],
        stretch: [0x21DA, 0x21DA],
        stretchv: [3, 1],
        HDW: [0.617, 0.117, 1.015],
        hd: [.466, -.034]
    },
    0x21DB: {
        dir: H,
        sizes: [1.015, 1.461],
        variants: [0, 2],
        stretch: [0, 0x21DA, 0x21DB],
        stretchv: [0, 1, 4],
        HDW: [0.617, 0.117, 1.015],
        hd: [.466, -.034]
    },
    0x21F5: {
        dir: V,
        sizes: [.907, 1.373],
        variants: [0, 2],
        stretch: [0x21F5, 0x21C5, 0x21F5],
        stretchv: [3, 1, 4],
        HDW: [.703, .203, .896]
    },
    0x21F6: {
        dir: H,
        sizes: [.997, 1.463],
        variants: [0, 2],
        stretch: [0, 0x21F6, 0x21F6],
        stretchv: [0, 1, 4],
        HDW: [0.99, 0.49, .997],
        hd: [.751, .251]
    },
    0x220F: {
        dir: V,
        sizes: [1.001, 1.401],
        variants: [0, 2]
    },
    0x2210: {
        dir: V,
        sizes: [1.001, 1.401],
        variants: [0, 2]
    },
    0x2211: {
        dir: V,
        sizes: [1.001, 1.401],
        variants: [0, 2]
    },
    0x2212: {
        dir: H,
        stretch: [0, 0x2212],
        HDW: [0.583, 0.083, .778],
        hd: [.583, .083]
    },
    0x2215: {
        c: 0x2F,
        dir: V,
        sizes: [1.001, 1.311, 1.717, 2.249, 2.945, 3.859, 5.055, 6.621]
    },
    0x221A: {
        dir: V,
        sizes: [1.001, 1.201, 1.801, 2.401, 3.001],
        stretch: [0x221A, 0x221A, 0x23B7],
        stretchv: [3, 1, 0],
        HDW: [.04, .96, 1.056],
        fullExt: [0.64, 2.44]
    },
    0x2223: {
        dir: V,
        sizes: [1.001, 1.203, 1.443, 1.735, 2.085, 2.505, 3.005, 3.605],
        stretch: [0, 0x2223],
        stretchv: [0, 2],
        HDW: [.75, .25, .333]
    },
    0x2225: {
        dir: V,
        sizes: [1.001, 1.203, 1.443, 1.735, 2.085, 2.503, 3.004, 3.607],
        stretch: [0, 0x2225],
        stretchv: [0, 2],
        HDW: [.75, .25, .555]
    },
    0x222B: {
        dir: V,
        sizes: [1.112, 2.223],
        variants: [0, 2],
        stretch: [0x2320, 0x23AE, 0x2321],
        HDW: [.805, .306, 1.185]
    },
    0x222C: {
        dir: V,
        sizes: [1.112, 2.223],
        variants: [0, 2]
    },
    0x222D: {
        dir: V,
        sizes: [1.112, 2.223],
        variants: [0, 2]
    },
    0x222E: {
        dir: V,
        sizes: [1.112, 2.223],
        variants: [0, 2]
    },
    0x222F: {
        dir: V,
        sizes: [1.112, 2.223],
        variants: [0, 2]
    },
    0x2230: {
        dir: V,
        sizes: [1.112, 2.223],
        variants: [0, 2]
    },
    0x2231: {
        dir: V,
        sizes: [1.112, 2.223],
        variants: [0, 2]
    },
    0x2232: {
        dir: V,
        sizes: [1.112, 2.223],
        variants: [0, 2]
    },
    0x2233: {
        dir: V,
        sizes: [1.112, 2.223],
        variants: [0, 2]
    },
    0x2261: {
        dir: H,
        stretch: [0, 0x2261],
        HDW: [0.464, -0.036, .778],
        hd: [.464, -.036]
    },
    0x2263: {
        dir: H,
        stretch: [0, 0x2263],
        HDW: [0.561, 0.061, .778],
        hd: [.561, .061]
    },
    0x22A2: {
        dir: V,
        sizes: [.685, .869],
        variants: [0, 0],
        schar: [0x22A2, 0x27DD]
    },
    0x22A3: {
        dir: V,
        sizes: [.685, .869],
        variants: [0, 0],
        schar: [0x22A3, 0x27DE]
    },
    0x22A4: {
        dir: V,
        sizes: [.685, .869],
        variants: [0, 0],
        schar: [0x22A4, 0x27D9]
    },
    0x22A5: {
        dir: V,
        sizes: [.685, .869],
        variants: [0, 0],
        schar: [0x22A5, 0x27D8]
    },
    0x22C0: {
        dir: V,
        sizes: [1.045, 1.394],
        variants: [0, 2]
    },
    0x22C1: {
        dir: V,
        sizes: [1.045, 1.394],
        variants: [0, 2]
    },
    0x22C2: {
        dir: V,
        sizes: [1.023, 1.357],
        variants: [0, 2]
    },
    0x22C3: {
        dir: V,
        sizes: [1.023, 1.357],
        variants: [0, 2]
    },
    0x2308: {
        dir: V,
        sizes: [1.001, 1.101, 1.201, 1.451, 1.801, 2.101, 2.401, 3.001],
        stretch: [0x23A1, 0x23A2],
        HDW: [.75, .25, .667]
    },
    0x2309: {
        dir: V,
        sizes: [1.001, 1.101, 1.201, 1.451, 1.801, 2.101, 2.401, 3.001],
        stretch: [0x23A4, 0x23A5],
        HDW: [.75, .25, .667]
    },
    0x230A: {
        dir: V,
        sizes: [1.001, 1.101, 1.201, 1.451, 1.801, 2.101, 2.401, 3.001],
        stretch: [0, 0x23A2, 0x23A3],
        HDW: [.75, .25, .667]
    },
    0x230B: {
        dir: V,
        sizes: [1.001, 1.101, 1.201, 1.451, 1.801, 2.101, 2.401, 3.001],
        stretch: [0, 0x23A5, 0x23A6],
        HDW: [.75, .25, .667]
    },
    0x2312: {
        c: 0x23DC,
        dir: H,
        sizes: [.504, 1.006, 1.508, 2.012, 2.516, 3.02, 3.524, 4.032],
        stretch: [0x23DC, 0x23DC, 0x23DC],
        stretchv: [3, 1, 4],
        HDW: [0.796, -0.502, .504],
        hd: [.796, -.689]
    },
    0x2322: {
        c: 0x23DC,
        dir: H,
        sizes: [.504, 1.006, 1.508, 2.012, 2.516, 3.02, 3.524, 4.032],
        stretch: [0x23DC, 0x23DC, 0x23DC],
        stretchv: [3, 1, 4],
        HDW: [0.796, -0.502, .504],
        hd: [.796, -.689]
    },
    0x2323: {
        c: 0x23DD,
        dir: H,
        sizes: [.504, 1.006, 1.508, 2.012, 2.516, 3.02, 3.524, 4.032],
        stretch: [0x23DD, 0x23DD, 0x23DD],
        stretchv: [3, 1, 4],
        HDW: [-0.072, 0.366, .504],
        hd: [-.259, .366]
    },
    0x2329: {
        dir: V,
        sizes: [1.001, 1.101, 1.201, 1.451, 1.801, 2.101, 2.401, 3.001],
        schar: [0x2329, 0x27E8]
    },
    0x232A: {
        dir: V,
        sizes: [1.001, 1.101, 1.201, 1.451, 1.801, 2.101, 2.401, 3.001],
        schar: [0x232A, 0x27E9]
    },
    0x23AA: {
        dir: V,
        sizes: [.748],
        stretch: [0, 0x23AA],
        HDW: [.748, 0, .902]
    },
    0x23AF: {
        c: 0x2013,
        dir: H,
        stretch: [0, 0x2013],
        HDW: [0.277, -0.255, .5],
        hd: [.277, -.255]
    },
    0x23B0: {
        dir: V,
        sizes: [1.125],
        stretch: [0x23A7, 0x23AA, 0x23AD],
        HDW: [.75, .375, .902]
    },
    0x23B1: {
        dir: V,
        sizes: [1.125],
        stretch: [0x23AB, 0x23AA, 0x23A9],
        HDW: [.75, .375, .902]
    },
    0x23B4: {
        dir: H,
        sizes: [.36, .735, 1.11, 1.485, 1.86, 2.235, 2.61, 2.985],
        stretch: [0x23B4, 0x23B4, 0x23B4],
        stretchv: [3, 1, 4],
        HDW: [0.772, -0.504, .36],
        hd: [.772, -.706]
    },
    0x23B5: {
        dir: H,
        sizes: [.36, .735, 1.11, 1.485, 1.86, 2.235, 2.61, 2.985],
        stretch: [0x23B5, 0x23B5, 0x23B5],
        stretchv: [3, 1, 4],
        HDW: [-0.074, 0.342, .36],
        hd: [-.276, .342]
    },
    0x23D0: {
        dir: V,
        sizes: [.642],
        stretch: [0, 0x23D0],
        HDW: [.642, 0, .333]
    },
    0x23DC: {
        dir: H,
        sizes: [.504, 1.006, 1.508, 2.012, 2.516, 3.02, 3.524, 4.032],
        stretch: [0x23DC, 0x23DC, 0x23DC],
        stretchv: [3, 1, 4],
        HDW: [0.796, -0.502, .504],
        hd: [.796, -.689]
    },
    0x23DD: {
        dir: H,
        sizes: [.504, 1.006, 1.508, 2.012, 2.516, 3.02, 3.524, 4.032],
        stretch: [0x23DD, 0x23DD, 0x23DD],
        stretchv: [3, 1, 4],
        HDW: [-0.072, 0.366, .504],
        hd: [-.259, .366]
    },
    0x23DE: {
        dir: H,
        sizes: [.492, .993, 1.494, 1.996, 2.498, 3, 3.502, 4.006],
        stretch: [0x23DE, 0xAF, 0x23DE, 0x23DE],
        stretchv: [3, 1, 4, 1],
        HDW: [0.85, -0.493, .492],
        hd: [.724, -.618]
    },
    0x23DF: {
        dir: H,
        sizes: [.492, .993, 1.494, 1.996, 2.498, 3, 3.502, 4.006],
        stretch: [0x23DF, 0x5F, 0x23DF, 0x23DF],
        stretchv: [3, 1, 4, 1],
        HDW: [-0.062, 0.419, .492],
        hd: [-.188, .294]
    },
    0x23E0: {
        dir: H,
        sizes: [.546, 1.048, 1.55, 2.056, 2.564, 3.068, 3.574, 4.082],
        stretch: [0x23E0, 0x23E0, 0x23E0],
        stretchv: [3, 1, 4],
        HDW: [0.873, -0.605, .546],
        hd: [.873, -.766]
    },
    0x23E1: {
        dir: H,
        sizes: [.546, 1.048, 1.55, 2.056, 2.564, 3.068, 3.574, 4.082],
        stretch: [0x23E1, 0x23E1, 0x23E1],
        stretchv: [3, 1, 4],
        HDW: [-0.175, 0.443, .546],
        hd: [-.336, .443]
    },
    0x2500: {
        c: 0x2013,
        dir: H,
        stretch: [0, 0x2013],
        HDW: [0.277, -0.255, .5],
        hd: [.277, -.255]
    },
    0x2758: {
        c: 0x2223,
        dir: V,
        sizes: [1.001, 1.203, 1.443, 1.735, 2.085, 2.505, 3.005, 3.605],
        stretch: [0, 0x2223],
        stretchv: [0, 2],
        HDW: [.75, .25, .333]
    },
    0x27D5: {
        dir: V,
        sizes: [.511, .628],
        variants: [0, 2]
    },
    0x27D6: {
        dir: V,
        sizes: [.511, .628],
        variants: [0, 2]
    },
    0x27D7: {
        dir: V,
        sizes: [.511, .628],
        variants: [0, 2]
    },
    0x27E6: {
        dir: V,
        sizes: [1.001, 1.101, 1.201, 1.451, 1.801, 2.101, 2.401, 3.001],
        stretch: [0x27E6, 0x27E6, 0x27E6],
        stretchv: [3, 1, 4],
        HDW: [.75, .25, 1.007]
    },
    0x27E7: {
        dir: V,
        sizes: [1.001, 1.101, 1.201, 1.451, 1.801, 2.101, 2.401, 3.001],
        stretch: [0x27E7, 0x27E7, 0x27E7],
        stretchv: [3, 1, 4],
        HDW: [.75, .25, 1.007]
    },
    0x27E8: {
        dir: V,
        sizes: [1.001, 1.101, 1.201, 1.451, 1.801, 2.101, 2.401, 3.001]
    },
    0x27E9: {
        dir: V,
        sizes: [1.001, 1.101, 1.201, 1.451, 1.801, 2.101, 2.401, 3.001]
    },
    0x27EA: {
        dir: V,
        sizes: [1.001, 1.101, 1.201, 1.451, 1.801, 2.101, 2.401, 3.001]
    },
    0x27EB: {
        dir: V,
        sizes: [1.001, 1.101, 1.201, 1.451, 1.801, 2.101, 2.401, 3.001]
    },
    0x27EE: {
        dir: V,
        sizes: [1.025, 1.127, 1.229, 1.483, 1.837, 2.141, 2.445, 3.053],
        stretch: [0x27EE, 0x27EE, 0x27EE],
        stretchv: [3, 1, 4],
        HDW: [.762, .262, .647]
    },
    0x27EF: {
        dir: V,
        sizes: [1.025, 1.127, 1.229, 1.483, 1.837, 2.141, 2.445, 3.053],
        stretch: [0x27EF, 0x27EF, 0x27EF],
        stretchv: [3, 1, 4],
        HDW: [.762, .262, .647]
    },
    0x27F5: {
        c: 0x2190,
        dir: H,
        sizes: [1, 1.463],
        variants: [0, 0],
        schar: [0x2190, 0x27F5],
        stretch: [0x2190, 0x2190],
        stretchv: [3, 1],
        HDW: [0.51, 0.01, 1],
        hd: [.274, -.226]
    },
    0x27F6: {
        c: 0x2192,
        dir: H,
        sizes: [1, 1.463],
        variants: [0, 0],
        schar: [0x2192, 0x27F6],
        stretch: [0, 0x2190, 0x2192],
        stretchv: [0, 1, 4],
        HDW: [0.51, 0.01, 1],
        hd: [.274, -.226]
    },
    0x27F7: {
        c: 0x2194,
        dir: H,
        sizes: [1, 1.442],
        variants: [0, 0],
        schar: [0x2194, 0x27F7],
        stretch: [0x2190, 0x2190, 0x2192],
        stretchv: [3, 1, 4],
        HDW: [0.51, 0.01, 1],
        hd: [.274, -.226]
    },
    0x27F8: {
        c: 0x21D0,
        dir: H,
        sizes: [1, 1.457],
        variants: [0, 0],
        schar: [0x21D0, 0x27F8],
        stretch: [0x21D0, 0x21D0],
        stretchv: [3, 1],
        HDW: [0.52, 0.02, 1],
        hd: [.369, -.131]
    },
    0x27F9: {
        c: 0x21D2,
        dir: H,
        sizes: [1, 1.457],
        variants: [0, 0],
        schar: [0x21D2, 0x27F9],
        stretch: [0, 0x21D0, 0x21D2],
        stretchv: [0, 1, 4],
        HDW: [0.52, 0.02, 1],
        hd: [.369, -.131]
    },
    0x27FA: {
        c: 0x21D4,
        dir: H,
        sizes: [1, 1.534],
        variants: [0, 0],
        schar: [0x21D4, 0x27FA],
        stretch: [0x21D0, 0x21D0, 0x21D2],
        stretchv: [3, 1, 4],
        HDW: [0.52, 0.02, 1],
        hd: [.369, -.131]
    },
    0x27FB: {
        c: 0x21A4,
        dir: H,
        sizes: [.977, 1.443],
        variants: [0, 0],
        schar: [0x21A4, 0x27FB],
        stretch: [0x2190, 0x2190, 0x21A4],
        stretchv: [3, 1, 4],
        HDW: [0.51, 0.011, .977],
        hd: [.274, -.226]
    },
    0x27FC: {
        c: 0x21A6,
        dir: H,
        sizes: [.977, 1.443],
        variants: [0, 0],
        schar: [0x21A6, 0x27FC],
        stretch: [0x21A6, 0x2190, 0x2192],
        stretchv: [3, 1, 4],
        HDW: [0.51, 0.011, .977],
        hd: [.274, -.226]
    },
    0x294A: {
        dir: H,
        sizes: [1.012],
        stretch: [0x21BC, 0x21BC, 0x21C1],
        stretchv: [3, 1, 4],
        HDW: [0.499, 0, 1.012],
        hd: [.273, -.226]
    },
    0x294B: {
        dir: H,
        sizes: [1.012],
        stretch: [0x21BD, 0x21BC, 0x21C0],
        stretchv: [3, 1, 4],
        HDW: [0.499, 0, 1.012],
        hd: [.273, -.226]
    },
    0x294E: {
        dir: H,
        sizes: [1],
        stretch: [0x21BC, 0x21BC, 0x21C0],
        stretchv: [3, 1, 4],
        HDW: [0.499, -0.226, 1],
        hd: [.273, -.226]
    },
    0x2950: {
        dir: H,
        sizes: [1],
        stretch: [0x21BD, 0x21BC, 0x21C1],
        stretchv: [3, 1, 4],
        HDW: [0.273, 0, 1],
        hd: [.273, -.226]
    },
    0x295A: {
        dir: H,
        sizes: [1],
        stretch: [0x21BC, 0x21BC, 0x21A4],
        stretchv: [3, 1, 4],
        HDW: [0.51, 0.011, 1],
        hd: [.273, -.226]
    },
    0x295B: {
        dir: H,
        sizes: [1],
        stretch: [0x21A6, 0x21BC, 0x21C0],
        stretchv: [3, 1, 4],
        HDW: [0.51, 0.011, 1],
        hd: [.273, -.226]
    },
    0x295E: {
        dir: H,
        sizes: [1],
        stretch: [0x21BD, 0x21BC, 0x21A4],
        stretchv: [3, 1, 4],
        HDW: [0.51, 0.011, 1],
        hd: [.273, -.226]
    },
    0x295F: {
        dir: H,
        sizes: [1],
        stretch: [0x21A6, 0x21BC, 0x21C1],
        stretchv: [3, 1, 4],
        HDW: [0.51, 0.011, 1],
        hd: [.273, -.226]
    },
    0x2983: {
        dir: V,
        sizes: [1.001, 1.101, 1.201, 1.451, 1.801, 2.101, 2.401, 3.001]
    },
    0x2984: {
        dir: V,
        sizes: [1.001, 1.101, 1.201, 1.451, 1.801, 2.101, 2.401, 3.001]
    },
    0x2985: {
        dir: V,
        sizes: [.997, 1.095, 1.195, 1.445, 1.793, 2.093, 2.393, 2.991]
    },
    0x2986: {
        dir: V,
        sizes: [.997, 1.095, 1.195, 1.445, 1.793, 2.093, 2.393, 2.991]
    },
    0x29F8: {
        dir: V,
        sizes: [1.076, 1.917],
        variants: [0, 2]
    },
    0x29F9: {
        dir: V,
        sizes: [1.076, 1.917],
        variants: [0, 2]
    },
    0x29FC: {
        dir: V,
        sizes: [1.001, 1.083, 1.185, 1.433, 1.793, 2.093, 2.383, 2.997]
    },
    0x29FD: {
        dir: V,
        sizes: [1.001, 1.083, 1.185, 1.433, 1.793, 2.093, 2.383, 2.997]
    },
    0x2A00: {
        dir: V,
        sizes: [.987, 1.305],
        variants: [0, 2]
    },
    0x2A01: {
        dir: V,
        sizes: [.987, 1.305],
        variants: [0, 2]
    },
    0x2A02: {
        dir: V,
        sizes: [.987, 1.305],
        variants: [0, 2]
    },
    0x2A03: {
        dir: V,
        sizes: [1.023, 1.357],
        variants: [0, 2]
    },
    0x2A04: {
        dir: V,
        sizes: [1.023, 1.357],
        variants: [0, 2]
    },
    0x2A05: {
        dir: V,
        sizes: [1.029, 1.373],
        variants: [0, 2]
    },
    0x2A06: {
        dir: V,
        sizes: [1.029, 1.373],
        variants: [0, 2]
    },
    0x2A07: {
        dir: V,
        sizes: [1.045, 1.907],
        variants: [0, 2]
    },
    0x2A08: {
        dir: V,
        sizes: [1.045, 1.907],
        variants: [0, 2]
    },
    0x2A09: {
        dir: V,
        sizes: [.981, 1.261],
        variants: [0, 2]
    },
    0x2A0A: {
        dir: V,
        sizes: [1.001, 1.401],
        variants: [0, 2]
    },
    0x2A0B: {
        dir: V,
        sizes: [1.112, 2.223],
        variants: [0, 2]
    },
    0x2A0C: {
        dir: V,
        sizes: [1.112, 2.223],
        variants: [0, 2]
    },
    0x2A0D: {
        dir: V,
        sizes: [1.112, 2.223],
        variants: [0, 2]
    },
    0x2A0E: {
        dir: V,
        sizes: [1.112, 2.223],
        variants: [0, 2]
    },
    0x2A0F: {
        dir: V,
        sizes: [1.112, 2.223],
        variants: [0, 2]
    },
    0x2A10: {
        dir: V,
        sizes: [1.112, 2.223],
        variants: [0, 2]
    },
    0x2A11: {
        dir: V,
        sizes: [1.112, 2.223],
        variants: [0, 2]
    },
    0x2A12: {
        dir: V,
        sizes: [1.112, 2.223],
        variants: [0, 2]
    },
    0x2A13: {
        dir: V,
        sizes: [1.112, 2.223],
        variants: [0, 2]
    },
    0x2A14: {
        dir: V,
        sizes: [1.112, 2.223],
        variants: [0, 2]
    },
    0x2A15: {
        dir: V,
        sizes: [1.112, 2.223],
        variants: [0, 2]
    },
    0x2A16: {
        dir: V,
        sizes: [1.112, 2.223],
        variants: [0, 2]
    },
    0x2A17: {
        dir: V,
        sizes: [1.112, 2.223],
        variants: [0, 2]
    },
    0x2A18: {
        dir: V,
        sizes: [1.112, 2.223],
        variants: [0, 2]
    },
    0x2A19: {
        dir: V,
        sizes: [1.112, 2.223],
        variants: [0, 2]
    },
    0x2A1A: {
        dir: V,
        sizes: [1.112, 2.223],
        variants: [0, 2]
    },
    0x2A1B: {
        dir: V,
        sizes: [1.274, 2.464],
        variants: [0, 2]
    },
    0x2A1C: {
        dir: V,
        sizes: [1.274, 2.486],
        variants: [0, 2]
    },
    0x2A1D: {
        dir: V,
        sizes: [.767, 1.073],
        variants: [0, 2]
    },
    0x2A1E: {
        dir: V,
        sizes: [.767, 1.074],
        variants: [0, 2]
    },
    0x2A20: {
        dir: V,
        sizes: [.595, .835],
        variants: [0, 2]
    },
    0x2A21: {
        dir: V,
        sizes: [.901, 1.261],
        variants: [0, 2]
    },
    0x2AFC: {
        dir: V,
        sizes: [1.001, 1.915],
        variants: [0, 2]
    },
    0x2AFF: {
        dir: V,
        sizes: [1.241, 1.915],
        variants: [0, 2]
    },
    0x3008: {
        c: 0x27E8,
        dir: V,
        sizes: [1.001, 1.101, 1.201, 1.451, 1.801, 2.101, 2.401, 3.001]
    },
    0x3009: {
        c: 0x27E9,
        dir: V,
        sizes: [1.001, 1.101, 1.201, 1.451, 1.801, 2.101, 2.401, 3.001]
    },
    0xFE37: {
        c: 0x23DE,
        dir: H,
        sizes: [.492, .993, 1.494, 1.996, 2.498, 3, 3.502, 4.006],
        stretch: [0x23DE, 0xAF, 0x23DE, 0x23DE],
        stretchv: [3, 1, 4, 1],
        HDW: [0.85, -0.493, .492],
        hd: [.724, -.618]
    },
    0xFE38: {
        c: 0x23DF,
        dir: H,
        sizes: [.492, .993, 1.494, 1.996, 2.498, 3, 3.502, 4.006],
        stretch: [0x23DF, 0x5F, 0x23DF, 0x23DF],
        stretchv: [3, 1, 4, 1],
        HDW: [-0.062, 0.419, .492],
        hd: [-.188, .294]
    },
    0x1EEF0: {
        dir: V,
        sizes: [.527, .738]
    },
    0x1EEF1: {
        dir: V,
        sizes: [.531, .744]
    }
};
//# sourceMappingURL=delimiters.js.map