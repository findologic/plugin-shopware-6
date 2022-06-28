module.exports = {
    env: {
        jest: true
    },
    globals: {
        noUiSlider: true
    },
    extends: [
        '../../../vendor/shopware/platform/src/Storefront/Resources/app/storefront/.eslintrc.js',
        '.eslintrc.js',
    ],
};
