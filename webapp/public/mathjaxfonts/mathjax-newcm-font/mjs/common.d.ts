import { FontDataClass, CharOptions, VariantData, DelimiterData } from '@mathjax/src/mjs/output/common/FontData.js';
export declare function CommonMathJaxNewcmFontMixin<C extends CharOptions, V extends VariantData<C>, D extends DelimiterData, B extends FontDataClass<C, V, D>>(Base: B): FontDataClass<C, V, D> & B;
