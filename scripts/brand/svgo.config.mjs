// SVGO settings for the traced brand marks (resources/brand/*.svg) and public/icon.svg.
//
// The tracer writes every coordinate as an integer on a 1/6-master-pixel grid and only uses
// integer translations and ±1 scales, so floatPrecision 0 is lossless here: SVGO applies the
// transforms, converts to compact relative path data and merges the parts into one <path>.
export default {
  multipass: true,
  floatPrecision: 0,
  js2svg: { eol: 'lf', finalNewline: true },
  plugins: [
    'preset-default',
    'removeDimensions',
    { name: 'removeAttrs', params: { attrs: ['svg:version', 'svg:preserveAspectRatio', 'svg:xml:space'] } },
  ],
};
