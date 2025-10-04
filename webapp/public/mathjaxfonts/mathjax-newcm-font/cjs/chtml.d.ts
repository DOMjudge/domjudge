import { ChtmlFontData, ChtmlCharOptions, ChtmlVariantData, ChtmlDelimiterData, DelimiterMap, CharMapMap } from '@mathjax/src/cjs/output/chtml/FontData.js';
import { StringMap } from '@mathjax/src/cjs/output/common/Wrapper.js';
declare const Base: import("@mathjax/src/cjs/output/common/FontData.js").FontDataClass<ChtmlCharOptions, ChtmlVariantData, ChtmlDelimiterData> & typeof ChtmlFontData;
export declare class MathJaxNewcmFont extends Base {
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
        'mjx-container[jax="CHTML"] > mjx-math.NCM-N[breakable] > *': {
            'font-family': string;
        };
        '.NCM-N': {
            'font-family': string;
        };
        '.NCM-B': {
            'font-family': string;
        };
        '.NCM-I': {
            'font-family': string;
        };
        '.NCM-BI': {
            'font-family': string;
        };
        '.NCM-DS': {
            'font-family': string;
        };
        '.NCM-F': {
            'font-family': string;
        };
        '.NCM-FB': {
            'font-family': string;
        };
        '.NCM-SS': {
            'font-family': string;
        };
        '.NCM-SSB': {
            'font-family': string;
        };
        '.NCM-SSI': {
            'font-family': string;
        };
        '.NCM-SSBI': {
            'font-family': string;
        };
        '.NCM-M': {
            'font-family': string;
        };
        '.NCM-SO': {
            'font-family': string;
        };
        '.NCM-LO': {
            'font-family': string;
        };
        '.NCM-S3': {
            'font-family': string;
        };
        '.NCM-S4': {
            'font-family': string;
        };
        '.NCM-S5': {
            'font-family': string;
        };
        '.NCM-S6': {
            'font-family': string;
        };
        '.NCM-S7': {
            'font-family': string;
        };
        '.NCM-MI': {
            'font-family': string;
        };
        '.NCM-C': {
            'font-family': string;
        };
        '.NCM-CB': {
            'font-family': string;
        };
        '.NCM-OS': {
            'font-family': string;
        };
        '.NCM-OB': {
            'font-family': string;
        };
        '.NCM-V': {
            'font-family': string;
        };
        '.NCM-LT': {
            'font-family': string;
        };
        '.NCM-RB': {
            'font-family': string;
        };
        '.NCM-EM': {
            'font-family': string;
        };
        '.NCM-Be': {
            'font-family': string;
        };
        '.NCM-U': {
            'font-family': string;
        };
        '.NCM-Ue': {
            'font-family': string;
        };
        '.NCM-S': {
            'font-family': string;
        };
        '.NCM-SB': {
            'font-family': string;
        };
    };
    protected static defaultFonts: {
        '@font-face /* MJX-NCM-ZERO */': {
            'font-family': string;
            src: string;
        };
        '@font-face /* MJX-BRK */': {
            'font-family': string;
            src: string;
        };
        '@font-face /* MJX-NCM-N */': {
            'font-family': string;
            src: string;
        };
        '@font-face /* MJX-NCM-B */': {
            'font-family': string;
            src: string;
        };
        '@font-face /* MJX-NCM-I */': {
            'font-family': string;
            src: string;
        };
        '@font-face /* MJX-NCM-BI */': {
            'font-family': string;
            src: string;
        };
        '@font-face /* MJX-NCM-DS */': {
            'font-family': string;
            src: string;
        };
        '@font-face /* MJX-NCM-F */': {
            'font-family': string;
            src: string;
        };
        '@font-face /* MJX-NCM-FB */': {
            'font-family': string;
            src: string;
        };
        '@font-face /* MJX-NCM-SS */': {
            'font-family': string;
            src: string;
        };
        '@font-face /* MJX-NCM-SSB */': {
            'font-family': string;
            src: string;
        };
        '@font-face /* MJX-NCM-SSI */': {
            'font-family': string;
            src: string;
        };
        '@font-face /* MJX-NCM-SSBI */': {
            'font-family': string;
            src: string;
        };
        '@font-face /* MJX-NCM-M */': {
            'font-family': string;
            src: string;
        };
        '@font-face /* MJX-NCM-SO */': {
            'font-family': string;
            src: string;
        };
        '@font-face /* MJX-NCM-LO */': {
            'font-family': string;
            src: string;
        };
        '@font-face /* MJX-NCM-S3 */': {
            'font-family': string;
            src: string;
        };
        '@font-face /* MJX-NCM-S4 */': {
            'font-family': string;
            src: string;
        };
        '@font-face /* MJX-NCM-S5 */': {
            'font-family': string;
            src: string;
        };
        '@font-face /* MJX-NCM-S6 */': {
            'font-family': string;
            src: string;
        };
        '@font-face /* MJX-NCM-S7 */': {
            'font-family': string;
            src: string;
        };
        '@font-face /* MJX-NCM-MI */': {
            'font-family': string;
            src: string;
        };
        '@font-face /* MJX-NCM-C */': {
            'font-family': string;
            src: string;
        };
        '@font-face /* MJX-NCM-CB */': {
            'font-family': string;
            src: string;
        };
        '@font-face /* MJX-NCM-OS */': {
            'font-family': string;
            src: string;
        };
        '@font-face /* MJX-NCM-OB */': {
            'font-family': string;
            src: string;
        };
        '@font-face /* MJX-NCM-V */': {
            'font-family': string;
            src: string;
        };
        '@font-face /* MJX-NCM-LT */': {
            'font-family': string;
            src: string;
        };
        '@font-face /* MJX-NCM-RB */': {
            'font-family': string;
            src: string;
        };
        '@font-face /* MJX-NCM-EM */': {
            'font-family': string;
            src: string;
        };
        '@font-face /* MJX-NCM-Be */': {
            'font-family': string;
            src: string;
        };
        '@font-face /* MJX-NCM-U */': {
            'font-family': string;
            src: string;
        };
        '@font-face /* MJX-NCM-Ue */': {
            'font-family': string;
            src: string;
        };
        '@font-face /* MJX-NCM-S */': {
            'font-family': string;
            src: string;
        };
        '@font-face /* MJX-NCM-SB */': {
            'font-family': string;
            src: string;
        };
    };
    protected static dynamicFiles: import("@mathjax/src/cjs/output/common/FontData.js").DynamicFileList;
    cssFontPrefix: string;
}
export {};
