/**
 * SVGO config for the Summit icon set (see docs/icons.md).
 *
 *   npx svgo --config resources/icons/svgo.config.mjs -f resources/icons
 *
 * Keeps the viewBox and the explicit butt caps / mitre joins, keeps each primitive
 * (line, rect, circle, polyline, path) as written so a glyph stays readable and editable,
 * and strips anything the inline component must never inherit (ids, classes, styles, sizes).
 */
export default {
    multipass: true,
    floatPrecision: 3,
    js2svg: { pretty: false, finalNewline: true },
    plugins: [
        {
            name: 'preset-default',
            params: {
                overrides: {
                    // Primitives stay primitives: no rect/line/polyline -> path conversion, no merging.
                    convertShapeToPath: false,
                    mergePaths: false,
                    // stroke-linecap="butt" and stroke-linejoin="miter" are SVG defaults; keep them
                    // anyway so a host stylesheet can never round the flat terminals.
                    removeUnknownsAndDefaults: { defaultAttrs: false },
                },
            },
        },
        // Size comes from the component (1em) or CSS, never from the file.
        'removeDimensions',
        { name: 'removeAttrs', params: { attrs: ['id', 'class', 'style', 'data-.*'] } },
        'removeStyleElement',
        'removeScripts',
    ],
};
