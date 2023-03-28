module.exports = {
    env: {
        jest: true
    },
    globals: {
        noUiSlider: true
    },
    extends: [
        '../../../vendor/shopware/storefront/Resources/app/storefront/.eslintrc.js',
        '.eslintrc.js',
    ],
    parserOptions: {
        requireConfigFile: false,
    },
};
