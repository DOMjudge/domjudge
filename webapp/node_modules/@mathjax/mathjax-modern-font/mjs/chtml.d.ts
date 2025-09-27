import { ChtmlFontData, ChtmlCharOptions, ChtmlVariantData, ChtmlDelimiterData, DelimiterMap, CharMapMap } from '@mathjax/src/mjs/output/chtml/FontData.js';
import { StringMap } from '@mathjax/src/mjs/output/common/Wrapper.js';
declare const Base: import("@mathjax/src/mjs/output/common/FontData.js").FontDataClass<ChtmlCharOptions, ChtmlVariantData, ChtmlDelimiterData> & typeof ChtmlFontData;
export declare class MathJaxModernFont extends Base {
    static NAME: string;
    static OPTIONS: {
        fontURL: string;
        dynamicPrefix: string;
    };
    protected static defaultCssFamilyPrefix: string;
    protected static defaultVariantLetters: StringMap;
    protected static defaultDelimiters: DelimiterMap<ChtmlDelimiterData>;
    protected static defaultChars: CharMapMap<ChtmlCharOptions>;
    protected static defaultStyles: {
        'mjx-container[jax="CHTML"] > mjx-math.MM-N[breakable] > *': {
            'font-family': string;
        };
        '.MM-N': {
            'font-family': string;
        };
        '.MM-B': {
            'font-family': string;
        };
        '.MM-I': {
            'font-family': string;
        };
        '.MM-BI': {
            'font-family': string;
        };
        '.MM-DS': {
            'font-family': string;
        };
        '.MM-F': {
            'font-family': string;
        };
        '.MM-FB': {
            'font-family': string;
        };
        '.MM-S': {
            'font-family': string;
        };
        '.MM-SB': {
            'font-family': string;
        };
        '.MM-SS': {
            'font-family': string;
        };
        '.MM-SSB': {
            'font-family': string;
        };
        '.MM-SSI': {
            'font-family': string;
        };
        '.MM-SSBI': {
            'font-family': string;
        };
        '.MM-M': {
            'font-family': string;
        };
        '.MM-SO': {
            'font-family': string;
        };
        '.MM-LO': {
            'font-family': string;
        };
        '.MM-S3': {
            'font-family': string;
        };
        '.MM-S4': {
            'font-family': string;
        };
        '.MM-S5': {
            'font-family': string;
        };
        '.MM-S6': {
            'font-family': string;
        };
        '.MM-S7': {
            'font-family': string;
        };
        '.MM-MI': {
            'font-family': string;
        };
        '.MM-C': {
            'font-family': string;
        };
        '.MM-CB': {
            'font-family': string;
        };
        '.MM-OS': {
            'font-family': string;
        };
        '.MM-OB': {
            'font-family': string;
        };
        '.MM-V': {
            'font-family': string;
        };
        '.MM-LT': {
            'font-family': string;
        };
        '.MM-RB': {
            'font-family': string;
        };
        '.MM-EM': {
            'font-family': string;
        };
    };
    protected static defaultFonts: {
        '@font-face /* MJX-MM-ZERO */': {
            'font-family': string;
            src: string;
        };
        '@font-face /* MJX-BRK */': {
            'font-family': string;
            src: string;
        };
        '@font-face /* MJX-MM-N */': {
            'font-family': string;
            src: string;
        };
        '@font-face /* MJX-MM-B */': {
            'font-family': string;
            src: string;
        };
        '@font-face /* MJX-MM-I */': {
            'font-family': string;
            src: string;
        };
        '@font-face /* MJX-MM-BI */': {
            'font-family': string;
            src: string;
        };
        '@font-face /* MJX-MM-DS */': {
            'font-family': string;
            src: string;
        };
        '@font-face /* MJX-MM-F */': {
            'font-family': string;
            src: string;
        };
        '@font-face /* MJX-MM-FB */': {
            'font-family': string;
            src: string;
        };
        '@font-face /* MJX-MM-S */': {
            'font-family': string;
            src: string;
        };
        '@font-face /* MJX-MM-SB */': {
            'font-family': string;
            src: string;
        };
        '@font-face /* MJX-MM-SS */': {
            'font-family': string;
            src: string;
        };
        '@font-face /* MJX-MM-SSB */': {
            'font-family': string;
            src: string;
        };
        '@font-face /* MJX-MM-SSI */': {
            'font-family': string;
            src: string;
        };
        '@font-face /* MJX-MM-SSBI */': {
            'font-family': string;
            src: string;
        };
        '@font-face /* MJX-MM-M */': {
            'font-family': string;
            src: string;
        };
        '@font-face /* MJX-MM-SO */': {
            'font-family': string;
            src: string;
        };
        '@font-face /* MJX-MM-LO */': {
            'font-family': string;
            src: string;
        };
        '@font-face /* MJX-MM-S3 */': {
            'font-family': string;
            src: string;
        };
        '@font-face /* MJX-MM-S4 */': {
            'font-family': string;
            src: string;
        };
        '@font-face /* MJX-MM-S5 */': {
            'font-family': string;
            src: string;
        };
        '@font-face /* MJX-MM-S6 */': {
            'font-family': string;
            src: string;
        };
        '@font-face /* MJX-MM-S7 */': {
            'font-family': string;
            src: string;
        };
        '@font-face /* MJX-MM-MI */': {
            'font-family': string;
            src: string;
        };
        '@font-face /* MJX-MM-C */': {
            'font-family': string;
            src: string;
        };
        '@font-face /* MJX-MM-CB */': {
            'font-family': string;
            src: string;
        };
        '@font-face /* MJX-MM-OS */': {
            'font-family': string;
            src: string;
        };
        '@font-face /* MJX-MM-OB */': {
            'font-family': string;
            src: string;
        };
        '@font-face /* MJX-MM-V */': {
            'font-family': string;
            src: string;
        };
        '@font-face /* MJX-MM-LT */': {
            'font-family': string;
            src: string;
        };
        '@font-face /* MJX-MM-RB */': {
            'font-family': string;
            src: string;
        };
        '@font-face /* MJX-MM-EM */': {
            'font-family': string;
            src: string;
        };
    };
    protected static dynamicFiles: import("@mathjax/src/mjs/output/common/FontData.js").DynamicFileList;
    cssFontPrefix: string;
}
export {};
